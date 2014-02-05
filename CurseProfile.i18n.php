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
						'userlevel'							=> array(0, 'userlevel'),
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
						// Friendship email
						'commentemailpref'					=> 'Send me an email when a user comments on my profile',
						'commentemail-subj'					=> '$1 has left a comment on your profile on {{SITENAME}}!',
						'commentemail-body'					=> '
Hi $1,
$2 has left a comment on your profile at {{SITENAME}}. You can read and reply by viewing your profile:

$3

Thanks,
The Gamepedia Team

(You can unsubscribe by changing your email preferences at $4)',
						'friendreqemailpref'				=> 'Send me an email when a user sends me a friend request',
						'friendreqemail-subj'				=> '$1 has added you as a friend on Gamepedia!',
						'friendreqemail-body'				=> '
Hi $1,
$2 has added you as a friend on Gamepedia. You can confirm their request by visiting their profile on {{SITENAME}}:

$3

Thanks,
The Gamepedia Team

(You can unsubscribe by changing your email preferences at $4)',
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
						'profilepref-profile'				=> 'Use an enhanced profile page',
						'profilepref-wiki'					=> 'Use a simple wiki page',
						'prefs-public'						=> 'Public Profile',
						'prefs-location'					=> 'Location',
						'prefs-profiles'					=> 'Other Profiles',
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
);
