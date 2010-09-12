<?
authorize();

if(!check_perms('admin_reports') && !check_perms('project_team')) {
	error(403);
}

if(empty($_POST['reportid']) && !is_number($_POST['reportid'])) {
	error(403);
}

$ReportID = $_POST['reportid'];

if(!check_perms('admin_reports')) {
	$DB->query("SELECT Type FROM reports WHERE ID = ".$ReportID);
	list($Type) = $DB->next_record();
	if($Type != "request_update") {
		error(403);
	}
}

$DB->query("UPDATE reports 
			SET Status='Resolved',
				ResolvedTime='".sqltime()."',
				ResolverID='".$LoggedUser['ID']."'
			WHERE ID='".db_string($ReportID)."'");

$Channel = (($Type == "request_update") ? "#requestedits" : ADMIN_CHAN);
$DB->query("SELECT COUNT(ID) FROM reports WHERE Status = 'New'");
list($Remaining) = $DB->next_record();
send_irc("PRIVMSG ".$Channel." :Report ".$ReportID." resolved by ".preg_replace("/^(.{2})/", "$1·", $LoggedUser['Username'])." on site (".$Remaining." remaining).");

$Cache->delete_value('num_other_reports');

header('Location: reports.php');
?>
