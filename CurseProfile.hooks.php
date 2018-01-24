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
namespace CurseProfile;

class Hooks {
	/**
	 * Reference to ProfilePage object
	 *
	 * @access	private
	 * @var		object	CurseProfile\ProfilePage
	 */
	static private $profilePage;

	/**
	 * Reference to the title originally parsed from this request
	 * @access	private
	 * @var		object	Title
	 */
	static private $title;

	/**
	 * Setup extra namespaces during MediaWiki setup process.
	 *
	 * @access	public
	 * @param	object	Parser
	 * @return	boolean	True
	 */
	static public function onRegistration() {
		global $wgEchoNotificationIcons, $wgExtraNamespaces;

		define('NS_USER_PROFILE', 202);
		$wgExtraNamespaces[NS_USER_PROFILE] = 'UserProfile';

		$wgEchoNotificationIcons['gratitude'] = [
			'path' => "CurseProfile/img/notifications/Gratitude.png"
		];
		return true;
	}

	/**
	 * Set parser hooks for the profile pages.
	 *
	 * @access	public
	 * @param	object	Parser
	 * @return	boolean	True
	 */
	static public function onParserFirstCall(&$parser) {
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
			// $parser->setFunctionHook('userstats',			'CurseProfile\ProfilePage::userStats'); (replaced inline due to parser issues)
		}
		return true;
	}

	static public function onBeforeInitialize(&$title, &$article, &$output, &$user, $request, $mediaWiki) {
		self::$title = $title;
		self::$profilePage = ProfilePage::newFromTitle($title);

		if ($title->equals(\SpecialPage::getTitleFor("Preferences"))) {
			$output->addModules('ext.curseprofile.preferences');
		}

		return true;
	}

	/**
	 * Hide NS_USER_PROFILE from Special:WantedPages.
	 *
	 * @access	public
	 * @return	boolean	True
	 */
	static public function onWantedPagesGetQueryInfo(&$wantedPages, &$query) {
		if (isset($query['conds'])) {
			$db = wfGetDB(DB_REPLICA);
			foreach ($query['conds'] as $index => $condition) {
				if (strpos($condition, 'pl_namespace NOT IN') === 0) {
					$query['conds'][$index] = 'pl_namespace NOT IN('.$db->makeList([NS_USER, NS_USER_TALK, NS_USER_PROFILE]).')';
				}
			}
		}
		return true;
	}

	/**
	 * Make links to user pages known (not red) when that user opts for a profile page
	 */
	static public function onLinkBegin( $dummy, $target, &$html, &$customAttribs, &$query, &$options, &$ret ) {
		// only process user namespace links
		if (!in_array($target->getNamespace(), [NS_USER, NS_USER_PROFILE])) {
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
	 * @access	public
	 * @param	object	Title object
	 * @param	mixed	Article object or null
	 * @return	bool
	 */
	static public function onArticleFromTitle(\Title &$title, &$article, $context) {
		global $wgRequest, $wgOut;

		//Handle rendering duties for any user namespaces.
		if (self::$profilePage !== false) {
			$redirect = false;
			if (self::$profilePage->isProfilePage()) {
				if (!self::$profilePage->isActionView()) {
					$redirect = true;
				} else {
					// we are on our UserProfile namespace. Render.
					$article = self::$profilePage;
					$wgOut->addModules('ext.curseprofile.profilepage');
				}
			} else {
				//We are on the User namespace with our enhanced profile object enabled.
				if (self::$profilePage->profilePreferred() && $wgRequest->getVal('profile') !== "no" && self::$profilePage->isActionView()) {
					//Only redirect if we dont have "?profile=no" and they prefer the profile.
					$redirect = true;
				}
			}
			if ($redirect) {
				$wgOut->redirect(self::$profilePage->getUserProfileTitle()->getFullURL());
			}
		}

		return true;
	}

	static public function onArticleUpdateBeforeRedirect($article, &$anchor, &$extraQuery) {
		if ((self::$profilePage->isUserPage() || self::$profilePage->isUserTalkPage()) && self::$profilePage->profilePreferred()) {
			$extraQuery = 'profile=no';
		}
		return true;
	}

	// TODO: Currently unused? Either remove or find out how to properly use.
	static public function markUncachable($parser, &$limitReport) {
		$parser->disableCache();
		return true;
	}

	/**
	 * Adds links to the navigation tabs
	 */
	static public function onSkinTemplateNavigation($skin, &$links) {
		if (self::$profilePage !== false) {
			self::$profilePage->customizeNavBar($links);
		}
		return true;
	}

	/**
	 * Setups and Modifies Database Information
	 *
	 * @access	public
	 * @param	object	DatabaseUpdater Object
	 * @return	boolean	true
	 */
	static public function onLoadExtensionSchemaUpdates($updater) {
		$extDir = __DIR__;

		//Add tables that may exist for previous users of SocialProfile.
		$updater->addExtensionUpdate(['addTable', 'user_board', "{$extDir}/install/sql/table_user_board.sql", true]);
		$updater->addExtensionUpdate(['addTable', 'user_board_report_archives', "{$extDir}/install/sql/table_user_board_report_archives.sql", true]);
		$updater->addExtensionUpdate(['addTable', 'user_board_reports', "{$extDir}/install/sql/table_user_board_reports.sql", true]);

		//Update tables with extra fields for our use.
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

		if (defined('MASTER_WIKI') && MASTER_WIKI === true) {
			$updater->addExtensionUpdate(array('addTable', 'user_relationship', "{$extDir}/install/sql/create_user_relationship.sql", true));
			$updater->addExtensionUpdate(array('addTable', 'user_relationship_request', "{$extDir}/install/sql/create_user_relationship_request.sql", true));
		}

		return true;
	}

	/**
	 * Add unit tests to the mediawiki test framework
	 *
	 * @access	public
	 * @param	array	$files
	 * @return	boolean	true
	 */
	static public function onUnitTestsList( &$files ) {
		// TODO in MW >= 1.24 this can just add the /tests/phpunit subdirectory
		$files = array_merge( $files, glob(__DIR__.'/tests/phpunit/*Test.php'));
		return true;
	}

	/**
	 * Register the canonical names for custom namespaces.
	 *
	 * @access	public
	 * @param	array	namespace numbers mapped to corresponding canonical names
	 * @return	boolean	true
	 */
	static public function onCanonicalNamespaces(&$list) {
		$list[NS_USER_PROFILE] = 'UserProfile';
		return true;
	}

	/**
	 * Add extra preferences
	 *
	 * @access	public
	 * @param	object	User whose preferences are being modified
	 * @param	array	Preferences description object, to be fed to an HTMLForm
	 * @return	boolean	true
	 */
	static public function onGetPreferences($user, &$preferences) {
		ProfileData::insertProfilePrefs($preferences);
		return true;
	}

	/**
	 * Function Documentation
	 *
	 * @access	public
	 * @param	array	$formData: array of user submitted data
	 * @param	object	$form: PreferencesForm object, also a ContextSource
	 * @param	object	$user: User object with preferences to be saved set
	 * @param	boolean	&$result: boolean indicating success
	 * @return	boolean	True
	 */
	static public function onPreferencesFormPreSave($formData, $form, $user, &$result) {
		$untouchedUser = \User::newFromId($user->getId());

		if (!$untouchedUser || !$untouchedUser->getId()) {
			return true;
		}

		$profileData = new ProfileData($user);
		$canEdit = $profileData->canEdit($user);
		if ($canEdit !== true) {
			$result = \Status::newFatal($canEdit);
			foreach ($formData as $key => $value) {
				if (strpos($key, 'profile-') === 0 && $value != $untouchedUser->getOption($key)) {
					//Reset profile data to its previous state.
					$user->setOption($key, $untouchedUser->getOption($key));
				}
			}
		}

		return true;
	}

	/**
	 * Add extra preferences defaults
	 *
	 * @access	public
	 * @param	array	mapping of preference to default value
	 * @return	boolean	true
	 */
	static public function onUserGetDefaultOptions(&$defaultOptions) {
		ProfileData::insertProfilePrefsDefaults($defaultOptions);
		return true;
	}

	/**
	 * Save preferences
	 *
	 * @access	public
	 * @param	object	user whose preferences are being modified
	 * @param	array	Preferences description object, to be fed to an HTMLForm
	 * @return	boolean	true
	 */
	static public function onUserSaveOptions(\User $user, array &$options) {
		if ($user && $user->getId()) {
			ProfileData::processPreferenceSave($user, $options);
		}
		return true;
	}

	/**
	 * Add this extension's Echo notifications.
	 *
	 * @access	public
	 * @param	array	See $wgEchoNotifications in Extension:Echo.
	 * @param	array	See $wgEchoNotificationCategories in Extension:Echo.
	 * @param	array	See $wgEchoNotificationIcons in Extension:Echo.
	 * @return	boolean	True
	 */
	static public function onBeforeCreateEchoEvent(&$wgEchoNotifications, &$wgEchoNotificationCategories, &$wgEchoNotificationIcons) {
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
			\EchoAttributeManager::ATTR_LOCATORS => ['CurseProfile\MWEcho\EchoUserLocator::getAdmins']
		];

		return true;
	}

	/**
	 * Add CurseProfile CSS to Mobile Skin
	 *
	 * @access	public
	 * @param	object	SkinTemplate Object
	 * @param	array	Array of Modules to Modify
	 * @return	boolean True
	 */
	static public function onSkinMinervaDefaultModules($skin, &$modules) {
		if (self::$profilePage instanceof \CurseProfile\ProfilePage && self::$profilePage->isProfilePage()) {
			$modules = array_merge(
				['curseprofile-mobile' => ['a.ext.curseprofile.profilepage.mobile']],
				$modules
			);
		}

		return true;
	}
}
