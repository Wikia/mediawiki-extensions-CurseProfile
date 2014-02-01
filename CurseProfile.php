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

// Global profile namespace reference
if (!defined('NS_USER_PROFILE')) {
	define( 'NS_USER_PROFILE', 202 );
}
if (!defined('NS_USER_WIKI')) {
	define( 'NS_USER_WIKI', 200 );
}

/******************************************/
/* Language Strings, Page Aliases, Hooks  */
/******************************************/
$extDir = __DIR__ . '/';

$wgExtensionMessagesFiles['CurseProfile'] = "{$extDir}/CurseProfile.i18n.php";

$wgAutoloadClasses['CurseProfile\Hooks']          = $extDir . 'CurseProfile.hooks.php';
$wgAutoloadClasses['CurseProfile\CP']             = $extDir . 'classes/CP.php';
$wgAutoloadClasses['CurseProfile\ProfilePage']    = $extDir . 'classes/ProfilePage.php';
$wgAutoloadClasses['CurseProfile\ProfileData']    = $extDir . 'classes/ProfileData.php';
$wgAutoloadClasses['CurseProfile\RecentActivity'] = $extDir . 'classes/RecentActivity.php';
$wgAutoloadClasses['CurseProfile\Friendship']     = $extDir . 'classes/Friendship.php';
$wgAutoloadClasses['CurseProfile\FriendDisplay']  = $extDir . 'classes/FriendDisplay.php';
$wgAutoloadClasses['CurseProfile\FriendSync']     = $extDir . 'classes/FriendSync.php';
$wgAutoloadClasses['CurseProfile\CommentBoard']   = $extDir . 'classes/CommentBoard.php';
$wgAutoloadClasses['CurseProfile\CommentDisplay'] = $extDir . 'classes/CommentDisplay.php';
$wgAutoloadClasses['CurseProfile\ResourceLoaderModule'] = $extDir . 'classes/ResourceLoaderModule.php';
$wgAutoloadClasses['CurseProfile\SpecialConfirmAction'] = $extDir . 'specials/SpecialConfirmAction.php';

// Special Pages

$wgAutoloadClasses['CurseProfile\SpecialAddFriend']			= "{$extDir}/specials/friends/SpecialAddFriend.php";
$wgSpecialPages['AddFriend']								= 'CurseProfile\SpecialAddFriend';
$wgSpecialPageGroups['AddFriend']							= 'users';

$wgAutoloadClasses['CurseProfile\SpecialConfirmFriend']		= "{$extDir}/specials/friends/SpecialConfirmFriend.php";
$wgSpecialPages['ConfirmFriend']							= 'CurseProfile\SpecialConfirmFriend';
$wgSpecialPageGroups['ConfirmFriend']						= 'users';

$wgAutoloadClasses['CurseProfile\SpecialIgnoreFriend']		= "{$extDir}/specials/friends/SpecialIgnoreFriend.php";
$wgSpecialPages['IgnoreFriend']								= 'CurseProfile\SpecialIgnoreFriend';
$wgSpecialPageGroups['IgnoreFriend']						= 'users';

$wgAutoloadClasses['CurseProfile\SpecialRemoveFriend']		= "{$extDir}/specials/friends/SpecialRemoveFriend.php";
$wgSpecialPages['RemoveFriend']								= 'CurseProfile\SpecialRemoveFriend';
$wgSpecialPageGroups['RemoveFriend']						= 'users';

$wgAutoloadClasses['CurseProfile\SpecialAddComment']		= "{$extDir}/specials/comments/SpecialAddComment.php";
$wgSpecialPages['AddComment']								= 'CurseProfile\SpecialAddComment';
$wgSpecialPageGroups['AddComment']							= 'users';

$wgAutoloadClasses['CurseProfile\SpecialEditProfile']		= "{$extDir}/specials/SpecialEditProfile.php";
$wgSpecialPages['EditProfile']								= 'CurseProfile\SpecialEditProfile';
$wgSpecialPageGroups['EditProfile']							= 'users';

$wgAutoloadClasses['CurseProfile\SpecialToggleProfilePreference'] = "{$extDir}/specials/SpecialToggleProfilePreference.php";
$wgSpecialPages['ToggleProfilePreference']					= 'CurseProfile\SpecialToggleProfilePreference';
$wgSpecialPageGroups['ToggleProfilePreference']				= 'users';

// Resource modules
$wgResourceModules['ext.curseprofile.profilepage'] = [
	'styles' => ['css/curseprofile.css'],
	'scripts' => ['js/jquery.timeago.js', 'js/curseprofile.js'],
	'localBasePath' => $extDir,
	'remoteExtPath' => 'CurseProfile',
	'dependencies' => 'ext.curseprofile.customskin', // allows sites to customize by editing MediaWiki:CurseProfile.css
];
$wgResourceModules['ext.curseprofile.forms'] = [
	'styles' => ['css/curseprofile_forms.css'],
	'localBasePath' => $extDir,
	'remoteExtPath' => 'CurseProfile',
];
$wgResourceModules['ext.curseprofile.customskin'] = [
	'class' => 'CurseProfile\ResourceLoaderModule',
];

// Hooks
$wgHooks['ArticleFromTitle'][]				= 'CurseProfile\Hooks::onArticleFromTitle';
$wgHooks['ParserFirstCallInit'][]			= 'CurseProfile\Hooks::onParserFirstCall';
$wgHooks['LoadExtensionSchemaUpdates'][]	= 'CurseProfile\Hooks::onLoadExtensionSchemaUpdates';
$wgHooks['SkinTemplateNavigation'][]		= 'CurseProfile\Hooks::onSkinTemplateNavigation';

// Ajax Setup
require_once('CurseProfile.ajax.php');
