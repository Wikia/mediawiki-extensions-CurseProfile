<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @package   CurseProfile
 * @author    Noah Manneschmidt
 * @copyright (c) 2013 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki
**/

namespace CurseProfile;

use DynamicSettings\Environment;
use EchoAttributeManager;
use EditPage;
use Linker;
use MWNamespace;
use SpecialPage;
use Status;
use Title;
use User;
use WebRequest;

class Hooks {
	/**
	 * Reference to ProfilePage object
	 *
	 * @access private
	 * @var    object	CurseProfile\ProfilePage
	 */
	static private $profilePage = false;

	/**
	 * Reference to the title originally parsed from this request.
	 *
	 * @access private
	 * @var    object	Title
	 */
	static private $title = null;

	/**
	 * Setup extra namespaces during MediaWiki setup process.
	 *
	 * @access public
	 * @return boolean	True
	 */
	public static function onRegistration() {
		global $wgEchoNotificationIcons, $wgExtraNamespaces;

		if (!defined('NS_USER_PROFILE')) {
			define('NS_USER_PROFILE', 202);
		}
		$wgExtraNamespaces[NS_USER_PROFILE] = 'UserProfile';

		$wgEchoNotificationIcons['gratitude'] = [
			'path' => "CurseProfile/img/notifications/Gratitude.png"
		];
		return true;
	}

	/**
	 * Set parser hooks for the profile pages.
	 *
	 * @access public
	 * @param  Parser &$parser
	 * @return boolean	True
	 */
	public static function onParserFirstCall(&$parser) {
		// must check to see if profile page exists because sometimes the parser is used to parse messages
		// for a response to an API call that doesn't ever fully initialize the MW engine, thus never touching
		// onBeforeInitialize and not setting self::$profilePage
		if (self::$profilePage && self::$profilePage->isProfilePage()) {
			$parser->setFunctionHook('achievements',		[self::$profilePage, 'recentAchievements']);
			$parser->setFunctionHook('editorfriends',		[self::$profilePage, 'editOrFriends']);
			$parser->setFunctionHook('favwiki',				[self::$profilePage, 'favoriteWiki']);
			$parser->setFunctionHook('groups',				[self::$profilePage, 'groupList']);
			$parser->setFunctionHook('profilefield',		[self::$profilePage, 'fieldBlock']);
			$parser->setFunctionHook('profilelinks',		[self::$profilePage, 'profileLinks']);
			$parser->setFunctionHook('userlevel',			[self::$profilePage, 'userLevel']);
			$parser->setFunctionHook('avatar',				'CurseProfile\ProfilePage::userAvatar');
			$parser->setFunctionHook('comments',			'CurseProfile\CommentDisplay::comments');
			$parser->setFunctionHook('friendadd',			'CurseProfile\FriendDisplay::addFriendLink');
			$parser->setFunctionHook('friendcount',			'CurseProfile\FriendDisplay::count');
			$parser->setFunctionHook('friendlist',			'CurseProfile\FriendDisplay::friendList');
			$parser->setFunctionHook('recentactivity',		'CurseProfile\RecentActivity::parserHook');
		}
		return true;
	}

	/**
	 * Handle setting up profile page handlers.
	 *
	 * @param  Title   &$title
	 * @param  Article &$article
	 * @param  object  &$output
	 * @param  User    &$user
	 * @param  object  $request
	 * @param  object  $mediaWiki
	 * @return void
	 */
	public static function onBeforeInitialize(&$title, &$article, &$output, &$user, $request, $mediaWiki) {
		self::$title = $title;
		self::$profilePage = ProfilePage::newFromTitle($title);

		if ($title->equals(SpecialPage::getTitleFor("Preferences"))) {
			$output->addModuleStyles(['ext.curseprofile.preferences.styles']);
			$output->addModules(['ext.curseprofile.preferences.scripts']);
		}
	}

	/**
	 * Reset Title and ProfilePage context if a hard internal redirect is done by MediaWiki.
	 *
	 * @access public
	 * @param  object $destArticle Destination Article
	 * @return void
	 */
	public static function onArticleViewRedirect($destArticle) {
		if (self::$title !== null && !self::$title->equals($destArticle->getTitle())) {
			// Reset profile context.
			self::$title = $destArticle->getTitle();
			self::$profilePage = ProfilePage::newFromTitle(self::$title);
			self::onArticleFromTitle(self::$title, $destArticle);
		}
	}

	/**
	 * Hide NS_USER_PROFILE from Special:WantedPages.
	 *
	 * @access public
	 * @param  mixed &$wantedPages
	 * @param  mixed &$query
	 * @return boolean	True
	 */
	public static function onWantedPagesGetQueryInfo(&$wantedPages, &$query) {
		if (isset($query['conds'])) {
			$db = wfGetDB(DB_REPLICA);
			foreach ($query['conds'] as $index => $condition) {
				if (strpos($condition, 'pl_namespace NOT IN') === 0) {
					$query['conds'][$index] = 'pl_namespace NOT IN(' . $db->makeList([NS_USER, NS_USER_TALK, NS_USER_PROFILE]) . ')';
				}
			}
		}
		return true;
	}

	/**
	 * Make links to user pages known (not red) when that user opts for a profile page
	 *
	 * @param  mixed $dummy
	 * @param  mixed $target
	 * @param  mixed &$html
	 * @param  mixed &$customAttribs
	 * @param  mixed &$query
	 * @param  mixed &$options
	 * @param  mixed &$ret
	 * @return void
	 */
	public static function onLinkBegin($dummy, $target, &$html, &$customAttribs, &$query, &$options, &$ret) {
		// only process user namespace links
		if (!in_array($target->getNamespace(), [NS_USER, NS_USER_PROFILE]) || $target->isSubpage()) {
			return true;
		}

		// remove existing broken option
		$options = array_diff($options, ['broken']);
		// force link to be known
		$options[] = 'known';

		return true;
	}

	/**
	 * Execute actions when ArticleFromTitle is called and add resource loader modules.
	 *
	 * @param  Title   &$title
	 * @param  Article &$article
	 * @return bool
	 */
	public static function onArticleFromTitle(Title &$title, &$article) {
		if (!self::$profilePage) {
			return true;
		}

		if ($title->getNamespace() == NS_USER_PROFILE) {
			return self::renderProfile($title, $article);
		}

		return self::renderUserPages($title);
	}

	/**
	 * handle output of the profile page
	 *
	 * @param  Title   $title
	 * @param  Article $article
	 * @return bool
	 */
	private static function renderProfile(&$title, &$article) {
		global $wgOut;

		if (!self::$profilePage->isActionView() || strpos($title->getText(), '/') !== false) {
			return $wgOut->redirect(self::$profilePage->getUserProfileTitle()->getFullURL());
		}

		$wgOut->addModuleStyles(['ext.curseprofile.profilepage.styles']);
		$wgOut->addModules(['ext.curseprofile.profilepage.scripts']);

		if (!self::$profilePage->getUser()->getId()) {
			$article = new NoProfilePage($title);
			return false;
		}

		$article = self::$profilePage;

		return true;
	}

	/**
	 * Get the correct preference based on namespace
	 *
	 * @param  Title $title
	 * @return array
	 */
	private static function getProfilePreference(&$title) {
		if ($title->getNamespace() == NS_USER) {
			$preferProfile = self::$profilePage->isProfilePreferred();
			$key = 'cp-user-prefers-profile-user';
		}

		if ($title->getNamespace() == NS_USER_TALK) {
			$preferProfile = self::$profilePage->isCommentsPreferred();
			$key = 'cp-user-prefers-profile-talk';
		}

		return [$preferProfile, $key];
	}

	/**
	 * handle the user and talk page
	 *
	 * @param  Title $title
	 * @return bool
	 */
	private static function renderUserPages(&$title) {
		global $wgRequest, $wgOut;

		// Check if we are on a base page
		$username = self::resolveUsername($title);
		$basepage = ($title->getText() == $username);

		// Make sure we are in the right namespace
		if (!in_array($title->getNamespace(), [NS_USER, NS_USER_TALK]) || !$basepage) {
			return true;
		}

		list($preferProfile, $key) = self::getProfilePreference($title);

		// Only redirect if we dont have "?profile=no" and they prefer the profile.
		if ($wgRequest->getVal('profile') !== "no" &&
			$preferProfile &&
			self::$profilePage->isActionView() &&
			strpos($title->getText(), '/') === false &&
			empty($wgRequest->getVal('oldid')) &&
			empty($wgRequest->getVal('diff'))
		) {
			return $wgOut->redirect(self::$profilePage->getUserProfileTitle()->getFullURL());
		}

		// Warn visitors about user's preference
		if (self::shouldWarn($wgRequest) && $preferProfile && !$title->isRedirect()) {
			$wgOut->wrapWikiMsg(
				"<div class=\"mw-userpage-userdoesnotexist error\">\n$1\n</div>",
				[$key, wfEscapeWikiText($username)]
			);
		}

		return true;
	}

	/**
	 * Check for request variables that indicate the need to show warnings.
	 *
	 * @param  object $request Global $wgRequest object
	 * @return bool
	 */
	private static function shouldWarn($request) {
		return (
			$request->getVal('profile') == 'no' ||
			$request->getVal('veaction') == 'edit' ||
			$request->getVal('action') == 'edit'
		);
	}

	/**
	 * Handle adding profile=no to redirects after articles are created or edited in the NS_USER and NS_USER_TALK namespaces.
	 *
	 * @access public
	 * @param  EditPage   $editpage EditPage
	 * @param  WebRequest $request  WebRequest
	 * @return boolean	True
	 */
	public static function onEditPageImportFormData(EditPage $editpage, WebRequest $request) {
		if (self::$profilePage && ((self::$profilePage->isUserPage() && self::$profilePage->isProfilePreferred()) || (self::$profilePage->isUserTalkPage() && self::$profilePage->isCommentsPreferred()))) {
			$request->setVal('wpExtraQueryRedirect', 'profile=no');
		}
		return true;
	}

	/**
	 * Adds links to the navigation tabs.
	 *
	 * @access public
	 * @param  object $skin   SkinTemplate
	 * @param  array  &$links Link Descriptors
	 * @return boolean	True
	 */
	public static function onSkinTemplateNavigation($skin, &$links) {
		if (self::$profilePage !== false) {
			self::$profilePage->customizeNavBar($links, $skin->getContext()->getTitle());
		}
		return true;
	}

	/**
	 * Customize subpage links to use profile=no as needed.
	 *
	 * @access public
	 * @param  string &$subpages    Subpages HTML
	 * @param  object $skinTemplate SkinTemplate
	 * @param  object $output       Output
	 * @return boolean	True
	 */
	public static function onSkinSubPageSubtitle(&$subpages, $skinTemplate, $output) {
		if (self::$profilePage && ((self::$profilePage->isUserPage() && self::$profilePage->isProfilePreferred()) || (self::$profilePage->isUserTalkPage() && self::$profilePage->isCommentsPreferred()))) {
			$title = $output->getTitle();
			if ($output->isArticle() && MWNamespace::hasSubpages($title->getNamespace())) {
				$ptext = $title->getPrefixedText();
				if (strpos($ptext, '/') !== false) {
					$links = explode('/', $ptext);
					array_pop($links);
					$c = 0;
					$growinglink = '';
					$display = '';
					$lang = $skinTemplate->getLanguage();

					foreach ($links as $link) {
						$growinglink .= $link;
						$display .= $link;
						$linkObj = Title::newFromText($growinglink);

						if (is_object($linkObj) && $linkObj->isKnown()) {
							$getlink = Linker::linkKnown(
								$linkObj,
								htmlspecialchars($display),
								[],
								['profile' => 'no']
							);

							$c++;

							if ($c > 1) {
								$subpages .= $lang->getDirMarkEntity() . $skinTemplate->msg('pipe-separator')->escaped();
							} else {
								$subpages .= '&lt; ';
							}

							$subpages .= $getlink;
							$display = '';
						} else {
							$display .= '/';
						}
						$growinglink .= '/';
					}
				}
				return false;
			}
		}
		return true;
	}

	/**
	 * Setups and Modifies Database Information
	 *
	 * @access public
	 * @param  object $updater DatabaseUpdater Object
	 * @return boolean	true
	 */
	public static function onLoadExtensionSchemaUpdates($updater) {
		$extDir = dirname(__DIR__);

		// Add tables that may exist for previous users of SocialProfile.
		$updater->addExtensionUpdate(['addTable', 'user_board', "{$extDir}/install/sql/table_user_board.sql", true]);
		$updater->addExtensionUpdate(['addTable', 'user_board_report_archives', "{$extDir}/install/sql/table_user_board_report_archives.sql", true]);
		$updater->addExtensionUpdate(['addTable', 'user_board_reports', "{$extDir}/install/sql/table_user_board_reports.sql", true]);

		// Update tables with extra fields for our use.
		$updater->addExtensionField('user_board', 'ub_in_reply_to', "{$extDir}/upgrade/sql/add_user_board_reply_to.sql", true);
		$updater->addExtensionField('user_board', 'ub_edited', "{$extDir}/upgrade/sql/add_user_board_edit_and_reply_date.sql", true);
		$updater->addExtensionField('user_board_report_archives', 'ra_action_taken_at', "{$extDir}/upgrade/sql/add_user_board_report_archives_action_taken_timestamp.sql", true);
		$updater->addExtensionField('user_board', 'ub_admin_acted', "{$extDir}/upgrade/sql/add_user_board_admin_action_log.sql", true);

		$updater->addExtensionUpdate(['modifyField', 'user_board_reports', 'ubr_reporter_curse_id', "{$extDir}/upgrade/sql/user_board_reports/rename_ubr_reporter_curse_id.sql", true]);
		$updater->addExtensionUpdate(['dropIndex', 'user_board_reports', 'ubr_report_curse_id', "{$extDir}/upgrade/sql/user_board_reports/drop_ubr_report_curse_id.sql", true]);
		$updater->addExtensionUpdate(['addIndex', 'user_board_reports', 'ubr_reporter_global_id', "{$extDir}/upgrade/sql/user_board_reports/add_ubr_reporter_global_id.sql", true]);
		$updater->addExtensionUpdate(['dropIndex', 'user_board_reports', 'ubr_report_archive_id_ubr_reporter_curse_id', "{$extDir}/upgrade/sql/user_board_reports/drop_ubr_report_archive_id_ubr_reporter_curse_id.sql", true]);
		$updater->addExtensionUpdate(['addIndex', 'user_board_reports', 'ubr_report_archive_id_ubr_reporter_global_id', "{$extDir}/upgrade/sql/user_board_reports/add_ubr_report_archive_id_ubr_reporter_global_id.sql", true]);

		$updater->addExtensionUpdate(['modifyField', 'user_board_report_archives', 'ra_curse_id_from', "{$extDir}/upgrade/sql/user_board_report_archives/rename_ra_curse_id_from.sql", true]);
		$updater->addExtensionUpdate(['dropIndex', 'user_board_report_archives', 'ra_curse_id_from', "{$extDir}/upgrade/sql/user_board_report_archives/drop_ra_curse_id_from.sql", true]);
		$updater->addExtensionUpdate(['addIndex', 'user_board_report_archives', 'ra_global_id_from', "{$extDir}/upgrade/sql/user_board_report_archives/add_ra_global_id_from.sql", true]);

		if (Environment::isMasterWiki()) {
			$updater->addExtensionUpdate(['addTable', 'user_relationship', "{$extDir}/install/sql/table_user_relationship.sql", true]);
			$updater->addExtensionUpdate(['addTable', 'user_relationship_request', "{$extDir}/install/sql/table_user_relationship_request.sql", true]);
		}

		return true;
	}

	/**
	 * Add unit tests to the mediawiki test framework
	 *
	 * @access public
	 * @param  array &$files
	 * @return boolean	true
	 */
	public static function onUnitTestsList(&$files) {
		// TODO in MW >= 1.24 this can just add the /tests/phpunit subdirectory
		$files = array_merge($files, glob(__DIR__ . '/tests/phpunit/*Test.php'));
		return true;
	}

	/**
	 * Register the canonical names for custom namespaces.
	 *
	 * @access public
	 * @param  array &$list namespace numbers mapped to corresponding canonical names
	 * @return boolean	true
	 */
	public static function onCanonicalNamespaces(&$list) {
		$list[NS_USER_PROFILE] = 'UserProfile';
		return true;
	}

	/**
	 * Add extra preferences
	 *
	 * @access public
	 * @param  object $user         User whose preferences are being modified
	 * @param  array  &$preferences Preferences description object, to be fed to an HTMLForm
	 * @return boolean	true
	 */
	public static function onGetPreferences($user, &$preferences) {
		ProfileData::insertProfilePrefs($preferences);
		return true;
	}

	/**
	 * Function Documentation
	 *
	 * @access public
	 * @param  array  $formData array of user submitted data
	 * @param  object $form     PreferencesForm object, also a ContextSource
	 * @param  object $user     User object with preferences to be saved set
	 * @param  bool   &$result  boolean indicating success
	 * @return boolean	True
	 */
	public static function onPreferencesFormPreSave($formData, $form, $user, &$result) {
		$untouchedUser = User::newFromId($user->getId());

		if (!$untouchedUser || !$untouchedUser->getId()) {
			return true;
		}

		$profileData = new ProfileData($user);
		$canEdit = $profileData->canEdit($user);
		if ($canEdit !== true) {
			$result = Status::newFatal($canEdit);
			foreach ($formData as $key => $value) {
				if (strpos($key, 'profile-') === 0 && $value != $untouchedUser->getOption($key)) {
					// Reset profile data to its previous state.
					$user->setOption($key, $untouchedUser->getOption($key));
				}
			}
		}

		return true;
	}

	/**
	 * Add extra preferences defaults
	 *
	 * @access public
	 * @param  array &$defaultOptions mapping of preference to default value
	 * @return boolean	true
	 */
	public static function onUserGetDefaultOptions(&$defaultOptions) {
		ProfileData::insertProfilePrefsDefaults($defaultOptions);
		return true;
	}

	/**
	 * Save preferences.
	 *
	 * @access public
	 * @param  User  $user     User whose preferences are being modified.
	 * @param  array &$options Preferences description object, to be fed to an HTMLForm.
	 * @return boolean	True
	 */
	public static function onUserSaveOptions(User $user, array &$options) {
		if ($user && $user->getId()) {
			ProfileData::processPreferenceSave($user, $options);
		}
		return true;
	}

	/**
	 * Get a username from title
	 *
	 * @param  Title $title
	 * @return string
	 */
	public static function resolveUsername($title) {
		$username = $title->getText();
		if (strpos($username, '/') > 0) {
			$username = explode('/', $username);
			$username = array_shift($username);
			$canonical = User::getCanonicalName($username);
			$username = $canonical ? $canonical : $username;
		}

		return $username;
	}

	/**
	 * Add this extension's Echo notifications.
	 *
	 * @access public
	 * @param  array &$wgEchoNotifications          in Extension:Echo.
	 * @param  array &$wgEchoNotificationCategories in Extension:Echo.
	 * @param  array &$wgEchoNotificationIcons      in Extension:Echo.
	 * @return boolean	True
	 */
	public static function onBeforeCreateEchoEvent(&$wgEchoNotifications, &$wgEchoNotificationCategories, &$wgEchoNotificationIcons) {
		$wgEchoNotificationCategories['profile-friendship'] = [
			'tooltip' => 'echo-pref-tooltip-profile-friendship',
			'priority' => 3,
		];
		$wgEchoNotificationCategories['profile-comment'] = [
			'tooltip' => 'echo-pref-tooltip-profile-comment',
			'priority' => 4,
		];
		$wgEchoNotificationCategories['profile-report'] = [
			'tooltip' => 'echo-pref-tooltip-profile-comment-report',
			'priority' => 5,
		];

		$wgEchoNotifications['friendship'] = [
			'primary-link' => [
				'message' => 'notification-link-text-view-friendship',
				'destination' => 'managefriends'
			],
			'category' => 'profile-friendship',
			'group' => 'interactive',
			'icon' => 'gratitude',
			'presentation-model' => 'CurseProfile\MWEcho\FriendshipPresentationModel',
			'formatter-class' => 'CurseProfile\MWEcho\NotificationFormatter',
			'title-message' => 'notification-header-friendship',
			'title-params' => ['agent', 'user'],
			'email-subject-message' => 'notification-friendship-email-subject',
			'email-subject-params' => ['agent', 'user'],
			'email-body-batch-message' => 'notification-friendship-email-body',
			'email-body-batch-params' => ['agent', 'user'],
			'email-body-batch-bundle-message' => 'notification-friendship-email-batch-body',
			'email-body-batch-bundle-params' => ['agent', 'user', 'agent-other-display', 'agent-other-count'],
			'user-locators' => [
				['EchoUserLocator::locateFromEventExtra', ['target_user_id']]
			]
		];
		$wgEchoNotifications['comment'] = [
			'primary-link' => [
				'message' => 'notification-link-text-view-comment',
				'destination' => 'profile'
			],
			'category' => 'profile-comment',
			'group' => 'interactive',
			'icon' => 'mention',
			'presentation-model' => 'CurseProfile\MWEcho\CommentPresentationModel',
			'formatter-class' => 'CurseProfile\MWEcho\NotificationFormatter',
			'title-message' => 'notification-header-comment',
			'title-params' => ['agent', 'user'],
			'email-subject-message' => 'notification-comment-email-subject',
			'email-subject-params' => ['agent', 'user'],
			'email-body-batch-message' => 'notification-comment-email-body',
			'email-body-batch-params' => ['agent', 'user', 'comment_id', 'comment'],
			'email-body-batch-bundle-message' => 'notification-comment-email-batch-body',
			'email-body-batch-bundle-params' => ['agent', 'user', 'agent-other-display', 'agent-other-count'],
			'user-locators' => [
				['EchoUserLocator::locateFromEventExtra', ['target_user_id']]
			]
		];
		$wgEchoNotifications['comment-reply'] = [
			'primary-link' => [
				'message' => 'notification-link-text-view-comment',
				'destination' => 'profile'
			],
			'category' => 'profile-comment',
			'group' => 'interactive',
			'icon' => 'mention',
			'presentation-model' => 'CurseProfile\MWEcho\CommentPresentationModel',
			'formatter-class' => 'CurseProfile\MWEcho\NotificationFormatter',
			'title-message' => 'notification-header-comment-reply',
			'title-params' => ['agent', 'user'],
			'email-subject-message' => 'notification-comment-reply-email-subject',
			'email-subject-params' => ['agent', 'user'],
			'email-body-batch-message' => 'notification-comment-reply-email-body',
			'email-body-batch-params' => ['agent', 'user', 'comment_id', 'comment'],
			'email-body-batch-bundle-message' => 'notification-comment-reply-email-batch-body',
			'email-body-batch-bundle-params' => ['agent', 'user', 'agent-other-display', 'agent-other-count'],
			'user-locators' => [
				['EchoUserLocator::locateFromEventExtra', ['target_user_id']]
			]
		];
		$wgEchoNotifications['comment-report'] = [
			'primary-link' => [
				'message' => 'notification-link-text-view-comment',
				'destination' => 'profile'
			],
			'category' => 'profile-report',
			'group' => 'interactive',
			'icon' => 'mention',
			'presentation-model' => 'CurseProfile\MWEcho\CommentPresentationModel',
			'formatter-class' => 'CurseProfile\MWEcho\NotificationFormatter',
			'title-message' => 'notification-header-comment-report',
			'title-params' => ['agent', 'user', 'comment_id'],
			'email-subject-message' => 'notification-comment-report-email-subject',
			'email-subject-params' => ['agent', 'user', 'comment_id'],
			'email-body-batch-message' => 'notification-comment-report-email-body',
			'email-body-batch-params' => ['agent', 'user', 'comment_id'],
			'email-body-batch-bundle-message' => 'notification-comment-report-email-batch-body',
			'email-body-batch-bundle-params' => ['agent', 'user', 'agent-other-display', 'agent-other-count', 'comment_id'],
			EchoAttributeManager::ATTR_LOCATORS => ['CurseProfile\MWEcho\EchoUserLocator::getAdmins']
		];

		return true;
	}

	/**
	 * Add CurseProfile CSS to Mobile Skin
	 *
	 * @access public
	 * @param  object $skin     SkinTemplate Object
	 * @param  array  &$modules Array of Modules to Modify
	 * @return bool True
	 */
	public static function onSkinMinervaDefaultModules($skin, &$modules) {
		if (self::$profilePage instanceof \CurseProfile\ProfilePage && self::$profilePage->isProfilePage()) {
			$modules = array_merge(
				['curseprofile-mobile' => ['a.ext.curseprofile.profilepage.mobile.styles', 'a.ext.curseprofile.profilepage.mobile.scripts']],
				$modules
			);
		}

		return true;
	}

	/**
	 * Prevent UserProfile pages from being shown as movable
	 *
	 * @access public
	 * @param  Integer $index	The index of the namespace being checked
 	 * @param  Boolean &$result	Whether MediaWiki currently thinks this namespace is movable
	 * @return bool
	 */
	public static function onNamespaceIsMovable($index, &$result) {
		if ($index == NS_USER_PROFILE) {
			$result = false;
		}

		return true;
	}
	
	/**
	 * Prevent UserProfile pages from being edited
	 *
	 * @access public
	 * @param	Title	&$title		Reference to the title in question
 	 * @param	User	&$user		Reference to the current user
 	 * @param	string	$action		Action concerning the title in question
 	 * @param	bool	&$result	Whether MediaWiki currently thinks the action may be performed
	 * @return bool
	 */
	public static function onUserCan(&$title, &$user, $action, &$result) {
		if ($title->getNamespace() == NS_USER_PROFILE && $action === 'edit') {
			$result = false;
			return false;
		}

		return true;
	}

}
