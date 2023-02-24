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
 */

namespace CurseProfile\Classes;

use Article;
use CurseProfile\Maintenance\ReplaceGlobalIdWithUserId;
use IContextSource;
use MediaWiki\Hook\BeforeInitializeHook;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Hook\EditPage__importFormDataHook;
use MediaWiki\Hook\NamespaceIsMovableHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Hook\SkinSubPageSubtitleHook;
use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Hook\WantedPages__getQueryInfoHook;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;
use MediaWiki\Linker\Hook\HtmlPageLinkRendererEndHook;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\Hook\ArticleFromTitleHook;
use MediaWiki\Page\Hook\ArticleViewRedirectHook;
use MediaWiki\Permissions\Hook\GetUserPermissionsErrorsHook;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Preferences\Hook\PreferencesFormPreSaveHook;
use MediaWiki\User\Hook\UserGetDefaultOptionsHook;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserOptionsLookup;
use MediaWiki\User\UserOptionsManager;
use NamespaceInfo;
use RequestContext;
use SpecialPage;
use Status;
use Title;
use User;
use Wikimedia\Rdbms\ILoadBalancer;

class Hooks implements
	ParserFirstCallInitHook,
	BeforeInitializeHook,
	BeforePageDisplayHook,
	ArticleViewRedirectHook,
	ArticleFromTitleHook,
	WantedPages__getQueryInfoHook,
	HtmlPageLinkRendererEndHook,
	EditPage__importFormDataHook,
	SkinTemplateNavigation__UniversalHook,
	SkinSubPageSubtitleHook,
	LoadExtensionSchemaUpdatesHook,
	GetPreferencesHook,
	PreferencesFormPreSaveHook,
	UserGetDefaultOptionsHook,
	NamespaceIsMovableHook,
	GetUserPermissionsErrorsHook
{

	private static ?ProfilePage $profilePage = null;
	private static ?Title $title = null;

	public function __construct(
		private UserFactory $userFactory,
		private ILoadBalancer $lb,
		private NamespaceInfo $namespaceInfo,
		private LinkRenderer $linkRenderer,
		private HookContainer $hookContainer,
		private UserOptionsLookup $userOptionsLookup,
		private UserOptionsManager $userOptionsManager
	) {
	}

	/** @inheritDoc */
	public function onParserFirstCallInit( $parser ) {
		// must check to see if profile page exists because sometimes the parser is used to parse messages
		// for a response to an API call that doesn't ever fully initialize the MW engine, thus never touching
		// onBeforeInitialize and not setting self::$profilePage
		if ( self::$profilePage && self::$profilePage->isProfilePage() ) {
			$parser->setFunctionHook( 'achievements', [ self::$profilePage, 'recentAchievements' ] );
			$parser->setFunctionHook( 'editorfriends', [ self::$profilePage, 'editOrFriends' ] );
			$parser->setFunctionHook( 'favwiki', [ self::$profilePage, 'favoriteWiki' ] );
			$parser->setFunctionHook( 'groups', [ self::$profilePage, 'groupList' ] );
			$parser->setFunctionHook( 'profilefield', [ self::$profilePage, 'fieldBlock' ] );
			$parser->setFunctionHook( 'profilelinks', [ self::$profilePage, 'profileLinks' ] );
			$parser->setFunctionHook( 'userlevel', [ self::$profilePage, 'userLevel' ] );
			$parser->setFunctionHook( 'avatar', 'CurseProfile\Classes\ProfilePage::userAvatar' );
			$parser->setFunctionHook( 'comments', 'CurseProfile\Classes\CommentDisplay::comments' );
			$parser->setFunctionHook( 'friendadd', 'CurseProfile\Classes\FriendDisplay::addFriendLink' );
			$parser->setFunctionHook( 'friendcount', 'CurseProfile\Classes\FriendDisplay::count' );
			$parser->setFunctionHook( 'friendlist', 'CurseProfile\Classes\FriendDisplay::friendList' );
			$parser->setFunctionHook( 'recentactivity', 'CurseProfile\Classes\RecentActivity::parserHook' );
		}
		return true;
	}

	/** @inheritDoc */
	public function onBeforeInitialize( $title, $unused, $output, $user, $request, $mediaWiki ) {
		self::$title = $title;
		self::$profilePage = ProfilePage::newFromTitle( $title ) ?: null;

		if ( $title->equals( SpecialPage::getTitleFor( "Preferences" ) ) ) {
			$output->addModuleStyles( [ 'ext.curseprofile.preferences.styles', 'ext.hydraCore.font-awesome.styles' ] );
			$output->addModules( [ 'ext.curseprofile.preferences.scripts' ] );
		}
	}

	/** @inheritDoc */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( self::$profilePage instanceof ProfilePage
			&& self::$profilePage->isProfilePage()
			&& $skin->getSkinName() === 'fandommobile'
		) {
			$out->addModules( [ 'a.ext.curseprofile.profilepage.mobile.scripts' ] );
			$out->addModuleStyles( [ 'a.ext.curseprofile.profilepage.mobile.styles' ] );
		}
	}

	/**
	 * Reset Title and ProfilePage context if a hard internal redirect is done by MediaWiki.
	 * @inheritDoc
	 */
	public function onArticleViewRedirect( $article ) {
		if ( self::$title !== null && !self::$title->equals( $article->getTitle() ) ) {
			// Reset profile context.
			self::$title = $article->getTitle();
			self::$profilePage = ProfilePage::newFromTitle( self::$title ) ?: null;
			$this->onArticleFromTitle( self::$title, $article, RequestContext::getMain() );
		}
	}

	/**
	 * Hide NS_USER, NS_USER_TALK, NS_USER_PROFILE from Special:WantedPages.
	 * @inheritDoc
	 */
	public function onWantedPages__getQueryInfo( $wantedPages, &$query ) {
		if ( isset( $query['conds'] ) ) {
			$db = $this->lb->getConnection( DB_REPLICA );
			foreach ( $query['conds'] as $index => $condition ) {
				if ( strpos( $condition, 'pl_namespace NOT IN' ) === 0 ) {
					$query['conds'][$index] =
						'pl_namespace NOT IN(' . $db->makeList( [ NS_USER, NS_USER_TALK, NS_USER_PROFILE ] ) . ')';
				}
			}
		}
	}

	/**
	 * Make links to user pages known when that user opts for a profile page
	 * @inheritDoc
	 */
	public function onHtmlPageLinkRendererEnd( $linkRenderer, $target, $isKnown, &$text, &$attribs, &$ret ) {
		$target = Title::newFromLinkTarget( $target );
		// Only process user namespace links
		if ( !in_array( $target->getNamespace(), [ NS_USER, NS_USER_PROFILE ] ) || $target->isSubpage() ) {
			return true;
		}

		// Override user links based on enhanced user profile preference
		$profileData = new ProfileData( $this->userFactory->newFromName( $target->getText() ) );
		if ( $profileData->getProfileTypePreference() ) {
			$prefix = strtok( $attribs['title'], ':' );
			$attribs['href'] = $this->resolveHref( $attribs, $target );
			$attribs['class'] = str_replace( 'new', 'known', $attribs['class'] );
			$attribs['title'] = $prefix . ':' . $target->getText();
		}
		// Override enhanced profile links if preference is standard
		if ( !$profileData->getProfileTypePreference() && $target->getNamespace() === NS_USER_PROFILE ) {
			$attribs['href'] = $this->resolveHref( $attribs, $target );
			$attribs['class'] = str_replace( 'new', 'known', $attribs['class'] );
			$attribs['title'] = 'UserProfile:' . $target->getText();
		}
		return true;
	}

	/**
	 * Execute actions when ArticleFromTitle is called and add resource loader modules.
	 * @inheritDoc
	 */
	public function onArticleFromTitle( $title, &$article, $context ) {
		if ( !self::$profilePage ) {
			return true;
		}

		if ( $title->getNamespace() == NS_USER_PROFILE ) {
			return $this->renderProfile( $title, $article, $context );
		}

		$this->renderUserPages( $title, $article, $context );
		return true;
	}

	/** Resolve href without red link */
	private function resolveHref( array $attribs, LinkTarget $target ): string {
		$urlComponents = parse_url( $attribs['href'] );
		$query = [];
		if ( isset( $urlComponents['query'] ) ) {
			parse_str( $urlComponents['query'], $query );
		}

		// Remove redlink and edit
		if ( !empty( $query ) && isset( $query['redlink'] ) ) {
			unset( $query['redlink'], $query['action'] );
		}

		// Add profile=no where needed
		if ( $target->getNamespace() === NS_USER && str_contains( $attribs[ 'class' ], 'mw-changeslist-title' ) ) {
			$query['profile'] = 'no';
		}
		// Rebuild query string
		$params = count( $query ) ? '?' . http_build_query( $query ) : '';
		return $urlComponents['path'] . $params;
	}

	/** Handle output of the profile page */
	private function renderProfile( Title $title, ?Article &$article, IContextSource $context ): bool {
		$output = $context->getOutput();
		if ( !self::$profilePage->isActionView() || str_contains( $title->getText(), '/' ) ) {
			$output->redirect( self::$profilePage->getUserProfileTitle()->getFullURL() );
			return true;
		}

		$output->addModuleStyles( [
			'ext.curseprofile.profilepage.styles',
			'ext.curseprofile.customskin.styles',
			'ext.curseprofile.comments.styles',
			'ext.hydraCore.font-awesome.styles'
		] );
		$output->addModules( [ 'ext.curseprofile.profilepage.scripts' ] );

		if ( !self::$profilePage->getUser()->getId() ) {
			$article = new NoProfilePage( $title );
			return false;
		}

		$article = self::$profilePage;
		return true;
	}

	/** Handle the user and talk page */
	private function renderUserPages( Title $title, ?Article &$article, IContextSource $context ): void {
		// Check if we are on a base page
		$username = ProfilePage::resolveUsername( $title );

		// Make sure we are in the right namespace
		if (
			!in_array( $title->getNamespace(), [ NS_USER, NS_USER_TALK ], true ) ||
			$title->getText() !== $username
		) {
			return;
		}

		$preferredProfile = $title->getNamespace() === NS_USER ?
			self::$profilePage->isProfilePreferred() : self::$profilePage->isCommentsPreferred();
		$key = $title->getNamespace() === NS_USER ? 'cp-user-prefers-profile-user' : 'cp-user-prefers-profile-talk';

		$request = $context->getRequest();
		// Only redirect if we don't have "?profile=no" and they prefer the profile.
		if (
			$request->getVal( 'profile' ) !== "no" &&
			$preferredProfile &&
			self::$profilePage->isActionView() &&
			!str_contains( $title->getText(), '/' ) &&
			empty( $request->getVal( 'oldid' ) ) &&
			empty( $request->getVal( 'diff' ) )
		) {
			$context->getOutput()->redirect( self::$profilePage->getUserProfileTitle()->getFullURL() );
			return;
		}

		// Warn visitors about user's preference
		if ( (
				$request->getVal( 'profile' ) === 'no' ||
				$request->getVal( 'veaction' ) === 'edit' ||
				$request->getVal( 'action' ) === 'edit'
			) && $preferredProfile && !$title->isRedirect() ) {
			$article = new UserPrefersProfilePage( $title, $key, $username );
		}
	}

	/**
	 * Handle adding profile=no to redirects after articles are created or edited in
	 * the NS_USER and NS_USER_TALK namespaces.
	 * @inheritDoc
	 */
	public function onEditPage__importFormData( $editpage, $request ) {
		if (
			self::$profilePage &&
			(
				( self::$profilePage->isUserPage() && self::$profilePage->isProfilePreferred() ) ||
				( self::$profilePage->isUserTalkPage() && self::$profilePage->isCommentsPreferred() )
			)
		) {
			$request->setVal( 'wpExtraQueryRedirect', 'profile=no' );
		}
		return true;
	}

	/** @inheritDoc */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		// get title and namespace from request context
		$skinTitle = $sktemplate->getContext()->getTitle();
		$skinNamespace = $sktemplate->getContext()->getTitle()->getNamespace();
		// Only modify the navbar if we are on a user, user talk, or profile page
		if ( self::$profilePage !== false && in_array( $skinNamespace, [ NS_USER, NS_USER_TALK, NS_USER_PROFILE ] ) ) {
			self::$profilePage->customizeNavBar( $links, $skinTitle );
		}
	}

	/**
	 * Customize subpage links to use profile=no as needed.
	 * @inheritDoc
	 */
	public function onSkinSubPageSubtitle( &$subpages, $skin, $out ) {
		if (
			!self::$profilePage ||
			!(
				( self::$profilePage->isUserPage() && self::$profilePage->isProfilePreferred() ) ) ||
				( self::$profilePage->isUserTalkPage() && self::$profilePage->isCommentsPreferred() )
			) {
			return true;
		}

		$title = $out->getTitle();
		if ( !$out->isArticle() || !$this->namespaceInfo->hasSubpages( $title->getNamespace() ) ) {
			return true;
		}

		$ptext = $title->getPrefixedText();
		if ( !str_contains( $ptext, '/' ) ) {
			return false;
		}

		$links = explode( '/', $ptext );
		array_pop( $links );
		$c = 0;
		$growinglink = '';
		$display = '';
		$lang = $skin->getLanguage();

		foreach ( $links as $link ) {
			$growinglink .= $link;
			$display .= $link;
			$linkObj = Title::newFromText( $growinglink );

			if ( $linkObj && $linkObj->isKnown() ) {
				$getlink = $this->linkRenderer->makeKnownLink(
					$linkObj,
					htmlspecialchars( $display ),
					[],
					[ 'profile' => 'no' ]
				);

				$c++;

				$subpages .= $c > 1 ?
					$lang->getDirMarkEntity() . $skin->msg( 'pipe-separator' )->escaped() :
					'&lt; ';

				$subpages .= $getlink;
				$display = '';
			} else {
				$display .= '/';
			}
			$growinglink .= '/';
		}
		return false;
	}

	/** @inheritDoc */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$extDir = dirname( __DIR__ ) . '/..';

		// Add tables that may exist for previous users of SocialProfile.
		$updater->addExtensionTable( 'user_board', "{$extDir}/install/sql/table_user_board.sql" );
		$updater->addExtensionTable(
			'user_board_report_archives',
			"{$extDir}/install/sql/table_user_board_report_archives.sql"
		);
		$updater->addExtensionTable(
			'user_board_reports',
			"{$extDir}/install/sql/table_user_board_reports.sql"
		);

		// global_id migration.
		$updater->addExtensionField(
			'user_board_reports',
			'ubr_id',
			"{$extDir}/upgrade/sql/user_board_reports/add_ubr_id.sql"
		);
		$updater->modifyExtensionField(
			'user_board_reports',
			'ubr_reporter_global_id',
			"{$extDir}/upgrade/sql/user_board_reports/change_ubr_reporter_global_id.sql"
		);
		$updater->addExtensionField(
			'user_board_reports',
			'ubr_reporter_user_id',
			"{$extDir}/upgrade/sql/user_board_reports/add_ubr_reporter_user_id.sql"
		);
		$updater->addExtensionIndex(
			'user_board_reports',
			'ubr_report_archive_id_ubr_reporter_user_id',
			"{$extDir}/upgrade/sql/user_board_reports/add_index_ubr_report_archive_id_ubr_reporter_user_id.sql"
		);
		$updater->dropExtensionIndex(
			'user_board_reports',
			'ubr_report_archive_id_ubr_reporter_global_id',
			"{$extDir}/upgrade/sql/user_board_reports/drop_index_ubr_report_archive_id_ubr_reporter_global_id.sql"
		);
		$updater->addExtensionField(
			'user_board_report_archives',
			'ra_user_id_from',
			"{$extDir}/upgrade/sql/user_board_report_archives/add_ra_user_id_from.sql"
		);
		$updater->modifyExtensionField(
			'user_board_report_archives',
			'ra_action_taken_by',
			"{$extDir}/upgrade/sql/user_board_report_archives/rename_ra_action_taken_by.sql"
		);
		$updater->addExtensionField(
			'user_board_report_archives',
			'ra_action_taken_by_user_id',
			"{$extDir}/upgrade/sql/user_board_report_archives/add_ra_action_taken_by_user_id.sql"
		);
		$updater->addExtensionField(
			'user_board',
			'ub_admin_acted_user_id',
			"{$extDir}/upgrade/sql/user_board/add_ub_admin_acted_user_id.sql"
		);
		$updater->modifyExtensionField(
			'user_board',
			'ub_admin_acted',
			"{$extDir}/upgrade/sql/user_board/rename_ub_admin_acted.sql"
		);
		$updater->addPostDatabaseUpdateMaintenance( ReplaceGlobalIdWithUserId::class );

		// global_id migration - Second part
		$updater->dropExtensionField(
			'user_board_reports',
			'ubr_reporter_global_id',
			"{$extDir}/upgrade/sql/user_board_reports/drop_ubr_reporter_global_id.sql"
		);
		$updater->dropExtensionField(
			'user_board_report_archives',
			'ra_global_id_from',
			"{$extDir}/upgrade/sql/user_board_report_archives/drop_ra_global_id_from.sql"
		);
		$updater->dropExtensionField(
			'user_board_report_archives',
			'ra_action_taken_by_global_id',
			"{$extDir}/upgrade/sql/user_board_report_archives/drop_ra_action_taken_by_global_id.sql"
		);
		$updater->dropExtensionField(
			'user_board',
			'ub_admin_acted_global_id',
			"{$extDir}/upgrade/sql/user_board/drop_ub_admin_acted_global_id.sql"
		);
		$updater->addExtensionTable(
			'user_board_purge_archive',
			"{$extDir}/install/sql/table_user_board_purge_archive.sql"
		);
	}

	/** @inheritDoc */
	public function onGetPreferences( $user, &$preferences ) {
		$preferences['profile-pref'] = [
			'type' => 'select',
			'label-message' => 'profileprefselect',
			'section' => 'personal/info/public',
			'options' => [
				wfMessage( 'profilepref-profile' )->plain() => 1,
				wfMessage( 'profilepref-wiki' )->plain() => 0,
			],
		];

		$preferences['comment-pref'] = [
			'type' => 'select',
			'label-message' => 'commentprefselect',
			'section' => 'personal/info/public',
			'options' => [
				wfMessage( 'commentpref-profile' )->plain() => 1,
				wfMessage( 'commentpref-wiki' )->plain() => 0,
			],
		];

		$preferences['profile-favwiki-display'] = [
			'type' => 'text',
			'label-message' => 'favoritewiki',
			'section' => 'personal/info/public',
		];

		$preferences['profile-favwiki'] = [
			'type' => 'text',
			'label-message' => 'favoritewiki',
			'cssclass' => 'profile-favwiki-hidden',
			'section' => 'personal/info/public',
		];

		$preferences['profile-aboutme'] = [
			'type' => 'textarea',
			'label-message' => 'aboutme',
			'section' => 'personal/info/public',
			'rows' => 6,
			'maxlength' => 5000,
			'placeholder' => wfMessage( 'aboutmeplaceholder' )->plain(),
			'help-message' => 'aboutmehelp',
		];

		$preferences['profile-avatar'] = [
			'type' => 'info',
			'label-message' => 'avatar',
			'section' => 'personal/info/public',
			'default' => wfMessage( 'avatar-help' )->parse(),
			'raw' => true,
		];

		$preferences['profile-location'] = [
			'type' => 'text',
			'label-message' => 'locationlabel',
			'section' => 'personal/info/location',
		];

		foreach ( ProfileData::EXTERNAL_PROFILE_FIELDS as $index => $field ) {
			$service = str_replace( 'profile-link-', '', $field );
			$preferences[$field] = [
				'type' => 'text',
				'maxlength' => 2083,
				'label-message' => $service . 'link',
				'section' => 'personal/info/profiles',
				'placeholder' => wfMessage( $service . 'linkplaceholder' )->plain()
			];
			if ( count( ProfileData::EXTERNAL_PROFILE_FIELDS ) - 1 === $index ) {
				$preferences[$field]['help-message'] = 'profilelink-help';
			}
		}

		if ( $user->getBlock() !== null ) {
			foreach ( ProfileData::getValidEditFields() as $field ) {
				$preferences[$field]['help-message'] = 'profile-blocked';
				$preferences[$field]['disabled'] = true;
			}
		}
	}

	/** @inheritDoc */
	public function onPreferencesFormPreSave( $formData, $form, $user, &$result, $oldUserOptions ) {
		if ( $user->isAnon() ) {
			return;
		}

		$profileData = new ProfileData( $user );
		$canEdit = $profileData->canEdit( $user );
		if ( $canEdit === true ) {
			return;
		}

		$displayWarning = false;
		// Reset profile data to its previous state.
		foreach ( $formData as $key => $value ) {
			if ( !str_starts_with( $key, 'profile-' ) ) {
				continue;
			}

			if ( !isset( $oldUserOptions[$key] ) && ( isset( $formData[$key] ) || isset( $form->mFieldData[$key] ) ) ) {
				unset( $formData[$key], $form->mFieldData[$key] );
				$displayWarning = true;
				$this->userOptionsManager->setOption( $user, $key, $oldUserOptions[$key] );
				continue;
			}

			// Non strict comparison is required here
			if ( $value != $oldUserOptions[$key] ) {
				$formData[$key] = $oldUserOptions[$key];
				$form->mFieldData[$key] = $oldUserOptions[$key];
				$this->userOptionsManager->setOption( $user, $key, $oldUserOptions[$key] );
				$displayWarning = true;
			}
		}

		if ( $displayWarning ) {
			/**
			 * Hook docs says that $result is an &bool type
			 * Hook is called from @see DefaultPreferencesFactory::saveFormData which expect on of bool|Status|string
			 * To preserve original behaviour (UCP: release-525) we had to return Status|string
			 */
			$result = Status::newFatal( $canEdit );
			$this->userOptionsManager->saveOptions( $user );
		}
	}

	/** @inheritDoc */
	public function onUserGetDefaultOptions( &$defaultOptions ) {
		$defaultOptions['echo-subscriptions-web-profile-friendship'] = 1;
		$defaultOptions['echo-subscriptions-email-profile-friendship'] = 1;
		$defaultOptions['echo-subscriptions-web-profile-comment'] = 1;
		$defaultOptions['echo-subscriptions-email-profile-comment'] = 1;
		$defaultOptions['echo-subscriptions-web-profile-report'] = 1;
		$defaultOptions['echo-subscriptions-email-profile-report'] = 1;

		// Allow overriding by setting the value in the global $wgDefaultUserOptions
		if ( !isset( $defaultOptions['profile-pref'] ) ) {
			$defaultOptions['profile-pref'] = 1;
		}

		if ( !isset( $defaultOptions['comment-pref'] ) ) {
			$defaultOptions['comment-pref'] = 0;
		}
	}

	/**
	 * Save preferences.
	 *
	 * @param User $user User whose preferences are being modified.
	 * @param array &$options Preferences description object, to be fed to an HTMLForm.
	 *
	 */
	public function onFandomUserSaveOptions( User $user, array &$options ) {
		if ( !$user->isRegistered() ) {
			return;
		}

		// don't allow blocked users to change their about me text
		if (
			$user->isSafeToLoad() &&
			$user->getBlock() !== null &&
			isset( $preferences['profile-aboutme'] ) &&
			$preferences['profile-aboutme'] != $this->userOptionsLookup->getOption( $user, 'profile-aboutme' )
		) {
			$preferences['profile-aboutme'] = $this->userOptionsLookup->getOption( $user, 'profile-aboutme' );
		}

		// run hooks on profile preferences (mostly for achievements)
		foreach ( ProfileData::getValidEditFields() as $field ) {
			if ( !empty( $preferences[$field] ) ) {
				$this->hookContainer->run( 'CurseProfileEdited', [ $user, $field, $preferences[$field] ] );
			}
		}

		foreach ( ProfileData::EXTERNAL_PROFILE_FIELDS as $field ) {
			if ( !isset( $preferences[$field] ) ) {
				continue;
			}
			$valid = ProfileData::validateExternalProfile(
				str_replace( 'profile-link-', '', $field ),
				$preferences[$field]
			);
			if ( $valid === false ) {
				$preferences[$field] = '';
				continue;
			}
			$preferences[$field] = $valid;
		}
	}

	/**
	 * Prevent UserProfile pages from being shown as movable
	 * @inheritDoc
	 */
	public function onNamespaceIsMovable( $index, &$result ) {
		return $index !== NS_USER_PROFILE;
	}

	/**
	 * Prevent UserProfile pages from being edited
	 * @inheritDoc
	 */
	public function onGetUserPermissionsErrors( $title, $user, $action, &$result ) {
		if ( $title->getNamespace() === NS_USER_PROFILE && $action === 'edit' ) {
			$result = 'badaccess-group0';
			return false;
		}

		return true;
	}
}
