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
						'senddirectrequest'					=> 'Send New Friend Request',
						'sendrequest'						=> 'Send Request',
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
						'friendrequest-direct-success'		=> 'Your friend has been sent',
						'friendrequest-direct-notfound'		=> 'A user by that name could not be found',
						'friendrequest-direct-unmerged'		=> 'This user cannot be added as a friend because they have not merged their Curse account',
						'friendrequestsend'					=> 'Add Friend',
						'friendrequestcancel'				=> 'Cancel Friend Request',
						'ignorefriend-response'				=> 'Ignore',
						'confirmfriend-response'			=> 'Confirm',
						'removefriend-response'				=> 'Remove',
						'alreadyfriends'					=> 'Friends!',
						'friendrequestsent'					=> 'Request Sent',
						'nofriends'							=> 'No friends on this wiki',
						'soronery'							=> 'You have no friends :(',
						'friendship-invalidaction'			=> 'An invalid action was attempted.',
						'comment-invalidaction'				=> 'An invalid action was attempted.',
						'comment-adminremoved'				=> 'This comment has been removed.',

						// Profile management
						'emptyactivity'						=> 'This user hasn\'t made any edits on this wiki yet',
						'commentaction'						=> 'Post',
						'cp-editprofile'					=> 'Edit Profile',
						'profileprefselect'					=> 'Page Type',
						'userwikitab'						=> 'User wiki',
						'userprofiletab'					=> 'User profile',
						'tooltip-ca-switch_type'			=> 'Switch between profile and wiki page',
						'aboutme'							=> 'About Me',
						'aboutmehelp'						=> 'Wikitext is available for formatting.',
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
						'removelink'						=> '✕',
						'removelink-tooltip'				=> 'Remove this comment',
						'profilepref-profile'				=> 'Use an enhanced Curse Profile user page',
						'profilepref-wiki'					=> 'Use a standard wiki user page',
						'prefs-public'						=> 'Public Profile',
						'prefs-location'					=> 'Location',
						'prefs-profiles'					=> 'Other Profiles',
						'avatar'							=> 'Avatar',
						'avatar-help'						=> 'Gamepedia uses Gravatar to display an avatar based on your email address. Visit [http://www.gravatar.com/ gravatar.com] to change your avatar.',

						// form placeholders
						'commentplaceholder'				=> 'Leave a comment',
						'commentreplyplaceholder'			=> 'Leave a reply',
						'directfriendreqplaceholder'		=> 'Enter a username',
						'aboutmeplaceholder'				=> 'Write something about yourself!',
						'steamlinkplaceholder'				=> 'http://steamcommunity.com/id/example',
						'fblinkplaceholder'					=> 'https://www.facebook.com/example',
						'googlelinkplaceholder'				=> 'https://plus.google.com/+example/posts',
						'profilelink-help'					=> 'The above profile links must exactly match the example format to be displayed.',
						'twitterlinkplaceholder'			=> 'Share your twitter screen name',
						'redditlinkplaceholder'				=> 'Share your reddit user account',
						'xbllinkplaceholder'				=> 'Share your gamertag',
						'psnlinkplaceholder'				=> 'Share your PSN ID',

						// Stat labels
						'wikisedited'						=> 'Wikis Edited',
						'localrank'							=> '{{SITENAME}} Rank<br>([[Special:WikiPoints|Leaderboard]])',
						'globalrank'						=> 'Gamepedia Rank',
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
						'profileactivity-created'			=> 'Created',
						'profileactivity-edited'			=> 'Edited',

						// Echo Notification messages
						'echo-pref-subscription-profile-comment' => 'Leaves a comment on my profile',
						'echo-dismiss-title-profile-comment'=> 'profile comment',
						'notification-profile-comment'		=> '[[{{ns:User}}:$1]] has left a comment on [[{{ns:User}}:$2|your profile]].',
						'notification-profile-comment-email-subj' => '$1 has left a comment on your Gamepedia profile!',
						'notification-profile-comment-email-body' => 'Hi $2,
$1 has left a comment on your profile on {{SITENAME}}. You can read and reply by viewing your profile:

{{canonicalurl:{{ns:User}}:$2}}

$3',

						'echo-pref-subscription-friendship' => 'Sends me a friend request',
						'echo-dismiss-title-friendship'		=> 'friendship request',
						'notification-friendship-request'	=> '[[User:$1]] has added you as a friend.',
						'notification-friendship-request-email-subj' => '$1 has added you as a friend on Gamepedia!',
						'notification-friendship-request-email-body' => 'Hi $2,
$1 has added you as a friend on Gamepedia. You can confirm their request by visiting their profile on {{SITENAME}} {{canonicalurl:{{ns:User}}:$1}} or by visiting the friend management page:

{{canonicalurl:{{#special:ManageFriends}}}}

$3',
						'emailfooter-gamepedia' => 'Thanks,
Your friendly Gamepedia notification system

You can unsubscribe by changing your email preferences at:
{{canonicalurl:{{#special:Preferences}}#mw-prefsection-echo}}',
);
