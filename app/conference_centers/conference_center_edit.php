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
	Portions created by the Initial Developer are Copyright (C) 2008-2016
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

//includes
	require_once "root.php";
	require_once "resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (permission_exists('conference_center_add') || permission_exists('conference_center_edit')) {
		//access granted
	}
	else {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//action add or update
	if (isset($_REQUEST["id"])) {
		$action = "update";
		$conference_center_uuid = check_str($_REQUEST["id"]);
	}
	else {
		$action = "add";
	}

//get http post variables and set them to php variables
	if (count($_POST) > 0) {
		$dialplan_uuid = check_str($_POST["dialplan_uuid"]);
		$conference_center_name = check_str($_POST["conference_center_name"]);
		$conference_center_extension = check_str($_POST["conference_center_extension"]);
		$conference_center_greeting = check_str($_POST["conference_center_greeting"]);
		$conference_center_pin_length = check_str($_POST["conference_center_pin_length"]);
		$conference_center_description = check_str($_POST["conference_center_description"]);
		$conference_center_enabled = check_str($_POST["conference_center_enabled"]);

		//sanitize the conference name
		$conference_center_name = preg_replace("/[^A-Za-z0-9\- ]/", "", $conference_center_name);
		$conference_center_name = str_replace(" ", "-", $conference_center_name);
	}

//process user data
	if (count($_POST)>0 && strlen($_POST["persistformvar"]) == 0) {

		$msg = '';
		if ($action == "update") {
			$conference_center_uuid = check_str($_POST["conference_center_uuid"]);
		}

		//check for all required data
			//if (strlen($dialplan_uuid) == 0) { $msg .= "Please provide: Dialplan UUID<br>\n"; }
			if (strlen($conference_center_name) == 0) { $msg .= "Please provide: Name<br>\n"; }
			if (strlen($conference_center_extension) == 0) { $msg .= "Please provide: Extension<br>\n"; }
			if (strlen($conference_center_pin_length) == 0) { $msg .= "Please provide: PIN Length<br>\n"; }
			//if (strlen($conference_center_order) == 0) { $msg .= "Please provide: Order<br>\n"; }
			//if (strlen($conference_center_description) == 0) { $msg .= "Please provide: Description<br>\n"; }
			if (strlen($conference_center_enabled) == 0) { $msg .= "Please provide: Enabled<br>\n"; }
			if (strlen($msg) > 0 && strlen($_POST["persistformvar"]) == 0) {
				require_once "resources/header.php";
				require_once "resources/persist_form_var.php";
				echo "<div align='center'>\n";
				echo "<table><tr><td>\n";
				echo $msg."<br />";
				echo "</td></tr></table>\n";
				persistformvar($_POST);
				echo "</div>\n";
				require_once "resources/footer.php";
				return;
			}

		//add or update the database
			if ($_POST["persistformvar"] != "true") {
				if ($action == "add") {
					//prepare the uuids
						$conference_center_uuid = uuid();
						$dialplan_uuid = uuid();
					//add the conference
						$sql = "insert into v_conference_centers ";
						$sql .= "(";
						$sql .= "domain_uuid, ";
						$sql .= "conference_center_uuid, ";
						$sql .= "dialplan_uuid, ";
						$sql .= "conference_center_name, ";
						$sql .= "conference_center_extension, ";
						$sql .= "conference_center_pin_length, ";
						$sql .= "conference_center_greeting, ";
						$sql .= "conference_center_description, ";
						$sql .= "conference_center_enabled ";
						$sql .= ")";
						$sql .= "values ";
						$sql .= "(";
						$sql .= "'$domain_uuid', ";
						$sql .= "'$conference_center_uuid', ";
						$sql .= "'$dialplan_uuid', ";
						$sql .= "'$conference_center_name', ";
						$sql .= "'$conference_center_extension', ";
						$sql .= "'$conference_center_pin_length', ";
						$sql .= "'$conference_center_greeting', ";
						$sql .= "'$conference_center_description', ";
						$sql .= "'$conference_center_enabled' ";
						$sql .= ")";
						$db->exec(check_sql($sql));
						unset($sql);

					//create the dialplan entry
						$dialplan_name = $conference_center_name;
						$dialplan_order ='333';
						$dialplan_context = $_SESSION['context'];
						$dialplan_enabled = 'true';
						$dialplan_description = $conference_center_description;
						$app_uuid = 'b81412e8-7253-91f4-e48e-42fc2c9a38d9';
						dialplan_add($_SESSION['domain_uuid'], $dialplan_uuid, $dialplan_name, $dialplan_order, $dialplan_context, $dialplan_enabled, $dialplan_description, $app_uuid);

						//<condition destination_number="500" />
						$dialplan_detail_tag = 'condition'; //condition, action, antiaction
						$dialplan_detail_type = 'destination_number';
						$dialplan_detail_data = '^'.$conference_center_extension.'$';
						$dialplan_detail_order = '010';
						$dialplan_detail_group = '2';
						dialplan_detail_add($_SESSION['domain_uuid'], $dialplan_uuid, $dialplan_detail_tag, $dialplan_detail_order, $dialplan_detail_group, $dialplan_detail_type, $dialplan_detail_data);

						//<action application="lua" />
						$dialplan_detail_tag = 'action'; //condition, action, antiaction
						$dialplan_detail_type = 'lua';
						$dialplan_detail_data = 'app.lua conference_center';
						$dialplan_detail_order = '020';
						$dialplan_detail_group = '2';
						dialplan_detail_add($_SESSION['domain_uuid'], $dialplan_uuid, $dialplan_detail_tag, $dialplan_detail_order, $dialplan_detail_group, $dialplan_detail_type, $dialplan_detail_data);

					//save the xml
						save_dialplan_xml();

					$_SESSION["message"] = $text['message-add'];
					header("Location: conference_centers.php");
					return;
				} //if ($action == "add")

				if ($action == "update") {
					//update the conference center extension
						$sql = "update v_conference_centers set ";
						$sql .= "conference_center_name = '$conference_center_name', ";
						$sql .= "conference_center_extension = '$conference_center_extension', ";
						$sql .= "conference_center_pin_length = '$conference_center_pin_length', ";
						$sql .= "conference_center_greeting = '$conference_center_greeting', ";
						$sql .= "conference_center_description = '$conference_center_description', ";
						$sql .= "conference_center_enabled = '$conference_center_enabled' ";
						$sql .= "where domain_uuid = '$domain_uuid' ";
						$sql .= "and conference_center_uuid = '$conference_center_uuid'";
						$db->exec(check_sql($sql));
						unset($sql);

					//udpate the conference center dialplan
						$sql = "update v_dialplans set ";
						$sql .= "dialplan_name = '$conference_center_name', ";
						if (strlen($dialplan_order) > 0) {
							$sql .= "dialplan_order = '333', ";
						}
						$sql .= "dialplan_context = '".$_SESSION['context']."', ";
						$sql .= "dialplan_enabled = 'true', ";
						$sql .= "dialplan_description = '$conference_center_description' ";
						$sql .= "where domain_uuid = '".$_SESSION['domain_uuid']."' ";
						$sql .= "and dialplan_uuid = '$dialplan_uuid' ";
						$db->query($sql);
						unset($sql);

					//update dialplan detail condition
						$sql = "update v_dialplan_details set ";
						$sql .= "dialplan_detail_data = '^".$conference_center_extension."$' ";
						$sql .= "where domain_uuid = '".$_SESSION['domain_uuid']."' ";
						$sql .= "and dialplan_detail_tag = 'condition' ";
						$sql .= "and dialplan_detail_type = 'destination_number' ";
						$sql .= "and dialplan_uuid = '$dialplan_uuid' ";
						$db->query($sql);
						unset($sql);

					//update dialplan detail action
						$dialplan_detail_type = 'lua';
						$dialplan_detail_data = 'app.lua conference_center';
						$sql = "update v_dialplan_details set ";
						$sql .= "dialplan_detail_type = '".$dialplan_detail_type."', ";
						$sql .= "dialplan_detail_data = '".$dialplan_detail_data."' ";
						$sql .= "where domain_uuid = '".$_SESSION['domain_uuid']."' ";
						$sql .= "and dialplan_detail_tag = 'action' ";
						$sql .= "and dialplan_detail_type = 'lua' ";
						$sql .= "and dialplan_uuid = '$dialplan_uuid' ";
						$db->query($sql);

					//syncrhonize configuration
						save_dialplan_xml();

					//apply settings reminder
						$_SESSION["reload_xml"] = true;

					//clear the cache
						$cache = new cache;
						$cache->delete("dialplan:".$_SESSION["context"]);

					//redirect the browser
						$_SESSION["message"] = $text['message-update'];
						header("Location: conference_centers.php");
						return;
				} //if ($action == "update")
			} //if ($_POST["persistformvar"] != "true")
	} //(count($_POST)>0 && strlen($_POST["persistformvar"]) == 0)

//function to show the list of sound files
	// moved to functions.php

//pre-populate the form
	if (count($_GET)>0 && $_POST["persistformvar"] != "true") {
		$conference_center_uuid = $_GET["id"];
		$sql = "select * from v_conference_centers ";
		$sql .= "where domain_uuid = '$domain_uuid' ";
		$sql .= "and conference_center_uuid = '$conference_center_uuid' ";
		$prep_statement = $db->prepare(check_sql($sql));
		$prep_statement->execute();
		$result = $prep_statement->fetchAll();
		foreach ($result as &$row) {
			$dialplan_uuid = $row["dialplan_uuid"];
			$conference_center_name = $row["conference_center_name"];
			$conference_center_greeting = $row["conference_center_greeting"];
			$conference_center_extension = $row["conference_center_extension"];
			$conference_center_pin_length = $row["conference_center_pin_length"];
			$conference_center_order = $row["conference_center_order"];
			$conference_center_description = $row["conference_center_description"];
			$conference_center_enabled = $row["conference_center_enabled"];
			$conference_center_name = str_replace("-", " ", $conference_center_name);
		}
		unset ($prep_statement);
	}

//set defaults
	if (strlen($conference_center_enabled) == 0) { $conference_center_enabled = "true"; }
	if (strlen($conference_center_pin_length) == 0) { $conference_center_pin_length = 9; }

//get the recordings
	$sql = "select recording_name, recording_filename from v_recordings ";
	$sql .= "where domain_uuid = '".$_SESSION["domain_uuid"]."' ";
	$sql .= "order by recording_name asc ";
	$prep_statement = $db->prepare(check_sql($sql));
	$prep_statement->execute();
	$recordings = $prep_statement->fetchAll(PDO::FETCH_ASSOC);

//get the phrases
	$sql = "select * from v_phrases ";
	$sql .= "where (domain_uuid = '".$domain_uuid."' or domain_uuid is null) ";
	$prep_statement = $db->prepare(check_sql($sql));
	$prep_statement->execute();
	$phrases = $prep_statement->fetchAll(PDO::FETCH_NAMED);

//show the header
	require_once "resources/header.php";

//show the content
	echo "<form method='post' name='frm' action=''>\n";
	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
	echo "<tr>\n";
	echo "<td align='left' width='30%' nowrap='nowrap' valign='top'><b>".$text['title-conference_center']."</b></td>\n";
	echo "<td width='70%' align='right' valign='top'>\n";
	echo "	<input type='button' class='btn' name='' alt='".$text['button-back']."' onclick=\"window.location='conference_centers.php'\" value='".$text['button-back']."'>\n";
	if (permission_exists('conference_active_advanced_view')) {
		echo "	<input type='button' class='btn' name='' alt='".$text['button-view']."' onclick=\"window.location='".PROJECT_PATH."/app/conferences_active/conferences_active.php'\" value='".$text['button-view']."'>\n";
	}
	echo "	<input type='submit' name='submit' class='btn' value='".$text['button-save']."'>\n";
	echo "</td>\n";
	echo "</tr>\n";
	echo "</table>\n";
	echo "<br />";
	echo $text['description-conference_center']."\n";
	echo "<br /><br />\n";

	echo "<table width='100%'  border='0' cellpadding='0' cellspacing='0'>\n";
	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-name']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='conference_center_name' maxlength='255' value=\"$conference_center_name\">\n";
	echo "	<br />\n";
	echo "	".$text['description-name']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-extension']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='conference_center_extension' maxlength='255' value=\"$conference_center_extension\">\n";
	echo "	<br />\n";
	echo " ".$text['description-extension']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-greeting']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	if (permission_exists('conference_center_add') || permission_exists('conference_center_edit')) {
		echo "<script>\n";
		echo "var Objs;\n";
		echo "\n";
		echo "function changeToInput(obj){\n";
		echo "	tb=document.createElement('INPUT');\n";
		echo "	tb.type='text';\n";
		echo "	tb.name=obj.name;\n";
		echo "	tb.setAttribute('class', 'formfld');\n";
		echo "	tb.setAttribute('style', 'width: 350px;');\n";
		echo "	tb.value=obj.options[obj.selectedIndex].value;\n";
		echo "	tbb=document.createElement('INPUT');\n";
		echo "	tbb.setAttribute('class', 'btn');\n";
		echo "	tbb.setAttribute('style', 'margin-left: 4px;');\n";
		echo "	tbb.type='button';\n";
		echo "	tbb.value=$('<div />').html('&#9665;').text();\n";
		echo "	tbb.objs=[obj,tb,tbb];\n";
		echo "	tbb.onclick=function(){ Replace(this.objs); }\n";
		echo "	obj.parentNode.insertBefore(tb,obj);\n";
		echo "	obj.parentNode.insertBefore(tbb,obj);\n";
		echo "	obj.parentNode.removeChild(obj);\n";
		echo "}\n";
		echo "\n";
		echo "function Replace(obj){\n";
		echo "	obj[2].parentNode.insertBefore(obj[0],obj[2]);\n";
		echo "	obj[0].parentNode.removeChild(obj[1]);\n";
		echo "	obj[0].parentNode.removeChild(obj[2]);\n";
		echo "}\n";
		echo "</script>\n";
		echo "\n";
	}

	echo "	<select name='conference_center_greeting' class='formfld' ".((permission_exists('conference_center_add') || permission_exists('conference_center_edit')) ? "onchange='changeToInput(this);'" : null).">\n";
	echo "		<option></option>\n";

	//recordings
		$tmp_selected = false;
		if (is_array($recordings)) {
			echo "<optgroup label='Recordings'>\n";
			foreach ($recordings as &$row) {
				$recording_name = $row["recording_name"];
				$recording_filename = $row["recording_filename"];
				$recording_path = $_SESSION['switch']['recordings']['dir']."/".$_SESSION['domain_name'];
				$selected = '';
				if ($conference_center_greeting == $recording_path."/".$recording_filename) {
					$selected = "selected='selected'";
				}
				echo "	<option value='".$recording_path."/".$recording_filename."' ".$selected.">".$recording_name."</option>\n";
				unset($selected);
			}
			echo "</optgroup>\n";
		}
	//phrases
		if (count($phrases) > 0) {
			echo "<optgroup label='Phrases'>\n";
			foreach ($phrases as &$row) {
				$selected = ($conference_center_greeting == "phrase:".$row["phrase_uuid"]) ? true : false;
				echo "	<option value='phrase:".$row["phrase_uuid"]."' ".(($selected) ? "selected='selected'" : null).">".$row["phrase_name"]."</option>\n";
				if ($selected) { $tmp_selected = true; }
			}
			unset ($prep_statement);
			echo "</optgroup>\n";
		}
	//sounds
		$files = recur_sounds_dir($_SESSION['switch']['sounds']['dir']);
		if (count($files) > 0) {
			echo "<optgroup label='Sounds'>\n";
			foreach ($files as $key => $value) {
				if (strlen($value) > 0) {
					if (substr($conference_center_greeting, 0, 71) == "\$\${sounds_dir}/\${default_language}/\${default_dialect}/\${default_voice}/") {
						$conference_center_greeting = substr($conference_center_greeting, 71);
					}
					$selected = ($conference_center_greeting == $key) ? true : false;
					echo "	<option value='".$key."' ".(($selected) ? "selected='selected'" : null).">".$key."</option>\n";
					if ($selected) { $tmp_selected = true; }
				}
			}
			echo "</optgroup>\n";
		}
	//select
		if (strlen($conference_center_greeting) > 0) {
			if (permission_exists('conference_center_add') || permission_exists('conference_center_edit')) {
				if (!$tmp_selected) {
					echo "<optgroup label='selected'>\n";
					if (file_exists($_SESSION['switch']['recordings']['dir']."/".$_SESSION['domain_name']."/".$conference_center_greeting)) {
						echo "		<option value='".$_SESSION['switch']['recordings']['dir']."/".$_SESSION['domain_name']."/".$conference_center_greeting."' selected='selected'>".$ivr_menu_greet_long."</option>\n";
					}
					else if (substr($conference_center_greeting, -3) == "wav" || substr($conference_center_greeting, -3) == "mp3") {
						echo "		<option value='".$conference_center_greeting."' selected='selected'>".$conference_center_greeting."</option>\n";
					}
					else {
						echo "		<option value='".$conference_center_greeting."' selected='selected'>".$conference_center_greeting."</option>\n";
					}
					echo "</optgroup>\n";
				}
				unset($tmp_selected);
			}
		}
	echo "	</select>\n";
	echo "<br />\n";
	echo $text['description-greeting']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-pin-length']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='conference_center_pin_length' maxlength='255' value=\"$conference_center_pin_length\">\n";
	echo "	<br />\n";
	echo "	".$text['description-pin-length']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-enabled']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<select class='formfld' name='conference_center_enabled'>\n";
	if ($conference_center_enabled == "true") {
		echo "	<option value='true' selected='selected'>".$text['label-true']."</option>\n";
	}
	else {
		echo "	<option value='true'>".$text['label-true']."</option>\n";
	}
	if ($conference_center_enabled == "false") {
		echo "	<option value='false' selected='selected'>".$text['label-false']."</option>\n";
	}
	else {
		echo "	<option value='false'>".$text['label-false']."</option>\n";
	}
	echo "	</select>\n";
	echo "	<br />\n";
	echo "	".$text['description-enabled']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-description']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='conference_center_description' maxlength='255' value=\"$conference_center_description\">\n";
	echo "	<br />\n";
	echo "	".$text['description-description']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "	<tr>\n";
	echo "		<td colspan='2' align='right'>\n";
	if ($action == "update") {
		echo "		<input type='hidden' name='dialplan_uuid' value=\"$dialplan_uuid\">\n";
		echo "		<input type='hidden' name='conference_center_uuid' value='$conference_center_uuid'>\n";
	}
	echo "			<br>";
	echo "			<input type='submit' name='submit' class='btn' value='".$text['button-save']."'>\n";
	echo "		</td>\n";
	echo "	</tr>";
	echo "</table>";
	echo "<br><br>";
	echo "</form>";

//include the footer
	require_once "resources/footer.php";
?>
