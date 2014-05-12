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

/******************************************/
/* Credits                                */
/******************************************/
$wgExtensionCredits['specialpage'][] = array(
												'path'				=> __FILE__,
												'name'				=> 'Curse Profile',
												'author'			=> 'Noah Manneschmidt, Curse Inc&copy;',
												'descriptionmsg'	=> 'curseprofile_description',
												'version'			=> '1.0' //Must be a string or Mediawiki will turn it into an integer.
											);


define('NS_USER_WIKI', 200 );
define('NS_USER_PROFILE', 202 );

$wgAvailableRights[] = 'userlevel-view';
$wgAvailableRights[] = 'profile-modcomments';

/******************************************/
/* Language Strings, Page Aliases, Hooks  */
/******************************************/
$extDir = __DIR__ . '/';

$wgExtensionMessagesFiles['CurseProfile']			= "{$extDir}/CurseProfile.i18n.php";
$wgExtensionMessagesFiles['CurseProfileNamespaces']	= "{$extDir}/CurseProfile.namespaces.php";

$wgAutoloadClasses['FlagFinder']                  = $extDir . 'classes/FlagFinder.php';
$wgAutoloadClasses['CurseProfile\Hooks']          = $extDir . 'CurseProfile.hooks.php';
$wgAutoloadClasses['CurseProfile\CP']             = $extDir . 'classes/CP.php';
$wgAutoloadClasses['CurseProfile\ProfilePage']    = $extDir . 'classes/ProfilePage.php';
$wgAutoloadClasses['CurseProfile\ProfileData']    = $extDir . 'classes/ProfileData.php';
$wgAutoloadClasses['CurseProfile\RecentActivity'] = $extDir . 'classes/RecentActivity.php';
$wgAutoloadClasses['CurseProfile\Friendship']     = $extDir . 'classes/Friendship.php';
$wgAutoloadClasses['CurseProfile\FriendDisplay']  = $extDir . 'classes/FriendDisplay.php';
$wgAutoloadClasses['CurseProfile\FriendSync']     = $extDir . 'classes/FriendSync.php';
$wgAutoloadClasses['CurseProfile\FriendApi']      = $extDir . 'classes/FriendApi.php';
$wgAutoloadClasses['CurseProfile\CommentApi']     = $extDir . 'classes/CommentApi.php';
$wgAutoloadClasses['CurseProfile\CommentBoard']   = $extDir . 'classes/CommentBoard.php';
$wgAutoloadClasses['CurseProfile\CommentDisplay'] = $extDir . 'classes/CommentDisplay.php';
$wgAutoloadClasses['CurseProfile\NotificationFormatter'] = $extDir . 'classes/NotificationFormatter.php';
$wgAutoloadClasses['CurseProfile\ResourceLoaderModule'] = $extDir . 'classes/ResourceLoaderModule.php';

// Special Pages

$wgAutoloadClasses['CurseProfile\SpecialManageFriends']		= "{$extDir}/specials/friends/SpecialManageFriends.php";
$wgSpecialPages['ManageFriends']							= 'CurseProfile\SpecialManageFriends';
$wgSpecialPageGroups['ManageFriends']						= 'users';

$wgAutoloadClasses['CurseProfile\SpecialFriends']			= "{$extDir}/specials/friends/SpecialFriends.php";
$wgSpecialPages['Friends']									= 'CurseProfile\SpecialFriends';
$wgSpecialPageGroups['Friends']								= 'users';

$wgAutoloadClasses['CurseProfile\SpecialAddComment']		= "{$extDir}/specials/comments/SpecialAddComment.php";
$wgSpecialPages['AddComment']								= 'CurseProfile\SpecialAddComment';
$wgSpecialPageGroups['AddComment']							= 'users';

$wgAutoloadClasses['CurseProfile\SpecialCommentBoard']		= "{$extDir}/specials/comments/SpecialCommentBoard.php";
$wgSpecialPages['CommentBoard']								= 'CurseProfile\SpecialCommentBoard';
$wgSpecialPageGroups['CommentBoard']						= 'users';

// Resource modules
$wgResourceModules['ext.curseprofile.profilepage'] = [
	'styles' => ['css/curseprofile.css'],
	'scripts' => ['js/curseprofile.js'],
	'localBasePath' => $extDir,
	'remoteExtPath' => 'CurseProfile',
	'dependencies' => ['ext.curseprofile.customskin', 'ext.curse.pagination', 'mediawiki.user', 'mediawiki.api', 'jquery.timeago'],
];
$wgResourceModules['jquery.timeago'] = [
	'scripts' => ['js/jquery.timeago.js'],
	'localBasePath' => $extDir,
	'remoteExtPath' => 'CurseProfile',
];
$wgResourceModules['ext.curseprofile.customskin'] = [
	'class' => 'CurseProfile\ResourceLoaderModule',
]; // allows sites to customize by editing MediaWiki:CurseProfile.css

// Hooks
$wgHooks['BeforeInitialize'][]				= 'CurseProfile\Hooks::onBeforeInitialize';
$wgHooks['TestCanonicalRedirect'][]			= 'CurseProfile\Hooks::onTestCanonicalRedirect';
$wgHooks['ArticleFromTitle'][]				= 'CurseProfile\Hooks::onArticleFromTitle';
$wgHooks['ParserFirstCallInit'][]			= 'CurseProfile\Hooks::onParserFirstCall';
$wgHooks['LoadExtensionSchemaUpdates'][]	= 'CurseProfile\Hooks::onLoadExtensionSchemaUpdates';
$wgHooks['SkinTemplateNavigation'][]		= 'CurseProfile\Hooks::onSkinTemplateNavigation';
$wgHooks['CanonicalNamespaces'][]			= 'CurseProfile\Hooks::onCanonicalNamespaces';
$wgHooks['GetPreferences'][]				= 'CurseProfile\Hooks::onGetPreferences';
$wgHooks['UserGetDefaultOptions'][]			= 'CurseProfile\Hooks::onUserGetDefaultOptions';
$wgHooks['UserSaveOptions'][]				= 'CurseProfile\Hooks::onUserSaveOptions';
$wgHooks['BeforeCreateEchoEvent'][]			= 'CurseProfile\Hooks::onBeforeCreateEchoEvent';
$wgHooks['EchoGetDefaultNotifiedUsers'][]	= 'CurseProfile\Hooks::onEchoGetDefaultNotifiedUsers';

// Ajax Setup
require_once('CurseProfile.ajax.php');
