<?
authorize();

$UserID = $_REQUEST['userid'];
if(!is_number($UserID) || ($UserID != $LoggedUser['ID'] && !check_perms('users_edit_profiles'))) {
	send_irc("PRIVMSG ".ADMIN_CHAN." :User ".$LoggedUser['Username']." (http://".NONSSL_SITE_URL."/user.php?id=".$LoggedUser['ID'].") just tried to edit the profile of http://".NONSSL_SITE_URL."/user.php?id=".$_REQUEST['userid']);
	error(403);
}

//For the entire of this page we should in general be using $UserID not $LoggedUser['ID'] and $U[] not $LoggedUser[]	
$U = user_info($UserID);

if (!$U) {
	error(404);
}

$Val->SetFields('stylesheet',1,"number","You forgot to select a stylesheet.");
$Val->SetFields('styleurl',0,"regex","You did not enter a valid stylesheet url.",array('regex'=>'/^https?:\/\/(localhost(:[0-9]{2,5})?|[0-9]{1,3}(\.[0-9]{1,3}){3}|([a-zA-Z0-9\-\_]+\.)+([a-zA-Z]{1,5}[^\.]))(:[0-9]{2,5})?(\/[^<>]+)+\.css$/i'));
$Val->SetFields('paranoia',1,"number","You forgot to enter your paranoia level.",array('minlength'=>0,'maxlength'=>5));
$Val->SetFields('disablegrouping',1,"number","You forgot to select your torrent grouping option.",array('minlength'=>0,'maxlength'=>1));
$Val->SetFields('torrentgrouping',1,"number","You forgot to select your torrent grouping option.",array('minlength'=>0,'maxlength'=>1));
$Val->SetFields('discogview',1,"number","You forgot to select your discography view option.",array('minlength'=>0,'maxlength'=>1));
$Val->SetFields('postsperpage',1,"number","You forgot to select your posts per page option.",array('inarray'=>array(25,50,100)));
$Val->SetFields('hidecollage',1,"number","You forgot to select your collage option.",array('minlength'=>0,'maxlength'=>1));
$Val->SetFields('showtags',1,"number","You forgot to select your show tags option.",array('minlength'=>0,'maxlength'=>1));
$Val->SetFields('avatar',0,"regex","You did not enter a valid avatar url.",array('regex'=>"/^".IMAGE_REGEX."$/i"));
$Val->SetFields('email',1,"email","You did not enter a valid email address.");
$Val->SetFields('irckey',0,"string","You did not enter a valid IRCKey, must be between 6 and 32 characters long.",array('minlength'=>6,'maxlength'=>32));
$Val->SetFields('cur_pass',0,"string","You did not enter a valid password, must be between 6 and 40 characters long.",array('minlength'=>6,'maxlength'=>40));
$Val->SetFields('new_pass_1',0,"string","You did not enter a valid password, must be between 6 and 40 characters long.",array('minlength'=>6,'maxlength'=>40));
$Val->SetFields('new_pass_2',1,"compare","Your passwords do not match.",array('comparefield'=>'new_pass_1'));
if (check_perms('site_advanced_search')) {
	$Val->SetFields('searchtype',1,"number","You forgot to select your default search preference.",array('minlength'=>0,'maxlength'=>1));
}

$Err = $Val->ValidateForm($_POST);

if($Err) {
	error_message($Err);
	header('Location: user.php?action=edit&userid='.$UserID);
	die();
}

//Email change
$DB->query("SELECT Email FROM users_main WHERE ID=".$UserID);
list($CurEmail) = $DB->next_record();
if ($CurEmail != $_POST['email']) {
	if(!check_perms('users_edit_profiles')) { // Non-admins have to authenticate to change email
		$DB->query("SELECT PassHash,Secret FROM users_main WHERE ID='".db_string($UserID)."'");
		list($PassHash,$Secret)=$DB->next_record();
		if ($PassHash!=make_hash($_POST['cur_pass'],$Secret)) {
			$Err = "You did not enter the correct password.";
		}
	}
	if(!$Err) {
		$NewEmail = db_string($_POST['email']);		
		
		
		$Tmp = explode("@", $NewEmail);
		$Front = db_string($Tmp[0]);
		
		$DB->query("SELECT DISTINCT ID,Email FROM users_main AS u WHERE u.Email LIKE '".$Front."@%' AND ID != ".$UserID);
		$EmailUsers = $DB->to_array();
		$DB->query("SELECT DISTINCT UserID,Email FROM users_history_emails AS h WHERE h.Email LIKE '".$Front."@%' AND UserID != ".$UserID);
		$EmailUsers = array_merge($EmailUsers, $DB->to_array());
		if(count($EmailUsers) > 0) {
			$EmailUsers = $DB->to_array();
				
			foreach($EmailUsers as $EmailUser) {
				list($EmailUserID, $Email) = $EmailUser;
				$DB->query("SELECT Username FROM users_main WHERE ID = ".$EmailUserID);
				list($EmailUsername) = $DB->next_record();
				
				$DB->query("INSERT INTO reports_dupe_account
					(ID, Type, UserID, OldID, Time, Checked) VALUES
					('', 'ChangedEmail', ".$LoggedUser['ID'].", ".$EmailUserID.", '".sqltime()."', '0')");
				$ReportID = $DB->inserted_id();

				send_irc("PRIVMSG #reports :".$ReportID." - User http://".NONSSL_SITE_URL."/user.php?id=".$UserID." (".$U['Username'].") changed their email to ".$NewEmail." which is similar to ".$Email." used by user http://".NONSSL_SITE_URL."/user.php?id=".$EmailUserID." (".$EmailUsername.") at ".sqltime());
				$DB->query("UPDATE users_info SET AdminComment = CONCAT('".sqltime()." - Set email to ".$NewEmail." that is similar to ".$Email." used by http://".NONSSL_SITE_URL."/user.php?id=".$EmailUserID." (".$EmailUsername.") "."\n\n', AdminComment) WHERE UserID = ".$UserID);
				$DB->query("UPDATE users_info SET AdminComment = CONCAT('".sqltime()." - User http://".NONSSL_SITE_URL."/user.php?id=".$U['ID']." (".$U['Username'].") set their email to ".$NewEmail." which is similar to ".$Email." used by this user.\n\n', AdminComment) WHERE UserID = ".$EmailUserID);
			}
		}


		$DB->query("SELECT DISTINCT ID, 
				Username 
			FROM users_main AS u 
				WHERE (LENGTH(u.Username) >= 4 AND (u.Username LIKE '%".$Front."' 
					OR u.Username LIKE '".$Front."%'))
				AND u.ID != ".$UserID." 
				AND u.Enabled='2'");
		$UsernameUsers = $DB->to_array();
		if(count($UsernameUsers) > 0) {
			$UsernameUsers = $DB->to_array();
			
			foreach($UsernameUsers as $UsernameUser) {
				list($UsernameUserID, $Username) = $UsernameUser;
				
				if(count($UsernameUsers)<10) {
					$DB->query("INSERT INTO reports_dupe_account
						(ID, Type, UserID, OldID, Time, Checked) VALUES
						('', 'ChangedEmail', ".$UserID.", ".$UsernameUserID.", '".sqltime()."', '0')");
					$ReportID = $DB->inserted_id();

					send_irc("PRIVMSG #reports :".$ReportID." - User http://".NONSSL_SITE_URL."/user.php?id=".$UserID." (".$U['Username'].") changed to email ".$NewEmail." similar to the username of http://".NONSSL_SITE_URL."/user.php?id=".$UsernameUserID." (".$Username.") at ".sqltime());
				}
				
				$DB->query("UPDATE users_info SET AdminComment = CONCAT('".sqltime()." - Changed to email ".$NewEmail." that is similar to the username of user http://".NONSSL_SITE_URL."/user.php?id=".$UsernameUserID." / (".$Username.") "."\n\n', AdminComment) WHERE UserID = ".$UserID);
				$DB->query("UPDATE users_info SET AdminComment = CONCAT('".sqltime()." - User http://".NONSSL_SITE_URL."/user.php?id=".$UserID." (".$U['Username'].") changed to the email address ".$NewEmail." that is similar to this user\'s username.\n\n', AdminComment) WHERE UserID = ".$UsernameUserID);
			}
		}



		$DB->query("SELECT um.Username, UNIX_TIMESTAMP(uw.PasswordTime) FROM users_watch AS uw JOIN users_main AS um ON um.ID=uw.UserID WHERE uw.UserID = ".$UserID);
		if($DB->record_count() > 0) {
			list($Username, $PasswordChangeTime) = $DB->next_record();
			if(abs(time() - $PasswordChangeTime) < 1*60*60) {
				$AdminComment = date("Y-m-d")." - ".$Username." http://".NONSSL_SITE_URL."/user.php?id=".$UserID." just changed their password and email address within an hour of each other.\n";
				$DB->query("INSERT INTO reports_dupe_account
					(ID, Type, UserID, OldID, Time, Checked) VALUES
					('', 'EmailPasswordChange', '".$UserID."', '".$UserID."', '".sqltime()."', '0')");
				$ReportID = $DB->inserted_id();
				send_irc('PRIVMSG #reports :'.$ReportID.' - '.$AdminComment);

				$DB->query("UPDATE users_info SET AdminComment = CONCAT('".db_string($AdminComment)."', AdminComment) WHERE UserID=".$UserID);
			} else {
				$DB->query("UPDATE users_watch SET EmailTime = '".sqltime()."' WHERE UserID = ".$UserID);
			}
		} else {
			$DB->query("INSERT INTO users_watch (UserID, EmailTime) VALUES (".$UserID.", '".sqltime()."')");
		}

		//</strip>
	
		//This piece of code will update the time of their last email change to the current time *not* the current change.
		$ChangerIP = db_string($LoggedUser['IP']);
		$DB->query("UPDATE users_history_emails SET Time='".sqltime()."' WHERE UserID='$UserID' AND Time='0000-00-00 00:00:00'");
		$DB->query("INSERT INTO users_history_emails
				(UserID, Email, Time, IP) VALUES
				('$UserID', '$NewEmail', '0000-00-00 00:00:00', '".db_string($_SERVER['REMOTE_ADDR'])."')");
		
	} else {
		error_message($Err);
		header('Location: user.php?action=edit&userid='.$UserID);
		die();
	}
	
	
}
//End Email change

if (!$Err && ($_POST['cur_pass'] || $_POST['new_pass_1'] || $_POST['new_pass_2'])) {
	$DB->query("SELECT PassHash,Secret FROM users_main WHERE ID='".db_string($UserID)."'");
	list($PassHash,$Secret)=$DB->next_record();

	if ($PassHash == make_hash($_POST['cur_pass'],$Secret)) {
		if ($_POST['new_pass_1'] && $_POST['new_pass_2']) { 
			$ResetPassword = true; 
		}
	} else { 
		$Err = "You did not enter the correct password.";
	}
}

if($LoggedUser['DisableAvatar'] && $_POST['avatar'] != $U['Avatar']) {
	$Err = "Your avatar rights have been removed.";
}

if ($Err) {
	error_message($Err);
	header('Location: user.php?action=edit&userid='.$UserID);
	die();
}

$Options['DisableGrouping'] = (!empty($_POST['disablegrouping']) ? 1 : 0);
$Options['TorrentGrouping'] = (!empty($_POST['torrentgrouping']) ? 1 : 0);
$Options['DiscogView'] = (!empty($_POST['discogview']) ? 1 : 0);
$Options['PostsPerPage'] = (int) $_POST['postsperpage'];
$Options['HideCollage'] = (!empty($_POST['hidecollage']) ? 1 : 0);
$Options['ShowTags'] = (!empty($_POST['showtags']) ? 1 : 0);
$Options['AutoSubscribe'] = (!empty($_POST['autosubscribe']) ? 1 : 0);
$Options['DisableSmileys'] = (!empty($_POST['disablesmileys']) ? 1 : 0);
$Options['DisableAvatars'] = (!empty($_POST['disableavatars']) ? 1 : 0);

if(!empty($_POST['hidetypes'])) { 
	foreach($_POST['hidetypes'] as $Type) {
		$Options['HideTypes'][] = (int) $Type;
	}
} else {
	$Options['HideTypes']=array();
}
if (check_perms('site_advanced_search')) {
	$Options['SearchType']  =$_POST['searchtype'];
} else {
	unset($Options['SearchType']);
}

//TODO: Remove the following after a significant amount of time
unset($Options['ArtistNoRedirect']);
unset($Options['ShowQueryList']);
unset($Options['ShowCacheList']);

$DownloadAlt = (isset($_POST['downloadalt']))? 1:0;

// Information on how the user likes to download torrents is stored in cache
if($DownloadAlt != $LoggedUser['DownloadAlt']) {
	$Cache->delete_value('user_'.$LoggedUser['torrent_pass']);
}

$Cache->begin_transaction('user_info_'.$UserID);
$Cache->update_row(false, array(
		'Avatar'=>$_POST['avatar']

));
$Cache->commit_transaction(0);

$Cache->begin_transaction('user_info_heavy_'.$UserID);
$Cache->update_row(false, array(
		'StyleID'=>$_POST['stylesheet'],
		'StyleURL'=>$_POST['styleurl'],
		'DownloadAlt'=>$DownloadAlt,
		'Paranoia'=>$_POST['paranoia']
		));
$Cache->update_row(false, $Options);
$Cache->commit_transaction(0);



$SQL="UPDATE users_main AS m JOIN users_info AS i ON m.ID=i.UserID SET
	i.StyleID='".db_string($_POST['stylesheet'])."',
	i.StyleURL='".db_string($_POST['styleurl'])."',
	i.Avatar='".db_string($_POST['avatar'])."',
	i.SiteOptions='".db_string(serialize($Options))."',
	i.Info='".db_string($_POST['info'])."',
	i.DownloadAlt='$DownloadAlt',
	m.Email='".db_string($_POST['email'])."',
	m.IRCKey='".db_string($_POST['irckey'])."',";

$SQL .=	"m.Paranoia='".db_string($_POST['paranoia'])."'";

if ($ResetPassword) {
	$Secret=make_secret();
	$PassHash=make_hash($_POST['new_pass_1'],$Secret);
	$SQL.=",m.Secret='".db_string($Secret)."',m.PassHash='".db_string($PassHash)."'";
	$DB->query("INSERT INTO users_history_passwords
		(UserID, ChangerIP, ChangeTime) VALUES
		('$UserID', '$ChangerIP', '".sqltime()."')");

	
}

if (isset($_POST['resetpasskey'])) {
	
	
	
	$OldPassKey = db_string($LoggedUser['torrent_pass']);
	$NewPassKey = db_string(make_secret());
	$ChangerIP = db_string($LoggedUser['IP']);
	$SQL.=",m.torrent_pass='$NewPassKey'";
	$DB->query("INSERT INTO users_history_passkeys
			(UserID, OldPassKey, NewPassKey, ChangerIP, ChangeTime) VALUES
			('$UserID', '$OldPassKey', '$NewPassKey', '$ChangerIP', '".sqltime()."')");
	$Cache->begin_transaction('user_info_heavy_'.$UserID);
	$Cache->update_row(false, array('torrent_pass'=>$NewPassKey));
	$Cache->commit_transaction(0);
	
	
}

$SQL.="WHERE m.ID='".db_string($UserID)."'";
$DB->query($SQL);

save_message("Your profile has been saved.");
header('Location: user.php?action=edit&userid='.$UserID);

?>
