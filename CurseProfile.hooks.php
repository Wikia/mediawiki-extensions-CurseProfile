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
			$parser->setFunctionHook('groups',				'CurseProfile\ProfilePage::groupList');
			$parser->setFunctionHook('aboutme',				'CurseProfile\ProfilePage::aboutBlock');
			$parser->setFunctionHook('favwiki',				'CurseProfile\ProfilePage::favoriteWiki');
			$parser->setFunctionHook('location',			'CurseProfile\ProfilePage::location');
			$parser->setFunctionHook('profilelinks',		'CurseProfile\ProfilePage::profileLinks');
			// $parser->setFunctionHook('userstats',			'CurseProfile\ProfilePage::userStats'); (replaced inline)
			$parser->setFunctionHook('userlevel',			'CurseProfile\ProfilePage::userLevel');
			$parser->setFunctionHook('editorfriends',		'CurseProfile\ProfilePage::editOrFriends');
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
		$profile = new ProfilePage($target);
		if ($profile->isProfilePage()) {
			$ret = \Html::rawElement('a', ['href'=>$target->getFullURL($query)] + $customAttribs, $html);
			return false;
		}
		return true;
	}

	/**
	 * Function Documentation
	 *
	 * @access	public
	 * @param	object	Title object
	 * @param	mixed	Article object or null
	 * @param	object	Context object
	 * @return	void
	 */
	public static function onArticleFromTitle(\Title &$title, &$article, $context) {
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
		if (self::$profilePage->isProfilePage()) {
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
		if (self::$profilePage->isUserPage(false)) {
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

		// Update tables with extra fields for our use
		$updater->addExtensionField('user_board', 'ub_in_reply_to', "{$extDir}/upgrade/sql/add_user_board_reply_to.sql", true);

		if (defined('CURSEPROFILE_MASTER')) {
			$updater->addExtensionUpdate(array('addTable', 'user_relationship', "{$extDir}/install/sql/create_user_relationship.sql", true));
			$updater->addExtensionUpdate(array('addTable', 'user_relationship_request', "{$extDir}/install/sql/create_user_relationship_request.sql", true));
		}

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
			// 'tooltip' => 'echo-pref-tooltip-friendship',
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
			'email-body-message' => 'notification-friendship-request-email-body',
			'email-body-params' => ['agent', 'user', 'gamepedia-footer'],
		];

		$notificationCategories['profile-comment'] = [
			// 'tooltip' => 'echo-pref-tooltip-profile-comment',
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
			'email-body-message' => 'notification-profile-comment-email-body',
			'email-body-params' => ['agent', 'user', 'gamepedia-footer'],
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
