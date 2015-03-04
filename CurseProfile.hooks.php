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
	private static $profilePage;

	/**
	 * Reference to the title originally parsed from this request
	 * @access	private
	 * @var		object	Title
	 */
	private static $title;

	public static function onParserFirstCall(&$parser) {
		// must check to see if profile page exists because sometimes the parser is used to parse messages
		// for a response to an API call that doesn't ever fully initialize the MW engine, thus never touching
		// onBeforeInitialize and not setting self::$profilePage
		if (self::$profilePage && self::$profilePage->isProfilePage()) {
			$parser->setFunctionHook('avatar',				'CurseProfile\ProfilePage::userAvatar');

			// methods on the profile object
			$parser->setFunctionHook('groups',				[self::$profilePage, 'groupList']);
			$parser->setFunctionHook('aboutme',				[self::$profilePage, 'aboutBlock']);
			$parser->setFunctionHook('favwiki',				[self::$profilePage, 'favoriteWiki']);
			$parser->setFunctionHook('location',			[self::$profilePage, 'location']);
			$parser->setFunctionHook('profilelinks',		[self::$profilePage, 'profileLinks']);
			// $parser->setFunctionHook('userstats',			'CurseProfile\ProfilePage::userStats'); (replaced inline due to parser issues)
			$parser->setFunctionHook('userlevel',			[self::$profilePage, 'userLevel']);
			$parser->setFunctionHook('editorfriends',		[self::$profilePage, 'editOrFriends']);
			//$parser->setFunctionHook('achievements',		[self::$profilePage, 'recentAchievements']);

			$parser->setFunctionHook('recentactivity',		'CurseProfile\RecentActivity::parserHook');
			$parser->setFunctionHook('friendadd',			'CurseProfile\FriendDisplay::addFriendLink');
			$parser->setFunctionHook('friendcount',			'CurseProfile\FriendDisplay::count');
			$parser->setFunctionHook('friendlist',			'CurseProfile\FriendDisplay::friendlist');
			$parser->setFunctionHook('comments',			'CurseProfile\CommentDisplay::comments');
		}
		return true;
	}

	public static function onBeforeInitialize(&$title, &$article, &$output, &$user, $request, $mediaWiki) {
		self::$title = $title;
		self::$profilePage = new ProfilePage($title);

		if (self::$profilePage->isSpoofedWikiPage()) {
			// overwrite the assignable argument
			$title = self::$profilePage->getUserWikiArticle()->getTitle();
			// TODO investigate using a derivitave context here instead of overwriting the original one?
			\RequestContext::getMain()->setTitle($title);
		} elseif ($request->getVal('redirectToUserwiki')) {
			$output->redirect(self::$profilePage->getCustomUserWikiTitle()->getFullURL());
		}

		return true;
	}

	public static function onTestCanonicalRedirect( $request, $title, $output ) {
		if (self::$profilePage->isUserWikiPage()) {
			return false; // don't redirect if we're forcing the wiki page to render
		}
		return true;
	}

	/**
	 * Make links to user pages known (not red) when that user opts for a profile page
	 */
	public static function onLinkBegin( $dummy, $target, &$html, &$customAttribs, &$query, &$options, &$ret ) {
		// only process user namespace links
		if (!in_array($target->getNamespace(), [NS_USER, NS_USER_PROFILE, NS_USER_WIKI])) {
			return true;
		}
		// setup a temp context with the query string serving as the request info
		// this allows the profile page logic to make decisions on the link URL rather than the current request
		global $wgUser;
		$tempContext = new \RequestContext();
		$tempContext->setRequest(new \FauxRequest($query));
		$tempContext->setTitle($target);
		$tempContext->setUser($wgUser);
		$profile = new ProfilePage($target, $tempContext);

		if ($profile->isProfilePage()) {
			// remove existing broken option
			$options = array_diff($options, ['broken']);
			// force link to be known
			$options[] = 'known';
		}
		return true;
	}

	/**
	 * Replaces User:Xxx with UserWiki:Xxx in emails when applicable
	 *
	 * @param	editor	User whose edit is triggering this email
	 * @param	title	Title object of the page in question that was updated
	 * @param	rc		RecentChange instance (new param in 1.24, backported by curse)
	 * @return	bool
	 */
	public static function onAbortEmailNotification($editor, $title, $rc = null) {
		if ( $rc !== null && $title->getNamespace() == NS_USER && !$title->isSubpage() ) {
			// look up user by name
			$user = \User::newFromName($title->getText());
			$profile = new ProfileData($user);
			if ($profile->getTypePref()) { // need to replace User:Xxx with UserWiki:Xxx
				$userWikiTitle = \Title::makeTitle(NS_USER_WIKI, $title->getDBkey(), $title->getFragment());

				// send the email with the new title
				$enotif = new \EmailNotification();
				$enotif->notifyOnPageChange( $editor, $userWikiTitle,
					$rc->mAttribs['rc_timestamp'],
					$rc->mAttribs['rc_comment'],
					$rc->mAttribs['rc_minor'],
					$rc->mAttribs['rc_last_oldid'],
					$rc->mExtra['pageStatus'] );

				// abort the original email because we have already sent one here
				return false;
			}
		}
		return true;
	}

	/**
	 * Execute actions when ArticleFromTitle is called and add resource loader modules.
	 *
	 * @access	public
	 * @param	object	Title object
	 * @param	mixed	Article object or null
	 * @return	void
	 */
	public static function onArticleFromTitle(\Title &$title, &$article) {
		global $wgRequest, $wgOut;

		// TODO shouldn't need to special case against static vars here.
		// We should be able to statelessly create a new ProfilePage from
		// the given title and perform logic from that object.
		// However, some of the crappy static stuff in ProfilePage makes that
		// more appropriate approach problematic until the ProfilePage class
		// gets cleaned up first.
		if (self::$title instanceOf Title && !self::$title->equals($title)) {
			return true;
		}

		// handle rendering duties for any of our namespaces
		if (self::$profilePage instanceOf \CurseProfile\ProfilePage && self::$profilePage->isProfilePage()) {
			// Add our CSS and JS
			$article = self::$profilePage;
			$wgOut->addModules('ext.curseprofile.profilepage');
			return true;
		}

		return true;
	}

	public static function onArticleUpdateBeforeRedirect($article, &$anchor, &$extraQuery) {
		if (self::$profilePage->isUserPage(false) && self::$profilePage->profilePreferred()) {
			$extraQuery = 'redirectToUserwiki=1';
		}
		return true;
	}

	// TODO: Currently unused? Either remove or find out how to properly use.
	public static function markUncachable($parser, &$limitReport) {
		$parser->disableCache();
		return true;
	}

	/**
	 * Adds links to the navigation tabs
	 */
	static public function onSkinTemplateNavigation($skin, &$links) {
		if (self::$profilePage && self::$profilePage->isUserPage(false)) {
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

		// Add tables that may exist for previous users of SocialProfile
		$updater->addExtensionUpdate(array('addTable', 'user_board', "{$extDir}/install/sql/create_user_board.sql", true));
		$updater->addExtensionUpdate(array('addTable', 'user_board_reports', "{$extDir}/install/sql/create_comment_moderation.sql", true));

		// Update tables with extra fields for our use
		$updater->addExtensionField('user_board', 'ub_in_reply_to', "{$extDir}/upgrade/sql/add_user_board_reply_to.sql", true);
		$updater->addExtensionField('user_board', 'ub_edited', "{$extDir}/upgrade/sql/add_user_board_edit_and_reply_date.sql", true);
		$updater->addExtensionField('user_board_report_archives', 'ra_action_taken_at', "{$extDir}/upgrade/sql/add_user_board_report_archives_action_taken_timestamp.sql", true);

		if (defined('CURSEPROFILE_MASTER')) {
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
	public static function onUnitTestsList( &$files ) {
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
		$list[NS_USER_WIKI]    = 'UserWiki';
		$list[NS_USER_PROFILE] = 'UserProfile';
		return true;
	}

	/**
	 * Add extra preferences
	 *
	 * @access	public
	 * @param	object	user whose preferences are being modified
	 * @param	array	Preferences description object, to be fed to an HTMLForm
	 * @return	boolean	true
	 */
	static public function onGetPreferences($user, &$preferences) {
		ProfileData::insertProfilePrefs($preferences);
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
		ProfileData::processPreferenceSave($user, $options);
		return true;
	}

	/**
	 * Setup echo notifications
	 */
	public static function onBeforeCreateEchoEvent( &$notifications, &$notificationCategories /* , &$icons */ ) {
		$notificationCategories['friendship'] = [
			'tooltip' => 'echo-pref-tooltip-friendship',
			'priority' => 3,
		];
		$notifications['friendship-request'] = [
			'category' => 'friendship',
			'group' => 'interactive',
			'icon' => 'gratitude',
			'formatter-class' => 'CurseProfile\NotificationFormatter',
			'title-message' => 'notification-friendship-request',
			'title-params' => ['agent'],
			'email-subject-message' => 'notification-friendship-request-email-subj',
			'email-subject-params' => ['agent'],
			'email-body-batch-message' => 'notification-friendship-request-email-body',
			'email-body-batch-params' => ['agent'],
			'email-body-batch-bundle-message' => 'notification-friendship-request-email-batch-body',
			'email-body-batch-bundle-params' => ['agent', 'agent-other-display', 'agent-other-count'],
		];

		$notificationCategories['profile-comment'] = [
			'tooltip' => 'echo-pref-tooltip-profile-comment',
			'priority' => 4,
		];
		$notifications['profile-comment'] = [
			'category' => 'profile-comment',
			'group' => 'interactive',
			'icon' => 'chat',
			'formatter-class' => 'CurseProfile\NotificationFormatter',
			'title-message' => 'notification-profile-comment',
			'title-params' => ['agent', 'user'],
			'payload' => ['comment-text'],
			'email-subject-message' => 'notification-profile-comment-email-subj',
			'email-subject-params' => ['agent', 'user'],
			'email-body-batch-message' => 'notification-profile-comment-email-body',
			'email-body-batch-params' => ['agent', 'user', 'comment-id'],
			'email-body-batch-bundle-message' => 'notification-profile-comment-email-bundle-body',
			'email-body-batch-bundle-params' => ['agent', 'user', 'agent-other-display', 'agent-other-count'],
		];
		return true;
	}

	public static function onEchoGetDefaultNotifiedUsers($event, &$users) {
		switch ($event->getType()) {
			case 'friendship-request':
			case 'profile-comment':
				$extra = $event->getExtra();
				if (!$extra || !isset($extra['target_user_id'])) {
					break;
				}
				$targetId = $extra['target_user_id'];
				$user = \User::newFromId($targetId);
				$users[$targetId] = $user;
				break;
		}
		return true;
	}
}
