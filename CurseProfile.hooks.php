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
	 * If we build a profile page then we will add a bunch of extra
	 * parser functions for use during the construction.
	 *
	 * @access	private
	 * @var		boolean
	 */
	private static $buildingProfilePage = false;

	public static function onParserFirstCall(&$parser) {
		if (self::$buildingProfilePage) {
			$parser->setFunctionHook('cpGravatar', 'CurseProfile\ProfilePage::gravatar');
			$parser->setFunctionHook('placeholderImage', 'CurseProfile\CP::placeholderImage');
			$parser->setFunctionHook('avatar', 'CurseProfile\CP::userAvatar');
			$parser->setFunctionHook('groups', 'CurseProfile\CP::groupList');
			$parser->setFunctionHook('aboutme', 'CurseProfile\ProfilePage::aboutBlock');
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

		if (ProfilePage::isProfilePage($title)) {
			// TODO check user pref and disable return if they don't want the profile page

			// Disable editing
			if ( $wgRequest->getVal( 'action' ) == 'edit' ) {
				$wgOut->redirect( $title->getFullURL() );
			}

			$wgOut->addModules('ext.curseprofile.profilepage');

			$article = new ProfilePage($title);
			self::$buildingProfilePage = true;
		}
		return true;
	}

	public static function markUncachable($parser, &$limitReport) {
		$parser->disableCache();
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

		if (defined('CURSEPROFILE_MASTER')) {
			$updater->addExtensionUpdate(array('addTable', 'user_relationship', "{$extDir}/install/sql/create_user_relationship.sql", true));
			$updater->addExtensionUpdate(array('addTable', 'user_relationship_request', "{$extDir}/install/sql/create_user_relationship_request.sql", true));
		}

		return true;
	}
}
