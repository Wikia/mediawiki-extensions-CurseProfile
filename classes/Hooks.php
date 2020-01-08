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
use EditPage;
use Linker;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Linker\LinkTarget;
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
	 * @var object	CurseProfile\ProfilePage
	 */
	static private $profilePage = false;

	/**
	 * Reference to the title originally parsed from this request.
	 *
	 * @var object	Title
	 */
	static private $title = null;

	/**
	 * Setup extra namespaces during MediaWiki setup process.
	 *
	 * @return boolean	True
	 */
	public static function onRegistration() {
		global $wgExtraNamespaces;

		if (!defined('NS_USER_PROFILE')) {
			define('NS_USER_PROFILE', 202);
		}
		$wgExtraNamespaces[NS_USER_PROFILE] = 'UserProfile';

		return true;
	}

	/**
	 * Set parser hooks for the profile pages.
	 *
	 * @param Parser $parser
	 *
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
	 * @param Title   $title
	 * @param Article $article
	 * @param object  $output
	 * @param User    $user
	 * @param object  $request
	 * @param object  $mediaWiki
	 *
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
	 * @param object $destArticle Destination Article
	 *
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
	 * @param mixed $wantedPages
	 * @param mixed $query
	 *
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
	 * Make links to user pages known when that user opts for a profile page
	 *
	 * @param LinkRenderer $linkRenderer
	 * @param Title        $target
	 * @param bool         $isKnown
	 * @param string       $text
	 * @param array        $attribs
	 * @param mixed        $ret
	 *
	 * @return boolean
	 */
	public static function onHtmlPageLinkRendererEnd(
		LinkRenderer $linkRenderer,
		LinkTarget $target,
		$isKnown,
		&$text,
		&$attribs,
		&$ret
	) {
		// Only process user namespace links
		if (!in_array($target->getNamespace(), [NS_USER, NS_USER_PROFILE]) || $target->isSubpage()) {
			return true;
		}
		$user = User::newFromName($target->getText());
		// Override user links based on enhanced user profile preference
		$profileData = new ProfileData($user);
		if ($profileData->getProfileTypePreference()) {
			$prefix = strtok($attribs['title'], ':');
			$attribs['href'] = self::resolveHref($attribs, $target);
			$attribs['class'] = str_replace('new', 'known', $attribs['class']);
			$attribs['title'] = $prefix . ':' . $target->getText();
		}
		// Override enhanced profile links if preference is standard
		if (!$profileData->getProfileTypePreference() && $target->getNamespace() === NS_USER_PROFILE) {
			$attribs['href'] = self::resolveHref($attribs, $target);
			$attribs['class'] = str_replace('new', 'known', $attribs['class']);
			$attribs['title'] = 'UserProfile:' . $target->getText();
		}
		return true;
	}

	/**
	 * Execute actions when ArticleFromTitle is called and add resource loader modules.
	 *
	 * @param Title   $title
	 * @param Article $article
	 *
	 * @return boolean
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
	 * Resolve href without red link
	 *
	 * @param array  $attribs
	 * @param string $target
	 *
	 * @return string
	 */
	private static function resolveHref($attribs, $target) {
		$url_components = parse_url($attribs['href']);
		parse_str($url_components['query'], $query);

		// Remove redlink and edit
		if ($query && isset($query['redlink'])) {
			unset($query['redlink'], $query['action']);
		}

		if ($target->getNamespace() === NS_USER) {
			// Add profile=no where needed
			if (strpos($attribs['class'], 'mw-changeslist-title') !== false) {
				$query['profile'] = 'no';
			}
		}
		// Rebuild query string
		$params = count($query) ? '?' . http_build_query($query) : '';
		return $url_components['path'] . $params;
	}

	/**
	 * Handle output of the profile page
	 *
	 * @param Title   $title
	 * @param Article $article
	 *
	 * @return boolean
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
	 * @param Title $title
	 *
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
	 * Handle the user and talk page
	 *
	 * @param Title $title
	 *
	 * @return boolean
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

		// Only redirect if we don't have "?profile=no" and they prefer the profile.
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
				"<div class=\"curseprofile-userprefersprofile error\">\n$1\n</div>",
				[$key, wfEscapeWikiText($username)]
			);
		}

		return true;
	}

	/**
	 * Check for request variables that indicate the need to show warnings.
	 *
	 * @param object $request Global $wgRequest object
	 *
	 * @return boolean
	 */
	private static function shouldWarn($request) {
		return (
			$request->getVal('profile') == 'no' ||
			$request->getVal('veaction') == 'edit' ||
			$request->getVal('action') == 'edit'
		);
	}

	/**
	 * Handle adding profile=no to redirects after articles are created or edited in
	 * the NS_USER and NS_USER_TALK namespaces.
	 *
	 * @param EditPage   $editpage EditPage
	 * @param WebRequest $request  WebRequest
	 *
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
	 * @param object $skin  SkinTemplate
	 * @param array  $links Link Descriptors
	 *
	 * @return boolean	True
	 */
	public static function onSkinTemplateNavigation($skin, &$links) {
		// get title and namespace from request context
		$skinTitle = $skin->getContext()->getTitle();
		$skinNamespace = $skin->getContext()->getTitle()->getNamespace();
		// Only modify the navbar if we are on a user, user talk, or profile page
		if (self::$profilePage !== false && in_array($skinNamespace, [NS_USER, NS_USER_TALK, NS_USER_PROFILE])) {
			self::$profilePage->customizeNavBar($links, $skinTitle);
		}
		return true;
	}

	/**
	 * Customize subpage links to use profile=no as needed.
	 *
	 * @param string $subpages     Subpages HTML
	 * @param object $skinTemplate SkinTemplate
	 * @param object $output       Output
	 *
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
	 * @param object $updater DatabaseUpdater Object
	 *
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

		// global_id migration.
		$updater->addExtensionUpdate(['addField', 'user_board_reports', 'ubr_reporter_user_id', "{$extDir}/upgrade/sql/user_board_reports/add_ubr_reporter_user_id.sql", true]);
		$updater->addExtensionUpdate(['addField', 'user_board_report_archives', 'ra_user_id_from', "{$extDir}/upgrade/sql/user_board_report_archives/add_ra_user_id_from.sql", true]);
		$updater->addExtensionUpdate(['modifyField', 'user_board_report_archives', 'ra_action_taken_by', "{$extDir}/upgrade/sql/user_board_report_archives/rename_ra_action_taken_by.sql", true]);
		$updater->addExtensionUpdate(['addField', 'user_board_report_archives', 'ra_action_taken_by_user_id', "{$extDir}/upgrade/sql/user_board_report_archives/add_ra_action_taken_by_user_id.sql", true]);
		$updater->addExtensionUpdate(['addField', 'user_board', 'ub_admin_acted_user_id', "{$extDir}/upgrade/sql/user_board/add_ub_admin_acted_user_id.sql", true]);
		$updater->addExtensionUpdate(['modifyField', 'user_board', 'ub_admin_acted', "{$extDir}/upgrade/sql/user_board/rename_ub_admin_acted.sql", true]);

		// global_id migration - Second part, uncomment in the future.
		// $updater->addExtensionUpdate(['dropField', 'user_board_reports', 'ubr_reporter_global_id', "{$extDir}/upgrade/sql/user_board_reports/drop_ubr_reporter_global_id.sql", true]);
		// $updater->addExtensionUpdate(['dropField', 'user_board_report_archives', 'ra_global_id_from', "{$extDir}/upgrade/sql/user_board_report_archives/drop_ra_global_id_from.sql", true]);
		// $updater->addExtensionUpdate(['dropField', 'user_board_report_archives', 'ra_action_taken_by_global_id', "{$extDir}/upgrade/sql/user_board_report_archives/drop_ra_action_taken_by_global_id.sql", true]);
		// $updater->addExtensionUpdate(['dropField', 'user_board', 'ub_admin_acted_global_id', "{$extDir}/upgrade/sql/user_board/drop_ub_admin_acted_global_id.sql", true]);

		if (Environment::isMasterWiki()) {
			$updater->addExtensionUpdate(['addTable', 'user_relationship', "{$extDir}/install/sql/table_user_relationship.sql", true]);
			$updater->addExtensionUpdate(['addTable', 'user_relationship_request', "{$extDir}/install/sql/table_user_relationship_request.sql", true]);
		}

		$updater->addExtensionUpdate(['addTable', 'user_board_purge_archive', "{$extDir}/install/sql/table_user_board_purge_archive.sql", true]);

		return true;
	}

	/**
	 * Add unit tests to the mediawiki test framework
	 *
	 * @param array $files
	 *
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
	 * @param array $list namespace numbers mapped to corresponding canonical names
	 *
	 * @return boolean	true
	 */
	public static function onCanonicalNamespaces(&$list) {
		$list[NS_USER_PROFILE] = 'UserProfile';
		return true;
	}

	/**
	 * Add extra preferences
	 *
	 * @param object $user        User whose preferences are being modified
	 * @param array  $preferences Preferences description object, to be fed to an HTMLForm
	 *
	 * @return boolean	true
	 */
	public static function onGetPreferences($user, &$preferences) {
		ProfileData::insertProfilePrefs($preferences);
		return true;
	}

	/**
	 * Function Documentation
	 *
	 * @param array  $formData array of user submitted data
	 * @param object $form     PreferencesForm object, also a ContextSource
	 * @param object $user     User object with preferences to be saved set
	 * @param bool   $result   boolean indicating success
	 *
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
	 * @param array $defaultOptions mapping of preference to default value
	 *
	 * @return boolean	true
	 */
	public static function onUserGetDefaultOptions(&$defaultOptions) {
		ProfileData::insertProfilePrefsDefaults($defaultOptions);
		return true;
	}

	/**
	 * Save preferences.
	 *
	 * @param User  $user    User whose preferences are being modified.
	 * @param array $options Preferences description object, to be fed to an HTMLForm.
	 *
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
	 * @param Title $title
	 *
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
	 * Add CurseProfile CSS to Mobile Skin
	 *
	 * @param object $skin    SkinTemplate Object
	 * @param array  $modules Array of Modules to Modify
	 *
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
	 * @param Integer $index  The index of the namespace being checked
	 * @param Boolean $result Whether MediaWiki currently thinks this namespace is movable
	 *
	 * @return boolean
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
	 * @param Title  $title  Reference to the title in question
	 * @param User   $user   Reference to the current user
	 * @param string $action Action concerning the title in question
	 * @param bool   $result Whether MediaWiki currently thinks the action may be performed
	 *
	 * @return boolean
	 */
	public static function onUserCan(&$title, &$user, $action, &$result) {
		if ($title->getNamespace() == NS_USER_PROFILE && $action === 'edit') {
			$result = false;
			return false;
		}

		return true;
	}
}
