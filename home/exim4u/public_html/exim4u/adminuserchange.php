<?php
  include_once dirname(__FILE__) . '/config/variables.php';
  include_once dirname(__FILE__) . '/config/authpostmaster.php';
  include_once dirname(__FILE__) . '/config/functions.php';
  include_once dirname(__FILE__) . '/config/httpheaders.php';

   $query = "SELECT * FROM users WHERE user_id=:user_id
		AND domain_id=:domain_id
		AND (type='local' OR type='piped')";
   $sth = $dbh->prepare($query);
   $sth->execute(array(':user_id'=>$_GET['user_id'], ':domain_id'=>$_SESSION['domain_id']));
   if ($sth->rowCount()) { $row = $sth->fetch(); }
  
  $username = $row['username'];
  $domquery = "SELECT spamassassin,quotas,pipe,maxmsgsize FROM domains
  WHERE domain_id=:domain_id";
  $domsth = $dbh->prepare($domquery);
  $domsth->execute(array(':domain_id'=>$_SESSION['domain_id']));
  if ($domsth->rowCount()) {
    $domrow = $domsth->fetch();
  }
  $blockquery = "SELECT blockhdr,blockval,block_id FROM blocklists
     WHERE blocklists.user_id=:user_id";
  $blocksth = $dbh->prepare($blockquery);
  $blocksth->execute(array(':user_id'=>$_GET['user_id']));
?>
<html>
  <head>
    <title><?php echo _('Exim4U') . ': ' . _('Manage Users'); ?></title>
    <link rel="stylesheet" href="style.css" type="text/css">
    <script src="scripts.js" type="text/javascript"></script>
  </head>
  <body onLoad="document.userchange.realname.focus()">
  <?php include dirname(__FILE__) . '/config/header.php'; ?>
    <div id="Menu">
      <a href="adminuser.php"><?php echo _('Manage Accounts'); ?></a><br>
      <a href="admin.php"><?php echo _('Main Menu'); ?></a><br>
      <br><a href="logout.php"><?php echo _('Logout'); ?></a><br>
    </div>
    <div id="forms">
	<?php 
		# ensure this page can only be used to view/edit user accounts that already exist for the domain of the admin account
		if (!$sth->rowCount()) {
			echo '<table align="center"><tr><td>';
			echo "Invalid userid '" . htmlentities($_GET['user_id']) . "' for domain '" . htmlentities($_SESSION['domain']). "'";			
			echo '</td></tr></table>';
		}else{
	?>
	
    <form name="userchange" method="post" action="adminuserchangesubmit.php">
      <table align="center">
        <tr>
          <td><?php echo _('Name'); ?>:</td>
          <td>
            <input type="text" size="25" name="realname"
              value="<?php print $row['realname']; ?>" class="textfield">
            <input name="user_id" type="hidden"
              value="<?php print $_GET['user_id']; ?>">
          </td>
        </tr>
        <tr>
          <td><?php echo _('Email Address'); ?>:</td>
          <td><?php print $row['username']; ?></td>
        </tr>
        <tr>
          <td><?php echo _('Password'); ?>:</td>
          <td>
            <input type="password" size="25" id="clear" name="clear" class="textfield">
          </td>
        </tr>
        <tr>
          <td><?php echo _('Verify Password'); ?>:</td>
          <td>
            <input type="password" size="25" id="vclear" name="vclear" class="textfield">
          </td>
        </tr>
        <tr>
          <td></td>
          <td>
            <input type="button" value="<?php echo _('Generate password'); ?>" onclick="suggestPassword('suggest')">
            <input type="text" size="20" id="suggest" class="textfield">
            <input type="button" value="<?php echo _('Copy'); ?>" onclick="copyPassword('suggest', 'clear', 'vclear')">
          </td>
        </tr>
        <?php
          if ($postmasteruidgid == "yes") { ?>
          <tr>
            <td><?php echo _('UID'); ?>:</td>
            <td>
              <input type="text" size="25" name="uid" class="textfield"
                value="<?php echo $row['uid']; ?>">
            </td>
          </tr>
          <tr>
            <td><?php echo _('GID'); ?>:</td>
            <td>
              <input type="text" size="25" name="gid" class="textfield"
                value="<?php echo $row['gid']; ?>">
            </td>
          </tr> 
          <tr>
            <td colspan="2" style="padding-bottom:1em">
              <?php echo _('When you update the UID or GID, please make sure
                your MTA still has permission to create the required user
                directories!'); ?>
            </td>
          </tr>
        <?php
          }
        ?>
            <tr></tr>
            <tr>
               <td>
                 <?php printf (_('Mailbox quota:')); ?></td>
                <td>
                  <input type="text" size="5" name="quota" class="textfield"
                    value="<?php echo ($domrow['quotas'] == 0 ? $row['quota'] : ($row['quota'] == 0 ? $domrow['quotas'] : min($domrow['quotas'], $row['quota']))); ?>">
                    <?php printf(_('MB (%s MB Max, 0=unlimited)'),$domrow['quotas']); ?>
                </td>
              </tr>
          <?php
           //}
            if ((function_exists('imap_get_quotaroot'))
              && ($imap_to_check_quota == "yes")) {
              $mbox = imap_open(
                $imapquotaserver, $row['username'], $row['clear'], OP_HALFOPEN
              );
              $quota = imap_get_quotaroot($mbox, "INBOX");
              if (is_array($quota) && !empty($quota)) {
              printf ("<tr><td>"
                . _('Space used:')
                . "</td><td>"
                . _('%.2f MB')
                . "</td></tr>",
                $quota['STORAGE']['usage'] / 1024);
              }
              imap_close($mbox);
            }
          if ($domrow['pipe'] == "1") {
          ?>
          <tr>
            <td><?php echo _('Pipe To Command Or Alternative Maildir'); ?>:</td>
            <td>
              <input type="textfield" size="25" name="smtp" class="textfield"
                value="<?php echo $row['smtp']; ?>">
            </td>
          </tr>
          <tr>
            <td colspan="2" style="padding-bottom:1em">
              <?php echo _('Optional'); ?>:
              <?php echo _('Pipe all mail to a command (e.g. procmail).'); ?>
              <br>
              <?php echo _('Check box below to enable'); ?>:
            </td>
          </tr>
          <tr>
            <td><?php _('Enable piped command or alternative Maildir?'); ?></td>
            <td>
              <input type="checkbox" name="on_piped"
              <?php
                if ($row['on_piped'] == "1") {
                  print " checked ";
                } ?>>
            </td>
          </tr>
        <?php
          }
        ?>
        <tr>
          <td>
            <?php echo _('Admin'); ?>:</td>
            <td>
              <input name="admin" type="checkbox"<?php if ($row['admin'] == 1) { 
                print " checked";
              } ?> class="textfield">
            </td>
          </tr>
        <?php
           if ($domrow['spamassassin'] == "1") {
        ?>
            <tr>
              <td><?php echo _('Spamassassin'); ?>:</td>
              <td><input name="on_spamassassin" type="checkbox"
                <?php if ($row['on_spamassassin'] == "1") {
                  print " checked";
                }?>>
              </td>
            </tr>
            <tr>
          <tr>
            <td><?php echo _('Enable Spam Box'); ?>:</td>
              <td><input name="on_spambox" type="checkbox"
                <?php if ($row['on_spambox'] == "1") {
                  print " checked";
                }?>>
              </td>
      </tr><tr>
       <td><?php echo _('Enable Spam Box Report'); ?>:</td>
              <td><input name="on_spamboxreport" type="checkbox"
                <?php if ($row['on_spamboxreport'] == "1") {
                  print " checked";
                }?>>
              </td>      
          </tr>
          <tr>
            <td><?php echo _(' '); ?></td>
          </tr>
              <td><?php echo _('Spamassassin Tag Score'); ?>:</td>
              <td>
                <input type="text" size="5" name="sa_tag"
                  value="<?php echo $row['sa_tag']; ?>" class="textfield">
              </td>
            </tr>
            <tr>
              <td><?php echo _('Spamassassin Discard Score'); ?>:</td>
              <td>
                <input type="text" size="5" name="sa_refuse"
                  value="<?php echo $row['sa_refuse']; ?>" class="textfield">
              </td> </tr>
          <?php
            }
          ?>
        <tr>
          <td><?php printf (_('Maximum Message Size:')); ?></td>
          <td>
            <input type="text" size="5" name="maxmsgsize"
              value="<?php echo $row['maxmsgsize']; ?>" class="textfield">
              <?php printf(_('KB (%s KB Max, 0=unlimited)'),$domrow['maxmsgsize']); ?>
          </td>
        </tr>
        <tr>
          <td><?php echo _('Enabled'); ?>:</td>
          <td><input name="enabled" type="checkbox" <?php
            if ($row['enabled'] == 1) {
              print "checked";
            } ?> class="textfield">
          </td>
        </tr>
        <tr>
          <td><?php echo _('Vacation On'); ?>:</td>
          <td><input name="on_vacation" type="checkbox" <?php
            if ($row['on_vacation'] == "1") {
              print " checked ";
            } ?>>
          </td>
        </tr>
        <tr>
        <?php if (function_exists('imap_qprint')) { ?>
          <td><?php echo _('Vacation Message'); ?>:</td>
          <td>
            <textarea name="vacation" cols="40" rows="5" class="textfield"><?php print imap_qprint($row['vacation']); ?></textarea>
          </td>
        <?php } else { ?>
          <td><?php echo _('Vacation message (ASCII only!)'); ?>:</td>
          <td>
            <textarea name="vacation" cols="40" rows="5" class="textfield"><?php print $row['vacation']; ?></textarea>
          </td>
        <?php } ?>
        </tr>
        <tr>
          <td><?php echo _('Forwarding Enabled'); ?>:</td>
          <td><input name="on_forward" type="checkbox" <?php
            if ($row['on_forward'] == "1") {
              print " checked";
            } ?>>
          </td>
        </tr>
        <tr>
          <td><?php echo _('Forward Mail To'); ?>:<br><br><br><br></td>
          <td>
            <input type="text" size="25" name="forward" value="<?php print $row['forward']; ?>" class="textfield"><br>
            <?php echo _('Must be a full e-mail address'); ?>!<br>
            <?php echo _('OR') .":<br>\n"; ?>
            <select name="forwardmenu">
              <option selected value=""></option>
              <?php
                $queryuserlist = "SELECT realname, username, user_id, unseen
                FROM users
                WHERE enabled='1' AND domain_id=:domain_id AND type != 'fail'
                ORDER BY realname, username, type desc";
                $sthuserlist = $dbh->prepare($queryuserlist);
                $sthuserlist->execute(array(':domain_id'=>$_SESSION['domain_id']));
                while ($rowuserlist = $sthuserlist->fetch()) {
              ?>
                <option value="<?php echo $rowuserlist['username']; ?>">
                  <?php echo $rowuserlist['realname']; ?>
                  (<?php echo $rowuserlist['username']; ?>)
                </option>
              <?php 
                }
              ?>
            </select>
          </td>
        </tr>
        <tr>
          <td><?php echo _('Store Forwarded Mail Locally'); ?>:</td>
          <td><input name="unseen" type="checkbox" <?php
            if ($row['unseen'] == "1") {
              print " checked ";
            } ?>>
            <input name="user_id" type="hidden"
              value="<?php print $_GET['user_id']; ?>">
            <input name="localpart" type="hidden"
              value="<?php print $row['localpart']; ?>">
          </td>
        </tr>
        <tr>
          <td colspan="2" class="button">
          <input name="submit" type="submit" value="<?php echo _('Submit'); ?>">
          </td>
        </tr>
        <tr>
          <td colspan="2" style="padding-top:1em">
          <?php
            # Print the aliases associated with this account
            $query = "SELECT user_id,localpart,domain,realname FROM users,domains
            WHERE smtp=:smtp AND users.domain_id=domains.domain_id ORDER BY realname";
            $sth = $dbh->prepare($query);
            $sth->execute(array(':smtp'=>$row['localpart'].'@'.$_SESSION['domain']));
            if ($sth->rowCount()) {
          ?>
          <?php
            echo _('Aliases to this account'); ?>:<br>
          <?php
              while ($row = $sth->fetch()) {
                if (($row['domain'] == $_SESSION['domain'])
                  && ($row['localpart'] != "*")) {
                  print '<a href="adminaliaschange.php?user_id='
                    . $row['user_id']
                    . '">'
                    . $row['localpart']. '@' . $row['domain']
                    . '</a>';
              } else if (($row['domain'] == $_SESSION['domain'])
                && ($row['localpart'] == "*")) {
                print '<a href="admincatchall.php?user_id='
                 . $row['user_id']
                 . '">'
                 . $row['localpart'] . '@' . $row['domain']
                 . '</a>';
              } else {
                print $row['localpart'] . '@' . $row['domain'];
              }
              if ($row['realname'] == "Catchall") {
                print $row['realname'];
              }
                print '<br>';
              }
            }
          ?>
        </td></tr>
      </table>
    </form>
    <br>
    <form name="blocklist" method="post" action="adminuserblocksubmit.php">
      <table align="center">
        <tr>
          <td colspan="2">
           <h4>
             <?php
               echo _('Add a new header blocking filter for this user');
             ?>:
           </h4>
          </td>
        </tr>
        <tr>
          <td><?php echo _('Header'); ?>:</td>
          <td>
            <select name="blockhdr" class="textfield">
              <option value="From"><?php echo _('From'); ?>:</option>
              <option value="To"><?php echo _('To'); ?>:</option>
              <option value="Subject"><?php echo _('Subject'); ?>:</option>
              <option value="X-Mailer"><?php echo _('X-Mailer'); ?>:</option>
            </select>
          </td>
          <td>
            <input name="blockval" type="text" size="25" class="textfield">
            <input name="user_id" type="hidden"
              value="<?php print $_GET['user_id']; ?>">
            <input name="localpart" type="hidden"
              value="<?php print $_GET['localpart']; ?>">
            <input name="color" type="hidden" value="black">
          </td>
        </tr>
        <tr>
          <td colspan="3" class="button">
            <input name="submit" type="submit"
              value="<?php echo _('Submit'); ?>">
          </td>
        </tr>
      </table>
    </form>
    <table align="center">
      <tr>
        <td><?php echo _('Blocked'); ?></td>
        <td><?php echo _('Headers Listed Below'); ?></td>
        <td><?php echo _('(mail will be deleted):'); ?></td>
      </tr>
      <?php
        if ($blocksth->rowCount()) {
        while ($blockrow = $blocksth->fetch()) {
      ?>
            <tr>
              <td>
                <a href="adminuserblocksubmit.php?action=delete&user_id=<?php 
					print $_GET['user_id']
					. '&block_id='
					. $blockrow['block_id']
					.'&localpart='
					. $_GET['localpart'];?>">
                  <img class="trash" title="Delete" src="images/trashcan.gif"
                    alt="trashcan">
                </a>
              </td>
              <td><?php echo $blockrow['blockhdr']; ?></td>
              <td><?php echo $blockrow['blockval']; ?></td>
            </tr>
        <?php
          }
        }
      ?>
    </table>
	<?php 		
		# end of the block editing an alias within the domain
	}  
	?>	
    </div>
  </body>
</html>
<!-- Layout and CSS tricks obtained from http://www.bluerobot.com/web/layouts/ -->
