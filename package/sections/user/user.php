<?

include(SERVER_ROOT.'/classes/class_text.php'); // Text formatting class
$Text = new TEXT;

include(SERVER_ROOT.'/sections/requests/functions.php');

if (empty($_GET['id']) || !is_numeric($_GET['id'])) { error(0); }
$UserID = $_GET['id'];



if($UserID == $LoggedUser['ID']) { 
	$OwnProfile = true;
} else { 
	$OwnProfile = false;
}

if(check_perms('users_mod')) { // Person viewing is a staff member
	$DB->query("SELECT
		m.Username,
		m.Email,
		m.LastAccess,
		m.IP,
		p.Level AS Class,
		m.Uploaded,
		m.Downloaded,
		m.RequiredRatio,
		m.Title,
		m.torrent_pass,
		m.Enabled,
		m.Paranoia,
		m.Invites,
		m.can_leech,
		m.Visible,
		i.JoinDate,
		i.Info,
		i.Avatar,
		i.Country,
		i.AdminComment,
		i.Donor,
		i.Artist,
		i.Warned,
		i.SupportFor,
		i.Inviter,
		inviter.Username,
		COUNT(posts.id) AS ForumPosts,
		i.RatioWatchEnds,
		i.RatioWatchDownload,
		i.DisableAvatar,
		i.DisableInvites,
		i.DisablePosting,
		i.DisableForums,
		i.DisableTagging,
		i.DisableUpload,
		i.DisableWiki,
		i.DisablePM,
		i.DisableIRC
		FROM users_main AS m
		JOIN users_info AS i ON i.UserID = m.ID
		LEFT JOIN users_main AS inviter ON i.Inviter = inviter.ID
		LEFT JOIN permissions AS p ON p.ID=m.PermissionID
		LEFT JOIN forums_posts AS posts ON posts.AuthorID = m.ID
		WHERE m.ID = '".$UserID."' GROUP BY AuthorID");

	if ($DB->record_count() == 0) { // If user doesn't exist
		header("Location: log.php?search=User+".$UserID);
	}

	list($Username,	$Email,	$LastAccess, $IP, $Class, $Uploaded, $Downloaded, $RequiredRatio, $CustomTitle, $torrent_pass, $Enabled, $Paranoia, $Invites, $DisableLeech, $Visible, $JoinDate, $Info, $Avatar, $Country, $AdminComment, $Donor, $Artist, $Warned, $SupportFor, $InviterID, $InviterName, $ForumPosts, $RatioWatchEnds, $RatioWatchDownload, $DisableAvatar, $DisableInvites, $DisablePosting, $DisableForums, $DisableTagging, $DisableUpload, $DisableWiki, $DisablePM, $DisableIRC) = $DB->next_record(MYSQLI_NUM, array(8));
} else { // Person viewing is a normal user
	$DB->query("SELECT
		m.Username,
		m.Email,
		m.LastAccess,
		m.IP,
		p.Level AS Class,
		m.Uploaded,
		m.Downloaded,
		m.RequiredRatio,
		m.Enabled,
		m.Paranoia,
		m.Invites,
		m.Title,
		m.torrent_pass,
		i.JoinDate,
		i.Info,
		i.Avatar,
		i.Country,
		i.Donor,
		i.Warned,
		COUNT(posts.id) AS ForumPosts,
		i.Inviter,
		i.DisableInvites,
		inviter.username
		FROM users_main AS m
		JOIN users_info AS i ON i.UserID = m.ID
		LEFT JOIN permissions AS p ON p.ID=m.PermissionID
		LEFT JOIN users_main AS inviter ON i.Inviter = inviter.ID
		LEFT JOIN forums_posts AS posts ON posts.AuthorID = m.ID
		WHERE m.ID = $UserID GROUP BY AuthorID");

	if ($DB->record_count() == 0) { // If user doesn't exist
		header("Location: log.php?search=User+".$UserID);
	}

	list($Username, $Email, $LastAccess, $IP, $Class, $Uploaded, $Downloaded, $RequiredRatio, $Enabled, $Paranoia, $Invites, $CustomTitle, $torrent_pass, $JoinDate, $Info, $Avatar, $Country, $Donor, $Warned, $ForumPosts, $InviterID, $DisableInvites, $InviterName, $RatioWatchEnds, $RatioWatchDownload) = $DB->next_record(MYSQLI_NUM, array(7));
}

$JoinedDate = time_diff($JoinDate);
$LastAccess = time_diff($LastAccess);

$Badges=($Donor) ? '<a href="donate.php"><img src="'.STATIC_SERVER.'common/symbols/donor.png" alt="Donor" /></a>' : '';
$Badges.=($Warned!='0000-00-00 00:00:00') ? '<img src="'.STATIC_SERVER.'common/symbols/warned.png" alt="Warned" />' : '';
$Badges.=($Enabled == '1' || $Enabled == '0' || !$Enabled) ? '': '<img src="'.STATIC_SERVER.'common/symbols/disabled.png" alt="Banned" />';

show_header($Username,'user');
show_message();
?>
<div class="thin">
	<h2><?=$Username?></h2>
	<div class="linkbox">
<? if (!$OwnProfile) { ?>
		[<a href="inbox.php?action=compose&amp;to=<?=$UserID?>">Send Message</a>]
<? 	$DB->query("SELECT FriendID FROM friends WHERE UserID='$LoggedUser[ID]' AND FriendID='$UserID'");
	if($DB->record_count() == 0) { ?>
		[<a href="friends.php?action=add&amp;friendid=<?=$UserID?>&amp;auth=<?=$LoggedUser['AuthKey']?>">Add to friends</a>]
<?	}?>
		[<a href="reports.php?action=report&amp;type=user&amp;id=<?=$UserID?>">Report User</a>]
<?
}

if (check_perms('users_edit_profiles', $Class)) {
?>
		[<a href="user.php?action=edit&amp;userid=<?=$UserID?>">Settings</a>]
<? }
if (check_perms('users_view_invites', $Class)) {
?>
		[<a href="user.php?action=invite&amp;userid=<?=$UserID?>">Invites</a>]
<? }
if (check_perms('admin_manage_permissions', $Class)) {
?>
		[<a href="user.php?action=permissions&amp;userid=<?=$UserID?>">Permissions</a>]
<? }
if (check_perms('users_logout', $Class) && check_perms('users_view_ips', $Class)) {
?>
		[<a href="user.php?action=sessions&amp;userid=<?=$UserID?>">Sessions</a>]
<? }
if (check_perms('admin_reports')) {
?>
		[<a href="reportsv2.php?view=reporter&amp;id=<?=$UserID?>">Reports</a>]
<? }
?>
	</div>

	<div class="sidebar">
<?	if ($Avatar && empty($HeavyInfo['DisableAvatars'])) {
		if(check_perms('site_proxy_images') && !empty($Avatar)) {
			$Avatar = 'http://'.SITE_URL.'/image.php?c=1&avatar='.$UserID.'&i='.urlencode($Avatar);
		}
?>
		<div class="box">
			<div class="head colhead_dark">Avatar</div>
			<div align="center"><img src="<?=display_str($Avatar)?>" width="150" alt="<?=$Username?>'s avatar" /></div>
		</div>
<? } ?>
		<div class="box">
			<div class="head colhead_dark">Stats</div>
			<ul class="stats nobullet">
				<li>Joined: <?=$JoinedDate?></li>
<? if ($Paranoia < 5 || check_perms('users_override_paranoia', $Class) || $OwnProfile) { ?>
				<li>Last Seen: <?=$LastAccess?></li>
<?
}

if(check_perms('users_override_paranoia', $Class)) {
	$ViewStats = $ViewUploaded = $ViewRequested = 1;
} else {
	$ViewStats = ($Paranoia < 4 || $OwnProfile);
	$ViewUploaded = ($Paranoia < 3 || check_perms('users_view_uploaded') || $OwnProfile);
	$ViewRequested = ($Paranoia < 3 || check_perms('users_view_uploaded') || $OwnProfile);
}

if ($ViewStats) {
?>
				<li>Uploaded: <?=get_size($Uploaded)?></li>
				<li>Downloaded: <?=get_size($Downloaded)?></li>
				<li>Ratio: <?=ratio($Uploaded, $Downloaded)?></li>
<?	if(isset($RequiredRatio)) { ?>
				<li>Required ratio: <?=number_format((double)$RequiredRatio, 2)?></li>
<?	}?>
<? } ?>
			</ul>
		</div>
<?

	$DB->query("SELECT COUNT(DISTINCT r.ID), SUM(rv.Bounty) FROM requests AS r LEFT JOIN requests_votes AS rv ON r.ID=rv.RequestID WHERE r.FillerID = ".$UserID);
	list($RequestsFilled, $TotalBounty) = $DB->next_record();
	$DB->query("SELECT COUNT(rv.RequestID), SUM(rv.Bounty) FROM requests_votes AS rv WHERE rv.UserID = ".$UserID);
	list($RequestsVoted, $TotalSpent) = $DB->next_record();
	
	$DB->query("SELECT COUNT(ID) FROM torrents WHERE UserID='$UserID'");
	list($Uploads) = $DB->next_record();

if($Paranoia < 5 || check_perms('users_override_paranoia', $Class) || $OwnProfile) {
	include(SERVER_ROOT.'/classes/class_user_rank.php');
	$Rank = new USER_RANK;

	$DB->query("SELECT COUNT(DISTINCT r.ID), SUM(rv.Bounty) FROM requests AS r LEFT JOIN requests_votes AS rv ON r.ID=rv.RequestID WHERE r.FillerID = ".$UserID);
	list($RequestsFilled, $TotalBounty) = $DB->next_record();
	$DB->query("SELECT COUNT(rv.RequestID), SUM(rv.Bounty) FROM requests_votes AS rv WHERE rv.UserID = ".$UserID);
	list($RequestsVoted, $TotalSpent) = $DB->next_record();
	
	$DB->query("SELECT COUNT(ID) FROM torrents WHERE UserID='$UserID'");
	list($Uploads) = $DB->next_record();

	$DB->query("SELECT COUNT(ta.ArtistID) FROM torrents_artists AS ta WHERE ta.UserID = ".$UserID);
	list($ArtistsAdded) = $DB->next_record();

	$UploadedRank = $Rank->get_rank('uploaded', $Uploaded);
	$DownloadedRank = $Rank->get_rank('downloaded', $Downloaded);
	$UploadsRank = $Rank->get_rank('uploads', $Uploads);
	$RequestRank = $Rank->get_rank('requests', $RequestsFilled);
	$PostRank = $Rank->get_rank('posts', $ForumPosts);
	$BountyRank = $Rank->get_rank('bounty', $TotalSpent);
	$ArtistsRank = $Rank->get_rank('artists', $ArtistsAdded);

	if($Downloaded == 0) { $Ratio = 1; }
	elseif($Uploaded == 0) { $Ratio = 0.5; }
	else { $Ratio = round($Uploaded/$Downloaded, 2); }
	$OverallRank = $Rank->overall_score($UploadedRank, $DownloadedRank, $UploadsRank, $RequestRank, $PostRank, $BountyRank, $ArtistsRank, $Ratio);

?>
		<div class="box">
			<div class="head colhead_dark">Percentile Rankings (Hover for values)</div>
			<ul class="stats nobullet">
				<li title="<?=$ViewStats ? get_size($Uploaded) : 'Hidden'?>">Data uploaded: <?=number_format((int)$UploadedRank)?></li>
				<li title="<?=$ViewStats ? get_size($Downloaded) : 'Hidden'?>">Data downloaded: <?=number_format((int)$DownloadedRank)?></li>
				<li title="<?=$ViewUploaded ? $Uploads : 'Hidden'?>">Torrents uploaded: <?=number_format((int)$UploadsRank)?></li>
				<li title="<?=$ViewRequested ? $RequestsFilled : 'Hidden'?>">Requests filled: <?=number_format((int)$RequestRank)?></li>
				<li title="<?=$ViewRequested ? get_size($TotalSpent) : 'Hidden'?>">Bounty spent: <?=number_format((int)$BountyRank)?></li>
				<li title="<?=$ForumPosts?>">Posts made: <?=number_format((int)$PostRank)?></li>
				<li title="<?=$ArtistsAdded?>">Artists added: <?=number_format((int)$ArtistsRank)?></li>
				<li><strong>Overall rank: <?=number_format((int)$OverallRank)?></strong></li>
			</ul>
		</div>
<?
	}

	if (check_perms('users_mod', $Class) || check_perms('users_view_ips',$Class) || check_perms('users_view_keys',$Class)) {
		$DB->query("SELECT COUNT(*) FROM users_history_passwords WHERE UserID='$UserID'");
		list($PasswordChanges) = $DB->next_record();
		if (check_perms('users_view_keys',$Class)) {
			$DB->query("SELECT COUNT(*) FROM users_history_passkeys WHERE UserID='$UserID'");
			list($PasskeyChanges) = $DB->next_record();
		}
		if (check_perms('users_view_ips',$Class)) {
			$DB->query("SELECT COUNT(DISTINCT IP) FROM users_history_ips WHERE UserID='$UserID'");
			list($IPChanges) = $DB->next_record();
		}
		if (check_perms('users_view_email',$Class)) {
			$DB->query("SELECT COUNT(*) FROM users_history_emails WHERE UserID='$UserID'");
			list($EmailChanges) = $DB->next_record();
		}
?>
	<div class="box">
		<div class="head colhead_dark">History</div>
		<ul class="stats nobullet">
<?	if (check_perms('users_view_email',$Class)) { ?>
<li>Emails: <?=number_format((int)$EmailChanges)?> [<a href="userhistory.php?action=email&amp;userid=<?=$UserID?>">View</a>]&nbsp;[<a href="userhistory.php?action=email&amp;userid=<?=$UserID?>&amp;usersonly=1">View Users</a>]</li>
<?
	}
	if (check_perms('users_view_ips',$Class)) {
?>
	<li>IPs: <?=number_format((int)$IPChanges)?> [<a href="userhistory.php?action=ips&amp;userid=<?=$UserID?>">View</a>]&nbsp;[<a href="userhistory.php?action=ips&amp;userid=<?=$UserID?>&amp;usersonly=1">View Users</a>]</li>
<?
	}
	if (check_perms('users_view_keys',$Class)) {
?>
			<li>Passkeys: <?=number_format((int)$PasskeyChanges)?> [<a href="userhistory.php?action=passkeys&amp;userid=<?=$UserID?>">View</a>]</li>
<?
	}
	if (check_perms('users_mod', $Class)) {
?>
			<li>Passwords: <?=number_format((int)$PasswordChanges)?> [<a href="userhistory.php?action=passwords&amp;userid=<?=$UserID?>">View</a>]</li>
			<li>Stats: N/A [<a href="userhistory.php?action=stats&amp;userid=<?=$UserID?>">View</a>]</li>
<?
			
	}
?>
		</ul>
	</div>
<?	} ?>
		<div class="box">
			<div class="head colhead_dark">Personal</div>
			<ul class="stats nobullet">
				<li>Class: <?=$ClassLevels[$Class]['Name']?></li>
				<li>Paranoia Level: <?=number_format((int)$Paranoia)?></li>
<?	if (check_perms('users_view_email',$Class) || $OwnProfile) { ?>
				<li>Email: <a href="mailto:<?=display_str($Email)?>"><?=display_str($Email)?></a>
<?		if (check_perms('users_view_email',$Class)) { ?>
					[<a href="user.php?action=search&amp;email_history=on&amp;email=<?=display_str($Email)?>" title="Search">S</a>]
<?		} ?>
				</li>
<?	}

if (check_perms('users_view_ips',$Class)) {
?>
				<li>IP: <?=display_str($IP)?> (<?=old_geoip($IP)?>) [<a href="user.php?action=search&amp;ip_history=on&amp;ip=<?=display_str($IP)?>&matchtype=strict" title="Search">S</a>]</li>
				<li>Host: <?=get_host($IP)?></li>
<?
}

if (check_perms('users_view_keys',$Class) || $OwnProfile) {
?>
				<li>Passkey: <?=display_str($torrent_pass)?>
<? }
if (check_perms('users_view_invites')) {
	if (!$InviterID) {
		$Invited="<i>Nobody</i>";
	} else {
		$Invited='<a href="user.php?id='.$InviterID.'">'.$InviterName.'</a>';
	}
?>
				<li>Invited By: <?=$Invited?></li>
				<li>Invites: <? if($DisableInvites) { echo 'X'; } else { echo number_format((int)$Invites); } ?></li>
<?
}
?>
			</ul>
		</div>
<?
// These stats used to be all together in one UNION'd query
// But we broke them up because they had a habit of locking each other to death.
// They all run really quickly anyways.
$DB->query("SELECT COUNT(x.uid), COUNT(DISTINCT x.fid) FROM xbt_snatched AS x INNER JOIN torrents AS t ON t.ID=x.fid WHERE x.uid='$UserID'");
list($Snatched, $UniqueSnatched) = $DB->next_record();
$DB->query("SELECT COUNT(ID) FROM torrents_comments WHERE AuthorID='$UserID'");
list($NumComments) = $DB->next_record();
$DB->query("SELECT COUNT(ID) FROM collages WHERE Deleted='0' AND UserID='$UserID'");
list($NumCollages) = $DB->next_record();
$DB->query("SELECT COUNT(DISTINCT CollageID) FROM collages_torrents AS ct JOIN collages ON CollageID = ID WHERE Deleted='0' AND ct.UserID='$UserID'");
list($NumCollageContribs) = $DB->next_record();
?>
		<div class="box">
			<div class="head colhead_dark">Community</div>
			<ul class="stats nobullet">
				<li>Forum Posts: <?=number_format((int)$ForumPosts)?> [<a href="userhistory.php?action=posts&amp;userid=<?=$UserID?>" title="View">View</a>]</li>
				<li>Torrent Comments: <?=number_format((int)$NumComments)?> [<a href="comments.php?id=<?=$UserID?>" title="View">View</a>]</li>
				<li>Collages started: <?=number_format((int)$NumCollages)?> [<a href="collages.php?userid=<?=$UserID?>" title="View">View</a>]</li>
				<li>Collages contributed to: <?=number_format((int)$NumCollageContribs)?> [<a href="collages.php?userid=<?=$UserID?>&amp;contrib=1" title="View">View</a>]</li>
<? if($ViewUploaded) {
	$TotalBounty = get_size($TotalBounty);
	$TotalSpent = get_size($TotalSpent);
?>
				<li>Requests filled: <?=number_format((int)$RequestsFilled)?> for <?=$TotalBounty?> [<a href="requests.php?type=filled&amp;userid=<?=$UserID?>" title="View">View</a>]</li>
				<li>Requests voted: <?=number_format((int)$RequestsVoted)?> for <?=$TotalSpent?> [<a href="requests.php?type=voted&amp;userid=<?=$UserID?>" title="View">View</a>]</li>
				<li>Uploaded: <?=number_format((int)$Uploads)?> [<a href="torrents.php?type=uploaded&amp;userid=<?=$UserID?>" title="View">View</a>]<? if(check_perms('zip_downloader')) { ?> [<a href="torrents.php?action=redownload&amp;type=uploads&amp;userid=<?=$UserID?>" onclick="return confirm('If you no longer have the content, your ratio WILL be affected, be sure to check the size of all albums before redownloading.');">Download</a>]<? } ?></li>
<?
	$DB->query("SELECT COUNT(DISTINCT GroupID) FROM torrents WHERE UserID = '$UserID'");
	list($UniqueGroups) = $DB->next_record();
?>
	<li>Unique Groups: <?=number_format((int)$UniqueGroups)?> [<a href="torrents.php?type=uploaded&amp;userid=<?=$UserID?>&amp;filter=uniquegroup">View</a>]</li>
<?

	$DB->query("SELECT COUNT(ID) FROM torrents WHERE ((LogScore = 100 AND Format = 'FLAC') OR (Media = 'Vinyl' AND Format = 'FLAC') OR (Media = 'WEB' AND Format = 'FLAC') OR (Media = 'DVD' AND Format = 'FLAC') OR (Media = 'Soundboard' AND Format = 'FLAC')) AND UserID = '$UserID'");
	list($PerfectFLACs) = $DB->next_record();
?>
	<li>"Perfect" FLACs: <?=number_format((int)$PerfectFLACs)?> [<a href="torrents.php?type=uploaded&amp;userid=<?=$UserID?>&amp;filter=perfectflac">View</a>]</li>
<?
}

if ($Paranoia < 1 || check_perms('users_view_seedleech') || check_perms('users_override_paranoia') || $OwnProfile) {

	$DB->query("SELECT COUNT(x.uid) FROM xbt_files_users AS x INNER JOIN torrents AS t ON t.ID=x.fid WHERE x.uid='$UserID' AND x.remaining>0");
	list($Leeching) = $DB->next_record();
	$DB->query("SELECT COUNT(x.uid) FROM xbt_files_users AS x INNER JOIN torrents AS t ON t.ID=x.fid WHERE x.uid='$UserID' AND x.remaining=0");
	list($Seeding) = $DB->next_record();
?>
				<li>Seeding: <?=number_format((int)$Seeding)?> [<a href="torrents.php?type=seeding&amp;userid=<?=$UserID?>" title="View">View</a>]<? if (check_perms('zip_downloader')) { ?> [<a href="torrents.php?action=redownload&amp;type=seeding&amp;userid=<?=$UserID?>" onclick="return confirm('If you no longer have the content, your ratio WILL be affected, be sure to check the size of all albums before redownloading.');">Download</a>]<? } ?></li>
				<li>Leeching: <?=number_format((int)$Leeching)?> [<a href="torrents.php?type=leeching&amp;userid=<?=$UserID?>" title="View">View</a>]</li>
<?
}

if ($Paranoia < 2 || check_perms('users_view_seedleech') || check_perms('users_override_paranoia') || $OwnProfile) {
?>
				<li>Snatched: <?=number_format((int)$Snatched)?> 
<?				if(check_perms('site_view_torrent_snatchlist')) {?>
						(<?=number_format((int)$UniqueSnatched)?>)
<?				}?>
				[<a href="torrents.php?type=snatched&amp;userid=<?=$UserID?>" title="View">View</a>]<? if(check_perms('zip_downloader')) { ?> [<a href="torrents.php?action=redownload&amp;type=snatches&amp;userid=<?=$UserID?>" onclick="return confirm('If you no longer have the content, your ratio WILL be affected, be sure to check the size of all albums before redownloading.');">Download</a>]<? } ?></li>
<? }

if(check_perms('site_view_torrent_snatchlist')) {
	$DB->query("SELECT COUNT(ud.UserID), COUNT(DISTINCT ud.TorrentID) FROM users_downloads AS ud INNER JOIN torrents AS t ON t.ID=ud.TorrentID WHERE ud.UserID='$UserID'");
	list($NumDownloads, $UniqueDownloads) = $DB->next_record();
?>
				<li>Downloaded: <?=number_format((int)$NumDownloads)?> (<?=number_format((int)$UniqueDownloads)?>) [<a href="torrents.php?type=downloaded&amp;userid=<?=$UserID?>" title="View">View</a>]</li>
<?
}

if ($Paranoia < 2 || check_perms('users_view_invites') || check_perms('users_override_paranoia') || $OwnProfile) {
	$DB->query("SELECT COUNT(UserID) FROM users_info WHERE Inviter='$UserID'");
	list($Invited) = $DB->next_record();
?>
				<li>Invited: <?=number_format((int)$Invited)?></li>
<? } ?>
			</ul>
		</div>
	</div>
	<div class="main_column">
<?
if ($RatioWatchEnds!='0000-00-00 00:00:00'
		&& (time() < strtotime($RatioWatchEnds))
		&& ($Downloaded*$RequiredRatio)>$Uploaded
		) {
?>
		<div class="box">
			<div class="head">Ratio watch</div>
			<div class="pad">This user is currently on ratio watch, and must upload <?=get_size(($Downloaded*$RequiredRatio)-$Uploaded)?> in the next <?=time_diff($RatioWatchEnds)?>, or their leeching privileges will be revoked. Amount downloaded while on ratio watch: <?=get_size($Downloaded-$RatioWatchDownload)?></div>
		</div>
<? } ?>
		<div class="box">
			<div class="head">
				<span style="float:left;">Profile<? if ($CustomTitle) { echo " - ".html_entity_decode($CustomTitle); } ?></span>
				<span style="float:right;"><?=$Badges?></span>&nbsp;
			</div>
			<div class="pad">
<? if (!$Info) { ?>
				This profile is currently empty.
<?
} else {
	echo $Text->full_format($Info);
}

?>
			</div>
		</div>
<?
if ($Snatched > 4 && $Paranoia < 2) {
	$RecentSnatches = $Cache->get_value('recent_snatches_'.$UserID);
	if(!is_array($RecentSnatches)){
		$DB->query("SELECT
		g.ID,
		g.Name,
		g.WikiImage
		FROM xbt_snatched AS s
		INNER JOIN torrents AS t ON t.ID=s.fid
		INNER JOIN torrents_group AS g ON t.GroupID=g.ID
		WHERE s.uid='$UserID'
		AND g.CategoryID='1'
		AND g.WikiImage <> ''
		GROUP BY g.ID
		ORDER BY s.tstamp DESC
		LIMIT 5");
		$RecentSnatches = $DB->to_array();
		
		$Artists = get_artists($DB->collect('ID'));
		foreach($RecentSnatches as $Key => $SnatchInfo) {
			$RecentSnatches[$Key]['Artist'] = display_artists($Artists[$SnatchInfo['ID']], false, true);
		}
		$Cache->cache_value('recent_snatches_'.$UserID, $RecentSnatches, 0); //inf cache
	}
?>
	<table class="recent" cellpadding="0" cellspacing="0" border="0">
		<tr class="colhead">
			<td colspan="5">Recent Snatches</td>
		<tr>
		<tr>
<?		
		foreach($RecentSnatches as $RS) { ?>
			<td>
				<a href="torrents.php?id=<?=$RS['ID']?>" title="<?=$RS['Artist']?><?=$RS['Name']?>"><img src="<?=$RS['WikiImage']?>" alt="<?=$RS['Artist']?><?=$RS['Name']?>" width="107" /></a>
			</td>
<?		} ?>
		</tr>
	</table>
<?
}

if(!isset($Uploads)) { $Uploads = 0; }
if ($Uploads > 4 && $Paranoia < 3) {
	$RecentUploads = $Cache->get_value('recent_uploads_'.$UserID);
	if(!is_array($RecentUploads)){
		$DB->query("SELECT 
		g.ID,
		g.Name,
		g.WikiImage
		FROM torrents_group AS g
		INNER JOIN torrents AS t ON t.GroupID=g.ID
		WHERE t.UserID='$UserID'
		AND g.CategoryID='1'
		AND g.WikiImage <> ''
		GROUP BY g.ID
		ORDER BY t.Time DESC
		LIMIT 5");
		$RecentUploads = $DB->to_array();
		$Artists = get_artists($DB->collect('ID'));
		foreach($RecentUploads as $Key => $UploadInfo) {
			$RecentUploads[$Key]['Artist'] = display_artists($Artists[$UploadInfo['ID']], false, true);
		}
		$Cache->cache_value('recent_uploads_'.$UserID, $RecentUploads, 0); //inf cache
	}
?>
	<table class="recent" cellpadding="0" cellspacing="0" border="0">
		<tr class="colhead">
			<td colspan="5">Recent Uploads</td>
		<tr>
<?		foreach($RecentUploads as $RU) { ?>
			<td>
				<a href="torrents.php?id=<?=$RU['ID']?>" title="<?=$RU['Artist']?><?=$RU['Name']?>"><img src="<?=$RU['WikiImage']?>" alt="<?=$RU['Artist']?><?=$RU['Name']?>" width="107" /></a>
			</td>
<?		} ?>
		</tr>
	</table>
<?
}

$DB->query("SELECT ID, Name FROM collages WHERE UserID='$UserID' AND CategoryID='0' AND Deleted='0'");
list($CollageID, $Name) = $DB->next_record();
if($CollageID) {
	$DB->query("SELECT ct.GroupID,
		tg.WikiImage,
		tg.CategoryID
		FROM collages_torrents AS ct
		JOIN torrents_group AS tg ON tg.ID=ct.GroupID
		WHERE ct.CollageID='$CollageID'
		ORDER BY ct.Sort LIMIT 5");
	$Collage = $DB->to_array();

?>
	<table class="recent" cellpadding="0" cellspacing="0" border="0">
		<tr class="colhead">
			<td colspan="5"><?=display_str($Name)?> - <a href="collages.php?id=<?=$CollageID?>">see full</a></td>
		<tr>
<?	foreach($Collage as $C) {
			$Group = get_groups(array($C['GroupID']));
			$Group = array_pop($Group['matches']);
			list($GroupID, $GroupName, $GroupYear, $GroupRecordLabel, $GroupCatalogueNumber, $TagList, $ReleaseType, $Torrents, $GroupArtists) = array_values($Group);
			
			$Name = '';
			$Name .= display_artists(array('1'=>$GroupArtists), false, true);
			$Name .= $GroupName;
?>
			<td>
				<a href="torrents.php?id=<?=$GroupID?>" title="<?=$Name?>"><img src="<?=$C['WikiImage']?>" alt="<?=$Name?>" width="107" /></a>
			</td>
<?	} ?>
		</tr>
	</table>
<?
}


if ((check_perms('users_view_invites')) && $Invited > 0) {
	include(SERVER_ROOT.'/classes/class_invite_tree.php');
	$Tree = new INVITE_TREE($UserID, array('visible'=>false));
?>
		<div class="box">
			<div class="head">Invite Tree <a href="#" onclick="$('#invitetree').toggle();return false;">(View)</a></div>
			<div id="invitetree" class="hidden">
				<? $Tree->make_tree(); ?>
			</div>
		</div>
<?
}

// Requests
$DB->query("SELECT
		r.ID,
		r.CategoryID,
		r.Title,
		r.Year,
		r.TimeAdded,
		COUNT(rv.UserID) AS Votes,
		SUM(rv.Bounty) AS Bounty
	FROM requests AS r
		LEFT JOIN users_main AS u ON u.ID=UserID
		LEFT JOIN requests_votes AS rv ON rv.RequestID=r.ID
	WHERE r.UserID = ".$UserID."
		AND r.TorrentID = 0
	GROUP BY r.ID
	ORDER BY Votes DESC");

if($DB->record_count() > 0) {
	$Requests = $DB->to_array();
?>
		<div class="box">
			<div class="head">Requests <a href="#" onclick="$('#requests').toggle();return false;">(View)</a></div>
			<div id="requests" class="hidden">
				<table cellpadding="6" cellspacing="1" border="0" class="border" width="100%">
					<tr class="colhead_dark">
						<td style="width:48%;">
							<strong>Request Name</strong>
						</td>
						<td>
							<strong>Vote</strong>
						</td>
						<td>
							<strong>Bounty</strong>
						</td>
						<td>
							<strong>Added</strong>
						</td>
					</tr>
<?
	foreach($Requests as $Request) {
		list($RequestID, $CategoryID, $Title, $Year, $TimeAdded, $Votes, $Bounty) = $Request;

		$Request = get_requests(array($RequestID));
		$Request = $Request['matches'][$RequestID];
		if(empty($Request)) {
			continue;
		}

		list($RequestID, $RequestorID, $RequestorName, $TimeAdded, $LastVote, $CategoryID, $Title, $Year, $Image, $Description, $CatalogueNumber, $ReleaseType,
			$BitrateList, $FormatList, $MediaList, $LogCue, $FillerID, $FillerName, $TorrentID, $TimeFilled) = $Request;
		
			$CategoryName = $Categories[$CategoryID - 1];
			
			if($CategoryName == "Music") {
				$ArtistForm = get_request_artists($RequestID);
				$ArtistLink = display_artists($ArtistForm, true, true);
				$FullName = $ArtistLink."<a href='requests.php?action=view&amp;id=".$RequestID."'>".$Title." [".$Year."]</a>";
			} else if($CategoryName == "Audiobooks" || $CategoryName == "Comedy") {
				$FullName = "<a href='requests.php?action=view&amp;id=".$RequestID."'>".$Title." [".$Year."]</a>";
			} else {
				$FullName ="<a href='requests.php?action=view&amp;id=".$RequestID."'>".$Title."</a>";
			}
			
			$Row = (empty($Row) || $Row == 'a') ? 'b' : 'a';
?>
					<tr class="row<?=$Row?>">
						<td>
							<?=$FullName?>
							<div class="tags">
<?			
		$Tags = $Request['Tags'];
		$TagList = array();
		foreach($Tags as $TagID => $TagName) {
			$TagList[] = "<a href='requests.php?tag=".$TagID."'>".display_str($TagName)."</a>";
		}
		$TagList = implode(', ', $TagList);
?>
								<?=$TagList?>
							</div>
						</td>
						<td>
							<?=$Votes?> 
<?  	if(check_perms('site_vote')){ ?>
							<input type="hidden" id="auth" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
				&nbsp;&nbsp; <a href="javascript:Vote(20971520)"><strong>(+)</strong></a>
<?		} ?> 
						</td>
						<td>
							<?=get_size($Bounty)?>
						</td>
						<td>
							<?=time_diff($TimeAdded)?>
						</td>
					</tr>
<?	} ?>
				</table>
			</div>
		</div>
<?
}
?>
<br />
<? if (check_perms('users_mod', $Class)) { ?>
		<form id="form" action="user.php" method="post">
		<input type="hidden" name="action" value="moderate" />
		<input type="hidden" name="userid" value="<?=$UserID?>" />
		<input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />

		<div class="box">
			<div class="head">Staff Notes <a href="#" name="admincommentbutton" onclick="ChangeTo('text'); return false;">(Edit)</a></div>
			<div class="pad">
				<div id="admincommentlinks" class="AdminComment box" style="width:98%;"><?=$Text->full_format($AdminComment)?></div>
				<textarea id="admincomment" onkeyup="resize('admincomment');" class="AdminComment hidden" name="AdminComment" cols="65" rows="26" style="width:98%;"><?=display_str($AdminComment)?></textarea>
				<a href="#" name="admincommentbutton" onclick="ChangeTo('text'); return false;">Toggle Edit</a>
				<script type="text/javascript">
					resize('admincomment');
				</script>
			</div>
		</div>

		<table>
			<tr>
				<td class="colhead" colspan="2">User Info</td>
			</tr>
<?	if (check_perms('users_edit_usernames', $Class)) { ?>
			<tr>
				<td class="label">Username:</td>
				<td><input type="text" size="20" name="Username" value="<?=display_str($Username)?>" /></td>
			</tr>
<?
	}

	if (check_perms('users_edit_titles')) {
?>
			<tr>
				<td class="label">CustomTitle:</td>
				<td><input type="text" size="50" name="Title" value="<?=display_str($CustomTitle)?>" /></td>
			</tr>
<?
	}

	if (check_perms('users_promote_below', $Class) || check_perms('users_promote_to', $Class-1)) {
?>
			<tr>
				<td class="label">Class:</td>
				<td>
					<select name="Class">
<?
		foreach ($ClassLevels as $CurClass) {
			if (check_perms('users_promote_below', $Class) && $CurClass['ID']>=$LoggedUser['Class']) { break; }
			if ($CurClass['ID']>$LoggedUser['Class']) { break; }
			if ($Class===$CurClass['Level']) { $Selected='selected="selected"'; } else { $Selected=""; }
?>
						<option value="<?=$CurClass['ID']?>" <?=$Selected?>><?=$CurClass['Name'].' ('.$CurClass['Level'].')'?></option>
<?		} ?>
					</select>
				</td>
			</tr>
<?
	}

	if (check_perms('users_give_donor')) {
?>
			<tr>
				<td class="label">Donor:</td>
				<td><input type="checkbox" name="Donor" <? if ($Donor == 1) { ?>checked="checked" <? } ?> /></td>
			</tr>
<?
	}
	if (check_perms('users_promote_below') || check_perms('users_promote_to')) {
?>
			<tr>
				<td class="label">Artist:</td>
				<td><input type="checkbox" name="Artist" <? if ($Artist == 1) { ?>checked="checked" <? } ?> /></td>
			</tr>
<?
	}
	if (check_perms('users_make_invisible')) {
?>
			<tr>
				<td class="label">Visible:</td>
				<td><input type="checkbox" name="Visible" <? if ($Visible == 1) { ?>checked="checked" <? } ?> /></td>
			</tr>
<?
	}

	if (check_perms('users_edit_ratio',$Class) || (check_perms('users_edit_own_ratio') && $UserID == $LoggedUser['ID'])) {
?>
			<tr>
				<td class="label">Uploaded:</td>
				<td>
					<input type="hidden" name="OldUploaded" value="<?=$Uploaded?>" />
					<input type="text" size="20" name="Uploaded" value="<?=$Uploaded?>" />
				</td>
			</tr>
			<tr>
				<td class="label">Downloaded:</td>
				<td>
					<input type="hidden" name="OldDownloaded" value="<?=$Downloaded?>" />
					<input type="text" size="20" name="Downloaded" value="<?=$Downloaded?>" />
				</td>
			</tr>
			<tr>
				<td class="label">Merge Stats <strong>From:</strong></td>
				<td>
					<input type="text" size="40" name="MergeStatsFrom" />
				</td>
			</tr>
<?
	}

	if (check_perms('users_edit_invites')) {
?>
			<tr>
				<td class="label">Invites:</td>
				<td><input type="text" size="5" name="Invites" value="<?=$Invites?>" /></td>
			</tr>
<?
	}

	if (check_perms('admin_manage_fls') || (check_perms('users_mod') && $OwnProfile)) {
?>
			<tr>
				<td class="label">First Line Support:</td>
				<td><input type="text" size="50" name="SupportFor" value="<?=display_str($SupportFor)?>" /></td>
			</tr>
<?
	}

	if (check_perms('users_edit_reset_keys')) {
?>
			<tr>
				<td class="label">Reset:</td>
				<td>
					<input type="checkbox" name="ResetRatioWatch" id="ResetRatioWatch" /> <label for="ResetRatioWatch">Ratio Watch</label> |
					<input type="checkbox" name="ResetPasskey" id="ResetPasskey" /> <label for="ResetPasskey">Passkey</label> |
					<input type="checkbox" name="ResetAuthkey" id="ResetAuthkey" /> <label for="ResetAuthkey">Authkey</label> |
					<input type="checkbox" name="ResetIPHistory" id="ResetIPHistory" /> <label for="ResetIPHistory">IP History</label> |
					<input type="checkbox" name="ResetEmailHistory" id="ResetEmailHistory" /> <label for="ResetEmailHistory">Email History</label>
					<br />
					<input type="checkbox" name="ResetSnatchList" id="ResetSnatchList" /> <label for="ResetSnatchList">Snatch List</label> | 
					<input type="checkbox" name="ResetDownloadList" id="ResetDownloadList" /> <label for="ResetDownloadList">Download List</label>
				</td>
			</tr>
<?
	}

	if (check_perms('users_edit_password')) {
?>
			<tr>
				<td class="label">New Password:</td>
				<td>
					<input type="text" size="30" id="change_password" name="ChangePassword" />
				</td>
			</tr>
<?	} ?>
		</table><br />

<?	if (check_perms('users_warn')) { ?>
		<table>
			<tr class="colhead">
				<td colspan="2">Warn User</td>
			</tr>
			<tr>
				<td class="label">Warned:</td>
				<td>
					<input type="checkbox" name="Warned" <? if ($Warned != '0000-00-00 00:00:00') { ?>checked="checked"<? } ?> />
				</td>
			</tr>
<?		if ($Warned=='0000-00-00 00:00:00') { // user is not warned ?>
			<tr>
				<td class="label">Expiration:</td>
				<td>
					<select name="WarnLength">
						<option value="">---</option>
						<option value="1"> 1 Week</option>
						<option value="2"> 2 Weeks</option>
						<option value="4"> 4 Weeks</option>
						<option value="8"> 8 Weeks</option>
					</select>
				</td>
			</tr>
<?		} else { // user is warned ?>
			<tr>
				<td class="label">Extension:</td>
				<td>
					<select name="ExtendWarning">
						<option>---</option>
						<option value="1"> 1 Week</option>
						<option value="2"> 2 Weeks</option>
						<option value="4"> 4 Weeks</option>
						<option value="8"> 8 Weeks</option>
					</select>
				</td>
			</tr>
<?		} ?>
			<tr>
				<td class="label">Reason:</td>
				<td>
					<input type="text" size="60" name="WarnReason" />
				</td>
			</tr>
<?	} ?>
		</table><br />
		<table>
			<tr class="colhead"><td colspan="2">User Privileges</td></tr>
<?	if (check_perms('users_disable_posts') || check_perms('users_disable_any')) {
		$DB->query("SELECT DISTINCT Email, IP FROM users_history_emails WHERE UserID = ".$UserID." ORDER BY Time ASC");
		$Emails = $DB->to_array();
?>
			<tr>
				<td class="label">Disable:</td>
				<td>
					<input type="checkbox" name="DisableAvatar" id="DisableAvatar"<? if ($DisableAvatar==1) { ?>checked="checked"<? } ?> /> <label for="DisableAvatar">Avatar</label>
<?		if (check_perms('users_disable_any')) { ?>  |
					<input type="checkbox" name="DisableInvites" id="DisableInvites"<? if ($DisableInvites==1) { ?>checked="checked"<? } ?> /> <label for="DisableInvites">Invites</label> |
					<input type="checkbox" name="DisablePosting" id="DisablePosting"<? if ($DisablePosting==1) { ?>checked="checked"<? } ?> /> <label for="DisablePosting">Posting</label> |
					<input type="checkbox" name="DisableForums" id="DisableForums"<? if ($DisableForums==1) { ?>checked="checked"<? } ?> /> <label for="DisableForums">Forums</label> |
					<input type="checkbox" name="DisableTagging" id="DisableTagging"<? if ($DisableTagging==1) { ?>checked="checked"<? } ?> /> <label for="DisableTagging">Tagging</label>
					<br />
					 <input type="checkbox" name="DisableUpload" id="DisableUpload"<? if ($DisableUpload==1) { ?>checked="checked"<? } ?> /> <label for="DisableUpload">Upload</label> |
					<input type="checkbox" name="DisableWiki" id="DisableWiki"<? if ($DisableWiki==1) { ?>checked="checked"<? } ?> /> <label for="DisableWiki">Wiki</label> |
					<input type="checkbox" name="DisableLeech" id="DisableLeech"<? if ($DisableLeech==0) { ?>checked="checked"<? } ?> /><label for="DisableLeech">Leech</label> |
					<input type="checkbox" name="DisablePM" id="DisablePM"<? if ($DisablePM==1) { ?>checked="checked"<? } ?> /><label for="DisablePM">PM</label> |
					<input type="checkbox" name="DisableIRC" id="DisableIRC"<? if ($DisableIRC==1) { ?>checked="checked"<? } ?> /><label for="DisableIRC">IRC</label>
				</td>
			</tr>
			<tr>
				<td class="label">Hacked:</td>
				<td>
					<input type="checkbox" name="SendHackedMail" id="SendHackedMail" /> <label for="SendHackedMail">Send hacked account email</label> to 
					<select name="HackedEmail">
<?
			foreach($Emails as $Email) {
				list($Address, $IP) = $Email;
?>
						<option value="<?=display_str($Address)?>"><?=display_str($Address)?> - <?=display_str($IP)?></option>
<?			} ?>
					</select>
				</td>
			</tr>

<?		} ?>
<?
	}

	if (check_perms('users_disable_any')) {
?>
			<tr>
				<td class="label">Account:</td>
				<td>
					<select name="UserStatus">
						<option value="0" <? if ($Enabled==0) { ?>selected="selected"<? } ?>>Unconfirmed</option>
						<option value="1" <? if ($Enabled==1) { ?>selected="selected"<? } ?>>Enabled</option>
						<option value="2" <? if ($Enabled==2) { ?>selected="selected"<? } ?>>Disabled</option>
<?		if (check_perms('users_delete_users')) { ?>
						<optgroup label="-- WARNING --"></optgroup>
						<option value="delete">Delete Account</option>
<?		} ?>
					</select>
				</td>
			</tr>
			<tr>
				<td class="label">User Reason:</td>
				<td>
					<input type="text" size="60" name="UserReason" />
				</td>
			</tr>
<?	} ?>
		</table><br />
<?	if(check_perms('users_logout')) { ?>
		<table>
			<tr class="colhead"><td colspan="2">Session</td></tr>
			<tr>
				<td class="label">Reset session:</td>
				<td><input type="checkbox" name="ResetSession" id="ResetSession" /></td>
			</tr>
			<tr>
				<td class="label">Log out:</td>
				<td><input type="checkbox" name="LogOut" id="LogOut" /></td>
			</tr>

		</table>
<?	} ?>
		<table>
			<tr class="colhead"><td colspan="2">Submit</td></tr>
			<tr>
				<td class="label">Reason:</td>
				<td>
					<textarea rows="1" cols="50" name="Reason" id="Reason" onkeyup="resize('Reason');"></textarea>
				</td>
			</tr>

			<tr>
				<td align="right" colspan="2">
					<input type="submit" value="Save Changes" />
				</td>
			</tr>
		</table>
		</form>
<? } ?>
	</div>
</div>
<? show_footer(); ?>
