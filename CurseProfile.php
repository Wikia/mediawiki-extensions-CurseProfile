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
$wgExtensionCredits['specialpage'][] = [
	'path'				=> __FILE__,
	'name'				=> 'Curse Profile',
	'author'			=> 'Noah Manneschmidt, Curse Inc&copy;',
	'descriptionmsg'	=> 'curseprofile_description',
	'version'			=> '1.5' //Must be a string or Mediawiki will turn it into an integer.
];


define('NS_USER_WIKI', 200);
define('NS_USER_PROFILE', 202);

$wgAvailableRights[] = 'profile-modcomments';
$wgAvailableRights[] = 'profile-purgecomments';

/******************************************/
/* Language Strings, Page Aliases, Hooks  */
/******************************************/
$extDir = __DIR__ . '/';

$wgMessagesDirs['CurseProfile'] = $extDir.'i18n';
$wgExtensionMessagesFiles['CurseProfileAlias']		= "{$extDir}/CurseProfile.alias.php";
$wgExtensionMessagesFiles['CurseProfileMagic']		= "{$extDir}/CurseProfile.i18n.magic.php";
$wgExtensionMessagesFiles['CurseProfileNamespaces']	= "{$extDir}/CurseProfile.namespaces.php";

$wgAutoloadClasses['FlagFinder']                  = $extDir.'classes/FlagFinder.php';
$wgAutoloadClasses['CurseProfile\Hooks']          = $extDir.'CurseProfile.hooks.php';
$wgAutoloadClasses['CurseProfile\CP']             = $extDir.'classes/CP.php';
$wgAutoloadClasses['CurseProfile\ProfileApi']     = $extDir.'classes/ProfileApi.php';
$wgAutoloadClasses['CurseProfile\ProfilePage']    = $extDir.'classes/ProfilePage.php';
$wgAutoloadClasses['CurseProfile\ProfileData']    = $extDir.'classes/ProfileData.php';
$wgAutoloadClasses['CurseProfile\RecentActivity'] = $extDir.'classes/RecentActivity.php';
$wgAutoloadClasses['CurseProfile\Friendship']     = $extDir.'classes/Friendship.php';
$wgAutoloadClasses['CurseProfile\FriendDisplay']  = $extDir.'classes/FriendDisplay.php';
$wgAutoloadClasses['CurseProfile\FriendApi']      = $extDir.'classes/FriendApi.php';
$wgAutoloadClasses['CurseProfile\CommentApi']     = $extDir.'classes/CommentApi.php';
$wgAutoloadClasses['CurseProfile\CommentBoard']   = $extDir.'classes/CommentBoard.php';
$wgAutoloadClasses['CurseProfile\CommentReport']  = $extDir.'classes/CommentReport.php';
$wgAutoloadClasses['CurseProfile\CommentDisplay'] = $extDir.'classes/CommentDisplay.php';
$wgAutoloadClasses['CurseProfile\FriendSync']     = $extDir.'classes/jobs/FriendSync.php';
$wgAutoloadClasses['CurseProfile\ResolveComment'] 						= $extDir.'classes/jobs/ResolveComment.php';
$wgAutoloadClasses['CurseProfile\MWEcho\NotificationFormatter']			= $extDir.'classes/echo/NotificationFormatter.php';
$wgAutoloadClasses['CurseProfile\MWEcho\CommentPresentationModel']		= $extDir.'classes/echo/CommentPresentationModel.php';
$wgAutoloadClasses['CurseProfile\MWEcho\FriendshipPresentationModel']	= $extDir.'classes/echo/FriendshipPresentationModel.php';
$wgAutoloadClasses['CurseProfile\ResourceLoaderModule']					= $extDir.'classes/ResourceLoaderModule.php';
$wgAutoloadClasses['CurseProfile\CommentLogFormatter']					= $extDir.'classes/CommentLogFormatter.php';

$wgAutoloadClasses['TemplateCommentBoard']			= "{$extDir}/templates/TemplateCommentBoard.php";
$wgAutoloadClasses['TemplateCommentModeration']		= "{$extDir}/templates/TemplateCommentModeration.php";
$wgAutoloadClasses['TemplateManageFriends']			= "{$extDir}/templates/TemplateManageFriends.php";

//Special Pages
$wgAutoloadClasses['CurseProfile\SpecialManageFriends']		= "{$extDir}/specials/friends/SpecialManageFriends.php";
$wgSpecialPages['ManageFriends']							= 'CurseProfile\SpecialManageFriends';

$wgAutoloadClasses['CurseProfile\SpecialFriends']			= "{$extDir}/specials/friends/SpecialFriends.php";
$wgSpecialPages['Friends']									= 'CurseProfile\SpecialFriends';

$wgAutoloadClasses['CurseProfile\SpecialAddComment']		= "{$extDir}/specials/comments/SpecialAddComment.php";
$wgSpecialPages['AddComment']								= 'CurseProfile\SpecialAddComment';

$wgAutoloadClasses['CurseProfile\SpecialCommentBoard']		= "{$extDir}/specials/comments/SpecialCommentBoard.php";
$wgSpecialPages['CommentBoard']								= 'CurseProfile\SpecialCommentBoard';

$wgAutoloadClasses['CurseProfile\SpecialCommentPermalink']	= "{$extDir}/specials/comments/SpecialCommentPermalink.php";
$wgSpecialPages['CommentPermalink']							= 'CurseProfile\SpecialCommentPermalink';

$wgAutoloadClasses['CurseProfile\SpecialCommentModeration']	= "{$extDir}/specials/comments/SpecialCommentModeration.php";
$wgSpecialPages['CommentModeration']						= 'CurseProfile\SpecialCommentModeration';

$wgAutoloadClasses['CurseProfile\SpecialWikiImageRedirect']	= "{$extDir}/specials/SpecialWikiImageRedirect.php";
$wgSpecialPages['WikiImageRedirect']						= 'CurseProfile\SpecialWikiImageRedirect';

// Recent Changes Logs
$wgLogTypes['curseprofile']								= 'curseprofile';
$wgLogNames['curseprofile']								= 'curseprofile_log_name';
$wgLogHeaders['curseprofile']							= 'curseprofile_log_description';
$wgLogActionsHandlers['curseprofile/comment-created']	= '\CurseProfile\CommentLogFormatter';
$wgLogActionsHandlers['curseprofile/comment-replied']	= '\CurseProfile\CommentLogFormatter';
$wgLogActionsHandlers['curseprofile/comment-edited']	= '\CurseProfile\CommentLogFormatter';

// Resource modules
$wgResourceModules['ext.curseprofile.profilepage'] = [
	'styles' => ['css/curseprofile.css'],
	'scripts' => ['js/curseprofile.js'],
	'localBasePath' => $extDir,
	'remoteExtPath' => 'CurseProfile',
	'dependencies' => ['ext.curseprofile.customskin', 'ext.curseprofile.comments', 'jquery.autosize', 'mediawiki.user', 'mediawiki.api'],
	'position' => 'top',
	'messages' => [
		'purgeaboutme-prompt',
		'save',
		'cancel',
	]
];

$wgResourceModules['ext.curseprofile.comments'] = [
	'styles' => ['css/comments.css'],
	'scripts' => ['js/comments.js'],
	'localBasePath' => $extDir,
	'remoteExtPath' => 'CurseProfile',
	'dependencies' => ['jquery.timeago', 'jquery.autosize', 'mediawiki.user', 'mediawiki.api', 'ext.curse.font-awesome'],
	'position' => 'top',
	'messages' => [
		'save',
		'cancel',
		'remove-prompt',
		'purge-prompt',
		'report-prompt',
		'report-thanks',
	],
];

$wgResourceModules['ext.curseprofile.commentmoderation'] = [
	'styles' => ['css/commentmoderation.less'],
	'scripts' => ['js/commentmoderation.js'],
	'localBasePath' => $extDir,
	'remoteExtPath' => 'CurseProfile',
	'dependencies' => ['ext.curseprofile.comments'],
	'position' => 'top',
	'messages' => [
		'report-confirmdismiss',
		'report-confirmdelete',
	],
];

//Needs to load early, so it has an 'a' prefix.
$wgResourceModules['a.ext.curseprofile.profilepage.mobile'] = [
	'targets' => ['mobile'],
	'group' => 'site',
	'styles'			=> [
		'css/curseprofile.mobile.css',
		'css/comments.mobile.css',
	],
	'scripts' => ['js/comments.js'],
	'dependencies'		=> ['jquery.timeago', 'jquery.autosize'],
	'remoteBasePath'	=> 'CurseProfile',
	'localBasePath'		=> $extDir,
	'position'			=> 'top'
];

$wgResourceModules['jquery.timeago'] = [
	'targets'	=> ['desktop', 'mobile'],
	'scripts' => ['js/jquery.timeago.js'],
	'localBasePath' => $extDir,
	'remoteExtPath' => 'CurseProfile',
];

$wgResourceModules['jquery.autosize'] = [
	'targets'	=> ['desktop', 'mobile'],
	'scripts' => ['js/jquery.autosize.min.js'],
	'localBasePath' => $extDir,
	'remoteExtPath' => 'CurseProfile',
];

$wgResourceModules['ext.curseprofile.customskin'] = [
	'class' => 'CurseProfile\ResourceLoaderModule',
]; //Allows sites to customize by editing MediaWiki:CurseProfile.css

//Hooks
$wgHooks['BeforeInitialize'][]				= 'CurseProfile\Hooks::onBeforeInitialize';
$wgHooks['TestCanonicalRedirect'][]			= 'CurseProfile\Hooks::onTestCanonicalRedirect';
$wgHooks['LinkBegin'][]						= 'CurseProfile\Hooks::onLinkBegin';
$wgHooks['AbortEmailNotification'][]		= 'CurseProfile\Hooks::onAbortEmailNotification';
$wgHooks['ArticleFromTitle'][]				= 'CurseProfile\Hooks::onArticleFromTitle';
$wgHooks['ArticleUpdateBeforeRedirect'][]	= 'CurseProfile\Hooks::onArticleUpdateBeforeRedirect';
$wgHooks['ParserFirstCallInit'][]			= 'CurseProfile\Hooks::onParserFirstCall';
$wgHooks['LoadExtensionSchemaUpdates'][]	= 'CurseProfile\Hooks::onLoadExtensionSchemaUpdates';
$wgHooks['UnitTestsList'][]					= 'CurseProfile\Hooks::onUnitTestsList';
$wgHooks['SkinTemplateNavigation'][]		= 'CurseProfile\Hooks::onSkinTemplateNavigation';
$wgHooks['CanonicalNamespaces'][]			= 'CurseProfile\Hooks::onCanonicalNamespaces';
$wgHooks['GetPreferences'][]				= 'CurseProfile\Hooks::onGetPreferences';
$wgHooks['UserGetDefaultOptions'][]			= 'CurseProfile\Hooks::onUserGetDefaultOptions';
$wgHooks['UserSaveOptions'][]				= 'CurseProfile\Hooks::onUserSaveOptions';
$wgHooks['BeforeCreateEchoEvent'][]			= 'CurseProfile\Hooks::onBeforeCreateEchoEvent';
$wgHooks['EchoGetDefaultNotifiedUsers'][]	= 'CurseProfile\Hooks::onEchoGetDefaultNotifiedUsers';
$wgHooks['SkinMinervaDefaultModules'][]		= 'CurseProfile\Hooks::onSkinMinervaDefaultModules';

//Ajax Setup
require_once('CurseProfile.ajax.php');

$wgCPEditsToComment = 1;
