<?php

#TODO:
# acls
# trigger passwd file update on cvs.php.net
# trigger mail alias update on php.net mx
# handle flipping of the sort views

require_once 'login.inc';
require_once 'functions.inc';
require_once 'email-validation.inc';

$mailto = "group@php.net";

head("user administration");

@mysql_connect("localhost","nobody","")
  or die("unable to connect to database");
@mysql_select_db("php3");

# ?username=whatever will look up 'whatever' by email or cvs username
if (isset($username) && !isset($id)) {
  $query = "SELECT userid FROM users"
         . " WHERE username='$username' OR email='$username'";
  $res = db_query($query);
  if (!($id = @mysql_result($res,0))) {
    warn("wasn't able to find user matching '".clean($username)."'");
  }
}

if (isset($id) && isset($action)) {
  if (!is_admin($user)) {
    warn("you're not allowed to take actions on users.");
    exit;
  }
  $id = (int)$id;
  switch ($action) {
  case 'approve':
    if (db_query("UPDATE users SET cvsaccess=1 WHERE userid=$id")
     && mysql_affected_rows()) {
      $userinfo = fetch_user($id);
      $message =
"Your CVS account ($userinfo[username]) was created.

You should be able to log into the CVS server within the hour, and
your $userinfo[username]@php.net forward to $userinfo[email] should
be active within the next 24 hours.

Welcome to the PHP development team! If you encounter any problems
with your CVS account, feel free to send us a note at group@php.net.";
      mail($userinfo[email],"CVS Account Request: $userinfo[username]",$message,"From: PHP Group <group@php.net>");

      mail($mailto,"CVS Account Request: $userinfo[username] approved by $user","Approved $userinfo[username]","From: PHP Group <group@php.net>\nIn-Reply-To: <cvs-account-$id-admin@php.net>");
      if (!$noclose) {
        echo '<script language="javascript">window.close();</script>';
        exit;
      }
      warn("record $id ($userinfo[username]) approved");
    }
    else {
      warn("wasn't able to grant cvs access to id $id.");
    }
    break;
  case 'remove':
    $userinfo = fetch_user($id);
    if (db_query("DELETE FROM users WHERE userid=$id")
     && mysql_affected_rows()) {
      $message = $userinfo[cvsaccess] ? 
"Your CVS account ($userinfo[username]) was deleted.

Feel free to send us a note at group@php.net to find out why this
was done."
:
"Your CVS account request ($userinfo[username]) was denied.

The most likely reason is that you did not read the reasons for
which CVS accounts are granted, and your request failed to meet
the list of acceptable criteria.

If you'd like to make another appeal for a CVS account, feel free
to send us a note at group@php.net.";
      mail($userinfo[email],"CVS Account Request: $userinfo[username]",$message,"From: PHP Group <group@php.net>");
      mail($mailto,$userinfo[cvsaccess] ? "CVS Account Deleted: $userinfo[username] deleted by $user" : "CVS Account Rejected: $userinfo[username] rejected by $user","Nuked $userinfo[username]","From: PHP Group <group@php.net>\nIn-Reply-To: <cvs-account-$id-admin@php.net>");
      db_query("DELETE FROM users_note WHERE userid=$id");
      if (!$noclose) {
        echo '<script language="javascript">window.close();</script>';
        exit;
      }
      warn("record $id ($userinfo[username]) removed");
    }
    else {
      warn("wasn't able to delete id $id.");
    }
    break;
  default:
    warn("that action ('$action') is not understood.");
  }
}

if (isset($id) && isset($in)) {
  if (!can_modify($user,$id)) {
    warn("you're not allowed to modify this user.");
  }
  else {
    if ($error = invalid_input($in)) {
      warn($error);
    }
    else {
      if ($in[rawpasswd]) {
        $in[passwd] = crypt($in[rawpasswd],substr(md5(time()),0,2));
      }
      $cvsaccess = $in[cvsaccess] ? 1 : 0;
      $spamprotect = $in[spamprotect] ? 1 : 0;
      $verified = $in[verified] ? 1 : 0;

      if ($id) {
        # update main table data
        if (isset($in[email]) && isset($in[name])) {
          $query = "UPDATE users SET name='$in[name]',email='$in[email]'"
                 . ($in[passwd] ? ",passwd='$in[passwd]'" : "")
                 . ((is_admin($user) && $in[username]) ? ",username='$in[username]'" : "")
                 . (is_admin($user) ? ",cvsaccess=$cvsaccess" : "")
                 . ",spamprotect=$spamprotect"
                 . ",verified=$verified"
                 . " WHERE userid=$id";
          db_query($query);
          if(strlen($in['purpose'])) {
              $purpose = addslashes($in['purpose']);
              $query = "INSERT INTO users_note (userid, note, entered) VALUES ($id, '$purpose', NOW())";
              db_query($query);
          }
        }

        warn("record $id updated");
        unset($id);
      }
      else {
        $query = "INSERT users SET name='$in[name]',email='$in[email]'"
               . ($in[username] ? ",username='$in[username]'" : "")
               . ($in[passwd] ? ",passwd='$in[passwd]'" : "")
               . (is_admin($user) ? ",cvsaccess=$cvsaccess" : "")
               . ",spamprotect=$spamprotect"
               . ",verified=$verified";
        db_query($query);

        $nid = mysql_insert_id();

        warn("record $nid added");
      }
    }
  }
}

if ($id) {
  $query = "SELECT * FROM users"
         . " WHERE users.userid=$id";
  $res = db_query($query);
  $row = mysql_fetch_array($res);
  if (!$row) unset($id);
}

if (isset($id)) {
?>
<table>
<form method="post" action="<?php echo $PHP_SELF;?>">
<input type="hidden" name="id" value="<?php echo $row[userid];?>" />
<tr>
 <th align="right">Name:</th>
 <td><input type="text" name="in[name]" value="<?php echo htmlspecialchars($row[name]);?>" size="40" maxlength="255" /></td>
</tr>
<tr>
 <th align="right">Email:</th>
 <td><input type="text" name="in[email]" value="<?php echo htmlspecialchars($row[email]);?>" size="40" maxlength="255" /></td>
</tr>
<tr>
 <td colspan="2">Leave password fields blank to leave password unchanged.</td>
</tr>
<tr>
 <th align="right">Password:</th>
 <td><input type="password" name="in[rawpasswd]" value="" size="20" maxlength="20" /></td>
</tr>
<tr>
 <th align="right">Password (again):</th>
 <td><input type="password" name="in[rawpasswd2]" value="" size="20" maxlength="20" /></td>
</tr>
<?php if (is_admin($user)) {?>
<tr>
 <th align="right">Password (crypted):</th>
 <td><input type="text" name="in[passwd]" value="<?php echo htmlspecialchars($row[passwd]);?>" size="20" maxlength="20" /></td>
</tr>
<tr>
 <th align="right">CVS username:</th>
 <td><input type="text" name="in[username]" value="<?php echo htmlspecialchars($row[username]);?>" size="16" maxlength="16" /></td>
</tr>
<?php }?>
<?php if (is_admin($user)) {?>
<tr>
 <th align="right">CVS access?</th>
 <td><input type="checkbox" name="in[cvsaccess]"<?php echo $row[cvsaccess] ? " checked" : "";?> /></td>
</tr>
<?php }?>
<tr>
 <th align="right">Use spam protection?</th>
 <td><input type="checkbox" name="in[spamprotect]"<?php echo $row[spamprotect] ? " checked" : "";?> /></td>
</tr>
<tr>
 <th align="right">Verified?</th>
 <td><input type="checkbox" name="in[verified]"<?php echo $row[verified] ? " checked" : "";?> /></td>
</tr>
<tr>
 <th align="right">Add Note: </th>
 <td><textarea cols="50" rows="5" name="in[purpose]"></textarea></td>
</tr>
<tr>
 <td><input type="submit" value="<?php echo $id ? "Update" : "Add";?>" />
</tr>
</form>
<?php if (is_admin($user) && !$row[cvsaccess]) {?>
<tr>
 <form method="get" action="<?php echo $PHP_SELF;?>">
  <input type="hidden" name="action" value="remove" />
  <input type="hidden" name="noclose" value="1" />
  <input type="hidden" name="id" value="<?php echo $id?>" />
  <td><input type="submit" value="Reject" />
 </form>
 <form method="get" action="<?php echo $PHP_SELF;?>">
  <input type="hidden" name="action" value="approve" />
  <input type="hidden" name="noclose" value="1" />
  <input type="hidden" name="id" value="<?php echo $id?>" />
  <td><input type="submit" value="Approve" />
 </form>
</tr>
<?php }?>
</table>
<?php
  if ($id) {
    $res = db_query("SELECT note, UNIX_TIMESTAMP(entered) AS ts FROM users_note WHERE userid=$id");
    echo "<b>notes</b>";
    while ($res && $row = mysql_fetch_assoc($res)) {
      echo "<div>", date("r",$row['ts']), "<br />{$row['note']}</div>";
    }
  }
  foot();
  exit;
}
?>
<table width="100%">
 <tr>
  <td>
    <a href="<?php echo "$PHP_SELF?username=$user";?>">edit your entry</a>
  | <a href="<?php echo "$PHP_SELF?unapproved=1";?>">see outstanding requests</a>
  </td>
  <td align="right">
   <form method="GET" action="<?php echo $PHP_SELF;?>">
    <input type="text" name="search" value="<?php echo clean($search);?>" />
    <input type="submit" value="search">
   </form>
 </tr>
</table>
<?php

$begin = $begin ? (int)$begin : 0;
$full = $full ? 1 : (!isset($full) && ($search || $unapproved) ? 1 : 0);
$max = $max ? (int)$max : 20;

$limit = "LIMIT $begin,$max";
$orderby = $order ? "ORDER BY $order" : "";

$searchby = $search ? "WHERE (MATCH(name,email,username) AGAINST ('$search') OR MATCH(note) AGAINST ('$search') OR username = '$search')" : "";
if (!$searchby && $unapproved) {
  $searchby = 'WHERE (username IS NOT NULL AND NOT cvsaccess)';
}
elseif ($unapproved) {
  $searchby .= ' AND (username IS NOT NULL AND NOT cvsaccess)';
}

$query = "SELECT DISTINCT COUNT(users.userid) FROM users";
if ($searchby)
  $query .= " LEFT JOIN users_note USING(userid) $searchby";
$res = db_query($query);
$total = mysql_result($res,0);

$query = "SELECT DISTINCT users.userid,cvsaccess,username,name,email,note FROM users LEFT JOIN users_note USING (userid) $searchby group by userid $orderby $limit";

#echo "<pre>$query</pre>";
$res = db_query($query);

$extra = array(
  "search" => stripslashes($search),
  "order" => $order,
  "begin" => $begin,
  "max" => $max,
  "full" => $full,
  "unapproved" => $unapproved,
);

show_prev_next($begin,mysql_num_rows($res),$max,$total,$extra);
?>
<table border="0" cellspacing="1" width="100%">
<tr bgcolor="#aaaaaa">
 <th><a href="<?php echo "$PHP_SELF?",array_to_url($extra,array("full" => $full ? 0 : 1));?>"><?php echo $full ? "&otimes;" : "&oplus;";?></a></th>
 <th><a href="<?php echo "$PHP_SELF?",array_to_url($extra,array("order"=>"name"));?>">name</a></th>
 <th><a href="<?php echo "$PHP_SELF?",array_to_url($extra,array("order"=>"email"));?>">email</a></th>
 <th><a href="<?php echo "$PHP_SELF?",array_to_url($extra,array("order"=>"username"));?>">username</a></th>
</tr>
<?php
$color = '#dddddd';
while ($row = mysql_fetch_array($res)) {
?>
<tr bgcolor="<?php echo $color;?>">
 <td align="center"><a href="<?php echo "$PHP_SELF?id=$row[userid]";?>">edit</a></td>
 <td><?php echo htmlspecialchars($row[name]);?></td>
 <td><?php echo htmlspecialchars($row[email]);?></td>
 <td<?php if ($row[username] && !$row[cvsaccess]) echo ' bgcolor="#ff',substr($color,2),'"';?>><?php echo htmlspecialchars($row[username]);?><?php if ($row[username] && is_admin($user)) { if (!$row[cvsaccess]) echo " <a href=\"$PHP_SELF?action=approve&amp;noclose=1&amp;id=$row[userid]\" title=\"approve\">+</a>"; echo " <a href=\"$PHP_SELF?action=remove&amp;noclose=1&amp;id=$row[userid]\" title=\"remove\">&times;</a>"; }?></td>
</tr>
<?php
  if ($full && $row[note]) {?>
<tr bgcolor="<?php echo $color;?>">
 <td></td><td colspan="3"><?php echo htmlspecialchars($row[note]);?></td>
</tr>
<?php
  }
  $color = substr($color,2,2) == 'dd' ? '#eeeeee' : '#dddddd';
}
?>
</table>
<?php show_prev_next($begin,mysql_num_rows($res),$max,$total,$extra); ?>
<p><a href="<?php echo $PHP_SELF;?>?id=0">add a new user</a></p>
<?php
foot();

function invalid_input($in) {
  if (isset($in[email]) && !is_emailable_address($in[email])) {
    return "'".clean($in[email])."' does not look like a valid email address";
  }
  if ($in[username] && !preg_match("/^[-\w]+\$/",$in[username])) {
    return "'".clean($in[username])."' is not a valid username";
  }
  if ($in[rawpasswd] && $in[rawpasswd] != $in[rawpasswd2]) {
    return "the passwords you specified did not match!";
  }
  return false;
}

function is_admin($user) {
  #TODO: use acls, once implemented.
  if (in_array($user,array("jimw","rasmus","andrei","zeev","andi","sas","thies","rubys","ssb", "wez"))) return true;
}

# returns false if $user is not allowed to modify $userid
function can_modify($user,$userid) {
  if (is_admin($user)) return true;

  $userid = (int)$userid;

  $quser = addslashes($user);
  $query = "SELECT userid FROM users"
         . " WHERE userid=$userid"
         . "   AND (email='$quser' OR username='$quser')";

  $res = db_query($query);
  return $res ? mysql_num_rows($res) : false;
}

function fetch_user($user) {
  $query = "SELECT * FROM users LEFT JOIN users_note USING (userid)";
  if ((int)$user) {
    $query .= " WHERE users.userid=$user";
  }
  else {
    $quser = addslashes($user);
    $query .= " WHERE username='$quser' OR email='$quser'";
  }

  if ($res = db_query($query)) {
    return mysql_fetch_array($res);
  }

  return false;
}
