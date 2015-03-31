<?php
/*
	FusionPBX
	Version: MPL 1.1

	The contents of this file are subject to the Mozilla Public License Version
	1.1 (the "License"); you may not use this file except in compliance with
	the License. You may obtain a copy of the License at
	http://www.mozilla.org/MPL/

	Software distributed under the License is distributed on an "AS IS" basis,
	WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
	for the specific language governing rights and limitations under the
	License.

	The Original Code is FusionPBX

	The Initial Developer of the Original Code is
	Mark J Crane <markjcrane@fusionpbx.com>
	Portions created by the Initial Developer are Copyright (C) 2008-2014
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/
include "root.php";
require_once "resources/require.php";

//check permissions
	require_once "resources/check_auth.php";
	if (permission_exists('group_edit')) {
		//access allowed
	}
	else {
		echo "access denied";
		return;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//process update
	if (count($_POST) > 0) {
		//set the variables
			$group_uuid = check_str($_POST['group_uuid']);
			$group_name = check_str($_POST['group_name']);
			$domain_uuid = check_str($_POST["domain_uuid"]);
			$group_description = check_str($_POST["group_description"]);

		//check for global/domain duplicates
			$sql = "select count(*) as num_rows from v_groups where ";
			$sql .= "group_name = '".$group_name."' ";
			$sql .= "and group_uuid <> '".$group_uuid."' ";
			$sql .= "and domain_uuid ".(($domain_uuid != '') ? " = '".$domain_uuid."' " : " is null ");
			$prep_statement = $db->prepare($sql);
			if ($prep_statement) {
				$prep_statement->execute();
				$row = $prep_statement->fetch(PDO::FETCH_ASSOC);
				$group_exists = ($row['num_rows'] > 0) ? true : false;
			}
			else {
				$group_exists = false;
			}
			unset($sql, $prep_statement, $row);

		//insert group
			if (!$group_exists) {
				$sql = "update v_groups ";
				$sql .= "set ";
				$sql .= "group_name = '".$group_name."', ";
				$sql .= "domain_uuid = ".(($domain_uuid != '') ? "'".$domain_uuid."'" : "null").", ";
				$sql .= "group_description = '".$group_description."' ";
				$sql .= "where group_uuid = '".$group_uuid."' ";
				if (!$db->exec(check_sql($sql))) {
					//echo $db->errorCode() . "<br>";
					$info = $db->errorInfo();
					echo "<pre>".print_r($info, true)."</pre>";
					exit;
					// $info[0] == $db->errorCode() unified error code
					// $info[1] is the driver specific error code
					// $info[2] is the driver specific error string
				}

				$_SESSION["message"] = $text['message-update'];
				header("Location: groups.php");
			}
			else {
				$_SESSION['message_mood'] = 'negative';
				$_SESSION["message"] = $text['message-group_exists'];
				header("Location: groupedit.php?id=".$group_uuid);
			}

		//redirect the user
			return;
	}

//pre-populate the form
	$group_uuid = check_str($_REQUEST['id']);
	if ($group_uuid != '') {
		$sql = "select * from v_groups where ";
		$sql .= "group_uuid = '".$group_uuid."' ";
		$prep_statement = $db->prepare($sql);
		if ($prep_statement) {
			$prep_statement->execute();
			$row = $prep_statement->fetch(PDO::FETCH_ASSOC);
			$group_name = $row['group_name'];
			$domain_uuid = $row['domain_uuid'];
			$group_description = $row['group_description'];
		}
	}


//include the header
	include "resources/header.php";
	$document['title'] = $text['title-group_edit'];

//show the content
	echo "<form name='login' method='post' action=''>\n";
	echo "<input type='hidden' name='group_uuid' value='".$group_uuid."'>\n";

	echo "<table width='100%' cellpadding='0' cellspacing='0'>\n";
	echo "	<tr>\n";
	echo "		<td align='left' valign='top'>\n";
	echo "			<b>".$text['header-group_edit']."</b>\n";
	echo "			<br><br>\n";
	echo "			".$text['description-group_edit']."\n";
	echo "		</td>\n";
	echo "		<td align='right' valign='top'>\n";
	echo "			<input type='button' class='btn' name='' alt='back' onclick=\"window.location='groups.php'\" value='".$text['button-back']."'> ";
	echo "  		<input type='submit' class='btn' value=\"".$text['button-save']."\">\n";
	echo "		</td>\n";
	echo "	</tr>\n";
	echo "</table>\n";
	echo "<br>";

	echo "<table width='100%' cellpadding='0' cellspacing='0'>\n";
	echo "<tr>\n";
	echo "<td width='30%' class='vncellreq' valign='top'>\n";
	echo 	$text['label-group_name']."\n";
	echo "</td>\n";
	echo "<td width='70%' align='left' class='vtable'>\n";
	echo "  <input type='text' class='formfld' name='group_name' value=\"".$group_name."\">\n";
	echo "</td>\n";
	echo "</tr>\n";

	if (permission_exists('group_domain')) {
		echo "<tr>\n";
		echo "<td class='vncell' valign='top'>\n";
		echo "	".$text['label-domain']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "    <select class='formfld' name='domain_uuid'>\n";
		echo "    	<option value='' ".((strlen($domain_uuid) == 0) ? "selected='selected'" : null).">".$text['option-global']."</option>\n";
		foreach ($_SESSION['domains'] as $row) {
			echo "	<option value='".$row['domain_uuid']."' ".(($row['domain_uuid'] == $domain_uuid) ? "selected='selected'" : null).">".$row['domain_name']."</option>\n";
		}
		echo "    </select>\n";
		echo "<br />\n";
		echo $text['description-domain_name']."\n";
		echo "</td>\n";
		echo "</tr>\n";
	}
	else {
		echo "<input type='hidden' name='domain_uuid' value='".$domain_uuid."'>";
	}

	echo "<tr>\n";
	echo "<td class='vncell' valign='top'>\n";
	echo 	$text['label-group_description']."\n";
	echo "</td>\n";
	echo "<td align='left' class='vtable' valign='top'>\n";
	echo "	<textarea name='group_description' class='formfld' style='width: 250px; height: 50px;'>".$group_description."</textarea>\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td colspan='2' align='right'>\n";
	echo "	<br />";
	echo "	<input type='submit' class='btn' value=\"".$text['button-save']."\">\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "</table>\n";
	echo "<br><br>";
	echo "</form>";

//include the footer
	include "resources/footer.php";

?>