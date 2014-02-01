<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @author		Noah Manneschmidt
 * @copyright	(c) 2013 Curse Inc.
 * @license		All Rights Reserved
 * @package		CurseProfile
 * @link		http://www.curse.com/
 *
**/

$magicWords = [];
$magicWords['en'] = [
						'cpGravatar'						=> array(0, 'cpGravatar'),
						'placeholderImage'					=> array(0, 'img'),
						'recentactivity'					=> array(0, 'recentactivity'),
						'friendadd'							=> array(0, 'friendadd'),
						'friendcount'						=> array(0, 'friendcount'),
						'friendlist'						=> array(0, 'friendlist'),
						'comments'							=> array(0, 'comments'),
						'avatar'							=> array(0, 'avatar'),
						'groups'							=> array(0, 'groups'),
						'aboutme'							=> array(0, 'aboutme'),
						'location'							=> array(0, 'location'),
						'profilelinks'						=> array(0, 'profilelinks'),
						'favwiki'							=> array(0, 'favwiki'),
						'userstats'							=> array(0, 'userstats'),
];

$messages = array();
$messages['en'] = array(
						'curseprofile'						=> 'Curse Profile',
						'curseprofile_description'			=> 'A modular, multi-featured user profile system.',
						'avataralt'							=> 'Avatar for $1',
						// Friendsthip special pages
						'addfriend'							=> 'Add Friend',
						'confirmfriend'						=> 'Confirm Friend Request',
						'ignorefriend'						=> 'Ignore Friend Request',
						'removefriend'						=> 'Remove Friend',
						// Friendship messages
						'friendrequestsend-prompt'			=> 'Confirm your friend request to $1',
						'friendrequestconfirm-prompt'		=> 'Would you like to confirm the friend request from $1?',
						'friendrequestignore-prompt'		=> 'Would you like to ingore the friend request from  $1?',
						'friendrequestremove-prompt'		=> 'Would you like to erase all friendship between yourself and $1?',
						'friendrequestsend-error'			=> 'There was an error sending your request',
						'friendrequestconfirm-error'		=> 'There was an error confirming the request',
						'friendrequestignore-error'			=> 'There was an error ignoring request',
						'friendrequestremove-error'			=> 'There was an error while trying to remove a friend',
						'friendrequestsend'					=> 'Add Friend',
						'friendrequestcancel'				=> 'Cancel Friend Request',
						'ignorefriend-response'				=> 'Ignore',
						'confirmfriend-response'			=> 'Confirm',
						'alreadyfriends'					=> 'Friends!',
						'friendrequestsent'					=> 'Request Sent',
						'nofriends'							=> 'No friends on this wiki',
						// Profile management
						'emptyactivity'						=> 'This user hasn\'t made any edits on this wiki yet',
						'commentaction'						=> 'Post',
						'editprofile'						=> 'Edit Profile',
						'profileprefselect'					=> 'Page Type',
						'toggletypepref'					=> 'Switch User Page Default',
						'toggletypetooltip'					=> 'Switch between profile and wiki page',
						'aboutme'							=> 'About Me',
						'favoritewiki'						=> 'Favorite Wiki',
						'citylabel'							=> 'City',
						'statelabel'						=> 'State/Province',
						'countrylabel'						=> 'Country',
						'steamlink'							=> 'Steam',
						'xbllink'							=> 'Xbox Live',
						'psnlink'							=> 'Playstation Network',
						'viewreplies'						=> 'View $1 {{PLURAL:$1|reply|replies}}',
						'repliestooltip'					=> 'View replies or add one of your own',
						'replylink'							=> 'Reply',
						// form placeholders
						'commentplaceholder'				=> 'Leave a comment',
						'commentreplyplaceholder'			=> 'Leave a reply',
						'aboutmeplaceholder'				=> 'Write something about yourself!',
						'steamlinkplaceholder'				=> 'a URL to your Steam account',
						'xbllinkplaceholder'				=> 'What\'s your gamertag?',
						'psnlinkplaceholder'				=> 'Share your PSN ID',
						// Stat labels
						'wikisedited'						=> 'Wikis Edited',
						'totalcontribs'						=> 'Contributions',
						'totaledits'						=> 'Edits',
						'totaldeletes'						=> 'Deletes',
						'totalpatrols'						=> 'Patrols',
						'friends'							=> 'Friends',
						// The big deal
						'userprofilelayout'					=> '
<div class="curseprofile" data-userid="$2">
	<div class="leftcolumn">
		<div class="borderless section">
			{{#avatar: 160 | $3 | you | class="mainavatar"}}
			<div class="headline">
				{{#groups: $2}}
				<h1>$1</h1>
			</div>
			<div>
				{{#profilelinks: $2}}
				{{#location: $2}}
			</div>
			<div class="aboutme">
				{{#aboutme: $2}}
			</div>
		</div>
		<div class="activity section">
			<h3>Recent Wiki Activity</h3>
			{{#recentactivity: $2}}
		</div>
		<div class="comments section">
			<h3>Comments</h3>
			{{#comments: $2}}
		</div>
	</div>
	<div class="rightcolumn">
		<div class="rightfloat">
			<div class="score">{{#Points: User:$1 | all | raw}} GP</div>
		</div>
		<div>{{#favwiki: $2}}</div>
		<div class="section">
			<h3>Total Statistics</h3>
			{{#userstats: $2}}
			{{#friendlist: $2}}
		</div>
	</div>
</div>
__NOTOC__
'
);
