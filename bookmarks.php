<?php

/**
 * @author Bastiaan Welmers
 * @copyright 2011
 *
 * @see bookmarks.table.sql
 */

if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != 'on')
	{ header("Location: https://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}"); exit; }
	

require('phpuser.php');

phpuser::requireUser();

if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'logout')
        phpuser::logout();

require('bookmarks.secret.php');

mysql_connect($db_host, $db_user, $db_password);

mysql_select_db($db_database);

if (empty($db_table))
	$db_table = 'bookmarks';

?>
<html>
<head>
<link rel="stylesheet" href="bookmarks.css" type ="text/css" />
<!--[if IE]>
<link rel="stylesheet" href="bookmarks.ie.css" type="text/css" />
<![endif]-->
<title>Bookmarks</title>
</head>
<body>
<div id="loginbar">Ingelogd als: <?php print ($_SESSION['username']); ?> <a href="<?php print ($_SERVER['PHP_SELF']) ; ?>?action=logout">Uitloggen</a></div>
<?php

$username = mysql_real_escape_string($_SESSION['username']);


if (isset($_REQUEST['action'])) {
	if ($_SERVER['REQUEST_METHOD'] == 'POST') { // form posted
		$id = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : null;
		$parent_id = isset($_REQUEST['parent_id']) ? (int) $_REQUEST['parent_id'] : null;
		$description = isset($_REQUEST['description']) ? mysql_real_escape_string($_REQUEST['description']) : '';
		$url = isset($_REQUEST['url']) ? mysql_real_escape_string($_REQUEST['url']) : null;

		switch($_REQUEST['action']) {
			case 'add':
				$query = "INSERT INTO {$db_table} (username, parent_id, description, url) VALUES ( ";
				$query .= "'$username', ";
				if ($parent_id === null) $query .= "NULL, "; else $query .= "$parent_id, ";
				$query .= "'$description', ";
				if ($url === null) $query .= "NULL)"; else $query .= "'$url')";
				mysql_query($query);
				message( "Added" );
				break;
			case 'edit':
				if(getRecord($id)) {
					$query = "UPDATE {$db_table} SET description = ";
					$query .= "'$description', url = ";
					if ($url === null) $query .= "NULL "; else $query .= "'$url' ";
					$query .= " WHERE username = '$username' AND id = $id";
					mysql_query($query);
					message( "Edited" );
				} else
					error( "Not found" );
				break;
			case 'delete':
				if(getRecord($id) && count(getRecordsByParentId($id)) == 0) {
					$query = "DELETE FROM {$db_table} WHERE username = '$username' AND id = $id ";
					mysql_query($query);
					message("Deleted");
				} else 
					error("Error deleting record"); 
				break;
		}
	} else {
		switch ($_REQUEST['action']) {
			case 'add':
				$parent_id = isset($_REQUEST['parent_id']) ? $_REQUEST['parent_id'] : null;
				showForm($parent_id, null);
				closePage();
			case 'edit':
				$bookmark = getRecord($_REQUEST['id']);
				if (is_array($bookmark))
					showForm($bookmark['parent_id'], $bookmark['id'], $bookmark['description'], $bookmark['url']);
				else
					error( "Not found" );
				closePage();
			case 'delete':
				$parents = getRecordsByParentId($_REQUEST['id']);
				if(($row = getRecord($_REQUEST['id'])) && count($parents) == 0) {
					print "<div>{$row['description']}</div>\n";
					?>
					<form action="<?php print($_SERVER['PHP_SELF']); ?>" method="post">
						<input type="hidden" name="action" value="<?php print($_REQUEST['action']) ?>" />
						<input type="hidden" name="id" value="<?php print($_REQUEST['id']) ?>" />
						<div>Are you sure you want to remove this bookmark?</div>
						<input type="submit" value="Yes" />
					</form>
					<?php
				} elseif(count($parents) > 0)
					error( "Child elements linked to item" );
				else
					error ( "Not found" );
				closePage();
			default:
		}
	}
}

function getRecord($id) {
	global $db_table, $username;
	$query = "SELECT * FROM {$db_table} WHERE username = '$username' AND id = " . (int) $id;
	$result = mysql_query($query);
	if ($result && mysql_num_rows($result) > 0)
		return mysql_fetch_assoc($result);
	else
		return null;
}

function getRecordsByParentId($parent_id) {
	global $db_table, $username;
	$query = "SELECT * FROM {$db_table} WHERE username = '$username' AND parent_id = " . (int) $parent_id;
	$r = mysql_query($query);
	$result = array();
	while ($r &&  $row =mysql_fetch_assoc($r)) {
		$result[] = $row;
	}
	mysql_free_result($r);
	return $result;
}

function showForm($parent_id, $id, $description = '', $url = '') {
	?>
	<form action="<?php print($_SERVER['PHP_SELF']); ?>" method="post" class="bookmarkForm">
	<input type="hidden" name="action" value="<?php print($_REQUEST['action']) ?>" />
	<?php if (!empty($parent_id)): ?><input type="hidden" name="parent_id" value="<?php print($parent_id) ?>" /><?php endif ?>
	<?php if (!empty($id)): ?><input type="hidden" name="id" value="<?php print($id) ?>" /><?php endif ?>
		<table>
			<tr>
				<td>
					Description:
				</td>
				<td>
					<input type="text" name="description"<?php if(!empty($description)):?> value="<?php print($description);?>"<?php endif ?> />
				</td>
			</tr>
			<tr>
				<td>
					URL (optional):
				</td>
				<td>
					<input type="text" name="url"<?php if(!empty($url)):?> value="<?php print($url);?>"<?php endif ?> />
				</td>
			</tr>
			<tr>
				<td colspan="2">
					<input type="submit" value="Submit" /> <a href="<?php print($_SERVER['PHP_SELF']) ?>">Back</a>
				</td>
			</tr>
		</table>
	</form>
	<?php
}

function message($message) {
	print "<span class=\"message\">$message</span>";
}

function error($message) {
	print "<span class=\"error\">$message</span>";
}

function getRows($parent_id = null)
{
	global $db_table, $username;

	static $recursion;

	if ($recursion === null)
		$recursion = 0;
	
	$indenting = str_repeat("\t", $recursion * 2);

	if ($parent_id == null) 
		$where = 'IS NULL';
	else
		$where = '= ' . (int) $parent_id;
	
	$query = "SELECT * FROM $db_table WHERE username = '$username' AND parent_id $where";

	$result = mysql_query($query);

	if (!$result || mysql_num_rows($result) == 0)
		return;
	
	print "$indenting<ul>\n";

	while($row = mysql_fetch_assoc($result))
	{
		print "$indenting\t<li>\n";
		if ($row['url'] == null) 
			print "$indenting\t\t<span>{$row['description']}</span>";
		else
			print "$indenting\t\t<a href=\"{$row['url']}\" rel=\"noreferrer\">{$row['description']}</a>";
		
		print "<div class=\"actionBox\">=><span><a href=\"{$_SERVER['PHP_SELF']}?action=add&parent_id={$row['id']}\">add</a>/<a href=\"{$_SERVER['PHP_SELF']}?action=edit&id={$row['id']}\">edit</a>/<a href=\"{$_SERVER['PHP_SELF']}?action=delete&id={$row['id']}\">remove</a></span></div> \n";

		// find subitems

		++$recursion;
		getRows($row['id']);
		--$recursion;

		print "$indenting\t</li>\n";
	}

	print "$indenting</ul>\n";
	return;

}

getRows();

print "<div class=\"actionBox\">=><span><a href=\"{$_SERVER['PHP_SELF']}?action=add\">add</a></span></div> \n";

closePage();

function closePage() {
	?>
<div>
<a href="<?php print($_SERVER['PHP_SELF']) ?>">Home</a>
</div>
</body>
</html>
	<?php
	exit;
}


