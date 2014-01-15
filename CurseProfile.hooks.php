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
	 * @var		object
	 */
	private static $profilePage;

	public static function onParserFirstCall(&$parser) {
		if (self::$profilePage) {
			$parser->setFunctionHook('placeholderImage', 'CurseProfile\CP::placeholderImage');
			$parser->setFunctionHook('avatar', 'CurseProfile\ProfilePage::userAvatar');
			$parser->setFunctionHook('groups', 'CurseProfile\CP::groupList');
			$parser->setFunctionHook('aboutme', 'CurseProfile\ProfilePage::aboutBlock');
			$parser->setFunctionHook('favwiki', 'CurseProfile\ProfilePage::favoriteWiki');
			$parser->setFunctionHook('location', 'CurseProfile\ProfilePage::location');
			$parser->setFunctionHook('profilelinks', 'CurseProfile\ProfilePage::profileLinks');
			$parser->setFunctionHook('recentactivity', 'CurseProfile\RecentActivity::parserHook');
			$parser->setFunctionHook('friendadd', 'CurseProfile\FriendDisplay::addFriendLink');
			$parser->setFunctionHook('friendcount', 'CurseProfile\FriendDisplay::count');
			$parser->setFunctionHook('friendlist', 'CurseProfile\FriendDisplay::friendlist');
			$parser->setFunctionHook('comments', 'CurseProfile\CommentDisplay::comments');
		}
		return true;
	}

	public static function onArticleFromTitle(&$title, &$article) {
		global $wgRequest, $wgOut;
		self::$profilePage = new ProfilePage($title);

		if (self::$profilePage->isProfilePage()) {
			// Disable editing
			if ( $wgRequest->getVal( 'action' ) == 'edit' ) {
				$wgOut->redirect( $title->getFullURL() );
			}
			// Take over page output
			$wgOut->addModules('ext.curseprofile.profilepage');
			$article = self::$profilePage;
		} elseif (!self::$profilePage->isUserPage()) {
			self::$profilePage = null;
		}

		return true;
	}

	public static function markUncachable($parser, &$limitReport) {
		$parser->disableCache();
		return true;
	}

	/**
	 * Adds links to the navigation tabs
	 */
	static public function onSkinTemplateNavigation($skin, &$links) {
		if (self::$profilePage) {
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
		$updater->addExtensionUpdate(array('addTable', 'user_profile', "{$extDir}/install/sql/create_user_profile.sql", true));

		// Update tables with extra fields for our use
		$updater->addExtensionField('user_profile', 'up_steam_profile', "{$extDir}/upgrade/sql/add_profile_links.sql", true);
		$updater->addExtensionField('user_profile', 'up_favorite_wiki', "{$extDir}/upgrade/sql/add_favorite_wiki.sql", true);

		if (defined('CURSEPROFILE_MASTER')) {
			$updater->addExtensionUpdate(array('addTable', 'user_relationship', "{$extDir}/install/sql/create_user_relationship.sql", true));
			$updater->addExtensionUpdate(array('addTable', 'user_relationship_request', "{$extDir}/install/sql/create_user_relationship_request.sql", true));
		}

		return true;
	}
}
