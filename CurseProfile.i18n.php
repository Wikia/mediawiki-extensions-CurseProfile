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
						'editorfriends'						=> array(0, 'editorfriends'),
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
						'managefriends'						=> 'Manage Friends',
						'friends'							=> 'Friends',
						'pendingrequests'					=> 'Pending Requests',
						'sentrequests'						=> 'Sent Requests',
						'exception-nologinreturn-text'		=> 'Please [{{fullurl:Special:UserLogin|returnto=$1}} log in] to be able to access this page or action.',

						// Friendship messages
						'friendrequestsend-prompt'			=> 'Confirm your friend request to $1',
						'friendrequestconfirm-prompt'		=> 'Would you like to confirm the friend request from $1?',
						'friendrequestignore-prompt'		=> 'Would you like to ingore the friend request from  $1?',
						'friendrequestremove-prompt'		=> 'Would you like to remove $1 from your friends?',
						'friendrequestsend-error'			=> 'There was an error sending your request',
						'friendrequestconfirm-error'		=> 'There was an error confirming the request',
						'friendrequestignore-error'			=> 'There was an error ignoring request',
						'friendrequestremove-error'			=> 'There was an error while trying to remove a friend',
						'friendrequestsend'					=> 'Add Friend',
						'friendrequestcancel'				=> 'Cancel Friend Request',
						'ignorefriend-response'				=> 'Ignore',
						'confirmfriend-response'			=> 'Confirm',
						'removefriend-response'				=> 'Remove',
						'alreadyfriends'					=> 'Friends!',
						'friendrequestsent'					=> 'Request Sent',
						'nofriends'							=> 'No friends on this wiki',
						'soronery'							=> 'You have no friends :(',
						'friendship-invalidaction'			=> 'An invalid action was attempted',

						// Emails
						'commentemailpref'					=> 'Send me an email when a user comments on my profile',
						'commentemail-subj'					=> '$1 has left a comment on your profile on {{SITENAME}}!',
						'commentemail-body'					=> '
Hi $1,
$2 has left a comment on your profile at {{SITENAME}}. You can read and reply by viewing your profile:

$3

Thanks,
Your friendly {{SITENAME}} notification system

You can unsubscribe by changing your email preferences at:
$4
',
						'friendreqemailpref'				=> 'Send me an email when a user sends me a friend request',
						'friendreqemail-subj'				=> '$1 has added you as a friend on Gamepedia!',
						'friendreqemail-body'				=> '
Hi $1,
$2 has added you as a friend on Gamepedia. You can confirm their request by visiting their profile on {{SITENAME}} $3 or by visiting the friend management page:

$5

Thanks,
Your friendly {{SITENAME}} notification system

You can unsubscribe by changing your email preferences at:
$4
',

						// Profile management
						'emptyactivity'						=> 'This user hasn\'t made any edits on this wiki yet',
						'commentaction'						=> 'Post',
						'cp-editprofile'					=> 'Edit Profile',
						'profileprefselect'					=> 'Page Type',
						'toggletypepref'					=> 'Switch User Page Default',
						'userwikitab'						=> 'User wiki',
						'userprofiletab'					=> 'User profile',
						'tooltip-ca-switch_type'			=> 'Switch between profile and wiki page',
						'aboutme'							=> 'About Me',
						'aboutmehelp'						=> 'You may use wikitext for formatting.',
						'favoritewiki'						=> 'Favorite Wiki',
						'citylabel'							=> 'City',
						'statelabel'						=> 'State/Province',
						'countrylabel'						=> 'Country',
						'twitterlink'						=> 'Twitter',
						'facebooklink'						=> 'Facebook',
						'googlelink'						=> 'Google+',
						'redditlink'						=> 'Reddit',
						'steamlink'							=> 'Steam',
						'xbllink'							=> 'Xbox Live',
						'psnlink'							=> 'Playstation Network',
						'viewearlierreplies'				=> 'Load $1 more {{PLURAL:$1|reply|replies}}',
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
						'steamlinkplaceholder'				=> 'http://steamcommunity.com/id/example',
						'twitterlinkplaceholder'			=> 'https://twitter.com/exampmle',
						'redditlinkplaceholder'				=> 'http://www.reddit.com/user/example',
						'fblinkplaceholder'					=> 'https://www.facebook.com/example',
						'googlelinkplaceholder'				=> 'https://plus.google.com/+example/posts',
						'xbllinkplaceholder'				=> 'Share your gamertag',
						'psnlinkplaceholder'				=> 'Share your PSN ID',

						// Stat labels
						'wikisedited'						=> 'Wikis Edited',
						'localrank'							=> '{{SITENAME}} Rank',
						'globalrank'						=> 'Gamepedia Rank<br>([[Special:WikiPoints|Leaderboard]])',
						'totalcontribs'						=> 'Contributions',
						'totaledits'						=> 'Edits',
						'totaldeletes'						=> 'Deletes',
						'totalpatrols'						=> 'Patrols',
						'totalfriends'						=> 'Friends<br>([[Special:Friends/$1|{{#ifeq: $1 | $2 | Manage Friends | View All }}]])',

						// Comment board, Friends list
						'commentboard-empty'				=> 'There are no comments on this user\'s board.',
						'commentboard-invalid'				=> 'This is no such page.',
						'commentboard-title'				=> 'Comment Board: $1',
						'friendsboard-title'				=> 'Friends of $1',

						// Basic profile text
						'cp-recentactivitysection'			=> 'Recent Wiki Activity',
						'cp-recentcommentssection'			=> 'Recent Comments',
						'commentarchivelink'				=> 'Comment Archive',
						'cp-statisticssection'				=> 'Total Statistics',
);
