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

use Action;
use Article;
use Cheevos\Cheevos;
use Cheevos\CheevosAchievement;
use Cheevos\CheevosException;
use Cheevos\CheevosHelper;
use Cheevos\Points\PointsDisplay;
use Config;
use Fandom\WikiConfig\WikiVariablesDataService;
use Html;
use HtmlArmor;
use HydraCore;
use IContextSource;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserOptionsLookup;
use Message;
use MessageCache;
use Parser;
use SpecialPage;
use Subscription\Subscription;
use Title;
use User;

/**
 * Class ProfilePage
 * Holds the primary logic over how and when a profile page is displayed
 */
class ProfilePage extends Article {

	public const PENCIL_ICON_SVG = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 18 18"><defs><path id="pencil-small" d="M14 8.586L9.414 4 11 2.414 15.586 7 14 8.586zM6.586 16H2v-4.586l6-6L12.586 10l-6 6zm11.121-9.707l-6-6a.999.999 0 0 0-1.414 0l-9.999 10a.99.99 0 0 0-.217.325A.991.991 0 0 0 0 11v6a1 1 0 0 0 1 1h6c.13 0 .26-.026.382-.077a.99.99 0 0 0 .326-.217l9.999-9.999a.999.999 0 0 0 0-1.414z"/></defs><use fill-rule="evenodd" xlink:href="#pencil-small"/></svg>';

	private const HIDDEN_GROUPS = [
		'*',
		'ads_manager',
		'autoconfirmed',
		'checkuser',
		'hydra_admin',
		'widget_editor'
	];
	private const USER_NAMESPACES = [ NS_USER, NS_USER_TALK, NS_USER_PROFILE ];
	private bool $mobile;
	private bool $actionIsView;
	private User $user;
	private ProfileData $profile;

	private UserOptionsLookup $userOptionsLookup;
	private UserGroupManager $userGroupManager;
	private MessageCache $messageCache;
	private WikiVariablesDataService $wikiVariablesDataService;
	private Config $config;
	private Subscription $subscription;

	/**
	 * Main Constructor
	 *
	 * @param Title $title
	 * @param IContextSource|null $context
	 * @return void
	 */
	public function __construct( $title, $context = null ) {
		parent::__construct( $title );
		$services = MediaWikiServices::getInstance();
		$userFactory = $services->getUserFactory();
		$this->userOptionsLookup = $services->getUserOptionsLookup();
		$this->userGroupManager = $services->getUserGroupManager();
		$this->messageCache = $services->getMessageCache();
		$this->linkRenderer = $services->getLinkRenderer();
		$this->wikiVariablesDataService = $services->getService( WikiVariablesDataService::class );
		$this->subscription = $services->getService( Subscription::class );
		$this->config = $services->getMainConfig();

		if ( $context ) {
			$this->setContext( $context );
		}
		$this->actionIsView = Action::getActionName( $this->getContext() ) === 'view';
		$userName = self::resolveUsername( $title );
		$this->user = $userFactory->newFromName( $userName );
		if ( $this->user ) {
			$this->user->load();
			$this->getContext()->getSkin()->setRelevantUser( $this->getUser() );
		} else {
			$this->user = $userFactory->newAnonymous();
		}

		$skin = $this->getContext()->getSkin();
		$this->mobile = HydraCore::isMobileSkin( $skin ) || $skin->getSkinName() === 'fandommobile';
		$this->profile = new ProfileData( $this->user->getId() );
	}

	/**
	 * Create a new instance from a page title.  This is the preferred entry point since it handles
	 * if the title is in an usable namespace.
	 *
	 * @param Title $title
	 * @param IContextSource|null $context
	 * @return mixed New self or false for a bad title.
	 */
	public static function newFromTitle( $title, IContextSource $context = null ) {
		if ( in_array( $title->getNamespace(), self::USER_NAMESPACES, true ) ) {
			// We do not call the parent newFromTitle since it could return the wrong class.
			return new self( $title, $context );
		}
		return false;
	}

	/**
	 * Primary rendering function for mediawiki's Article
	 */
	public function view() {
		$output = $this->getContext()->getOutput();
		$output->setPageTitle( $this->getTitle()->getPrefixedText() );
		$output->setArticleFlag( false );
		$output->setRobotPolicy( "noindex,nofollow" );

		$layout = ( $this->mobile ? $this->mobileProfileLayout() : $this->profileLayout() );
		$userStats = $this->userStats();
		$layout = str_replace( '<USERSTATS>', $userStats, $layout );

		$outputString = $this->messageCache->parse( $layout, $this->getTitle() );
		if ( $outputString instanceof \ParserOutput ) {
			$outputString = $outputString->getText();
		}
		$output->addHTML( $outputString );
	}

	/**
	 * Return the User object for this profile.
	 *
	 * @param mixed $audience
	 * @param User|null $user
	 *
	 * @return mixed User
	 */
	public function getUser( $audience = RevisionRecord::FOR_PUBLIC, User $user = null ) {
		return $this->user;
	}

	/**
	 * Return the User object for who created this profile.(The user, technically.)
	 *
	 * @param mixed $audience
	 * @param User|null $user
	 *
	 * @return User
	 */
	public function getCreator( $audience = RevisionRecord::FOR_PUBLIC, User $user = null ) {
		return $this->user;
	}

	/**
	 * Shortcut method to retrieving the user's profile page preference
	 *
	 * @return bool True if profile page is preferred, false if wiki is preferred.
	 */
	public function isProfilePreferred() {
		return $this->profile->getProfileTypePreference();
	}

	/**
	 * Shortcut method to retrieving the user's comment page preference
	 *
	 * @return bool True if profile comment page is preferred, false if wiki is preferred.
	 */
	public function isCommentsPreferred() {
		return $this->profile->getCommentTypePreference();
	}

	/**
	 * True if we are not on a subpage, and if we are in the basic User namespace,
	 * or either of the custom UserProfile/UserWiki namespaces.
	 *
	 * @param Title|null $title object to check instead of the assumed.
	 * @return bool
	 */
	public function isUserPage( $title = null ) {
		if ( $title === null ) {
			$title = $this->getTitle();
		}
		return $title->getNamespace() === NS_USER;
	}

	/**
	 * True if we are viewing a user_talk namespace page.
	 *
	 * @param Title|null $title [Optional] Title object to check instead of the assumed.
	 * @return bool
	 */
	public function isUserTalkPage( $title = null ) {
		if ( $title === null ) {
			$title = $this->getTitle();
		}
		return $title->getNamespace() === NS_USER_TALK;
	}

	/**
	 * True if we need to render the user's profile page.
	 *
	 * @param Title|null $title [Optional] Title object to check instead of the assumed.
	 * @return bool
	 */
	public function isProfilePage( $title = null ) {
		if ( $title === null ) {
			$title = $this->getTitle();
		}
		return $title->getNamespace() === NS_USER_PROFILE;
	}

	/**
	 * Is the action for this page 'view'?
	 *
	 * @return bool
	 */
	public function isActionView() {
		return $this->actionIsView;
	}

	/**
	 * Returns the title object for the user's page in the UserProfile namespace
	 *
	 * @return Title instance
	 */
	public function getUserProfileTitle() {
		return Title::makeTitle( NS_USER_PROFILE, $this->user->getName() );
	}

	/**
	 * Adjusts the links in the primary action bar on profile pages and user wiki pages.
	 *
	 * @param array &$links Structured info on what links will appear on the rendered page.
	 * @param Title $title Title of the page the user is on in the User or User_talk namespace.
	 * @return void
	 */
	public function customizeNavBar( &$links, $title ) {
		// Using $this->user will result in a bad User object in the case of MediaWiki #REDIRECT pages
		// since the context is switched without performing a HTTP redirect.
		$userName = self::resolveUsername( $title );

		if ( $userName === false ) {
			if ( $this->isProfilePage( $title ) ) {
				$links['views'] = [];
				unset( $links['namespaces']['userprofile_talk'] );
			}
			return;
		}

		$profileTitle = $this->isProfilePage( $title ) ? $title : Title::makeTitle( NS_USER_PROFILE, $userName );
		$userPageTitle = $this->isUserPage( $title ) ? $title : Title::makeTitle( NS_USER, $title->getDBKey() );
		$userTalkPageTitle = $this->isUserTalkPage( $title ) ? $title :
			Title::makeTitle( NS_USER_TALK, $title->getDBKey() );

		$links['namespaces'] = [];

		if ( $this->isProfilePage( $title ) ) {
			// Reset $links to prevent hidden article.
			$links['namespaces'] = [];
			$links['views'] = [];
			$links['actions'] = [];
			$links['variants'] = [];
		}

		// Build Link for Profile
		$links['namespaces']['userprofile'] = [
			'class' => $this->isProfilePage( $title ) ? 'selected' : '',
			'href' => $profileTitle->getFullURL(),
			'text' => wfMessage( 'userprofiletab' )->text(),
			'primary' => true
		];

		// Build Link for User Page
		$class = [];
		if ( $this->isUserPage( $title ) ) {
			$class[] = 'selected';
		}
		if ( !$userPageTitle->isKnown() ) {
			$class[] = 'new';
		}
		$links['namespaces']['user'] = [
			'class' => implode( ' ', $class ),
			'text' => wfMessage( 'nstab-' . $userPageTitle->getNamespaceKey( '' ) )->text(),
			'href' => $this->profile->getUserPageUrl( $userPageTitle ),
			'primary' => true
		];

		// Build Link for User Talk Page
		$class = [];
		if ( $this->isUserTalkPage( $title ) ) {
			$class[] = 'selected';
		}
		if ( !$userTalkPageTitle->isKnown() ) {
			$class[] = 'new';
		}
		$links['namespaces']['user_talk'] = [
			'class' => implode( ' ', $class ),
			'text' => wfMessage( 'talk' )->text(),
			'href' => $this->profile->getTalkPageUrl( $userTalkPageTitle ),
			'primary' => true
		];

		$links['views']['contribs'] = [
			'class' => false,
			'text' => wfMessage( 'contributions' )->text(),
			'href' => SpecialPage::getTitleFor( 'Contributions', $userName )->getFullURL(),
		];
	}

	/**
	 * Gets an md5 hash for gravatar URLs
	 *
	 * @param string $email User's email address
	 * @return string md5 hash of the email address
	 */
	private static function emailToMD5Hash( $email ) {
		return md5( strtolower( trim( $email ) ) );
	}

	/**
	 * Prints a gravatar image tag for a user
	 *
	 * @param null $parser - Not Used but passed by MW
	 * @param int $size the square size of the avatar to display
	 * @param string $email email Address OR md5 Hash of user's email address
	 * @param string $userName the user's username
	 * @param string $attributeString additional html attributes to include in the IMG tag
	 * @return array the HTML fragment containing a IMG tag
	 */
	public static function userAvatar( $parser, $size, $email, $userName, $attributeString = '' ) {
		$size = (int)$size;
		$userName = htmlspecialchars( $userName, ENT_QUOTES );

		// Determine if we have a hash or an email address that needs to be hashed
		if ( strlen( $email ) != 32 && !ctype_xdigit( $email ) ) {
			$email = self::emailToMD5Hash( $email );
		}

		return [
			"<img src='//www.gravatar.com/avatar/" . $email . "?d=mm&amp;s=$size' height='$size' width='$size' alt='" .
			wfMessage( 'avataralt', $userName )->escaped() . "' $attributeString />",
			'isHTML' => true,
		];
	}

	/** Get a username from title */
	public static function resolveUsername( Title $title ): string {
		$username = $title->getText();
		if ( strpos( $username, '/' ) > 0 ) {
			$username = explode( '/', $username );
			$username = array_shift( $username );
			$canonical = MediaWikiServices::getInstance()->getUserNameUtils()->getCanonical( $username );
			$username = $canonical ?: $username;
		}

		return $username;
	}

	/**
	 * Performs the work for the parser tag that displays the groups to which a user belongs
	 *
	 * @param Parser &$parser parser reference
	 * @return mixed array with HTML string at index 0 or an HTML string
	 */
	public function groupList( &$parser ) {
		$groups = $this->userGroupManager->getUserEffectiveGroups( $this->user );
		if ( count( $groups ) == 0 ) {
			return '';
		}

		$specialListUsersTitle = SpecialPage::getTitleFor( 'Listusers' );
		$html = '<ul class="grouptags">';
		foreach ( $groups as $group ) {
			if ( in_array( $group, self::HIDDEN_GROUPS, true ) ||
				( $group === "sysop" && in_array( "wiki_guardian", $groups ) ) ) {
				continue;
			}
			$groupMessage = new Message( 'group-' . $group );
			if ( $groupMessage->exists() ) {
				$html .= '<li>' . $this->linkRenderer->makeKnownLink(
					$specialListUsersTitle,
					$groupMessage->text(),
					[],
					[ 'group' => $group ]
					) . '</li>';
			} else {
				// Legacy fall back to make the group name appear pretty.
				// This handles cases of user groups that are central to one wiki and are not localized.
				$html .= '<li>' . mb_convert_case(
					str_replace( "_", " ", htmlspecialchars( $group ) ),
					MB_CASE_TITLE,
					"UTF-8"
					) . '</li>';
			}
		}
		// Check the rights of the person viewing this page.
		$cGroups = $this->userGroupManager->getGroupsChangeableBy( $this->getContext()->getUser() );
		if ( !empty( $cGroups['add'] ) || !empty( $cGroups['remove'] ) ) {
			$link = $this->linkRenderer->makeKnownLink(
				Title::newFromText( 'Special:UserRights/' . $this->user->getName() ),
				new HtmlArmor( self::PENCIL_ICON_SVG ),
				[ 'class' => 'is-icon' ]
			);
			$html .= "<li class=\"edit\">$link</li>";
		}
		$html .= '</ul>';

		return [ $html, 'isHTML' => true ];
	}

	/**
	 * Performs the work for the parser tag that displays the user's location.
	 *
	 * @param Parser &$parser parser reference
	 * @return mixed array with HTML string at index 0 or an HTML string
	 */
	public function location( &$parser ) {
		$location = $this->profile->getLocation();

		return [
			array_map( 'htmlentities', $location ),
			'isHTML' => true,
		];
	}

	/**
	 * Performs the work for the parser tag that displays the user's "About Me" text
	 *
	 * @param Parser &$parser Parser reference.
	 * @param string $field Field name to retrieve.
	 * @return mixed array with HTML string at index 0 or an HTML string
	 */
	public function fieldBlock( &$parser, $field ) {
		return [ $this->profile->getFieldHtml( $field ), 'isHTML' => true ];
	}

	/**
	 * Generate Profile Links HTML
	 *
	 * @param array $profileLinks Profile Links
	 * @return string HTML
	 */
	public static function generateProfileLinks( $profileLinks ) {
		$html = '<ul class="profilelinks">';
		if ( count( $profileLinks ) ) {
			foreach ( $profileLinks as $service => $text ) {
				if ( !empty( $text ) ) {
					$escapedText = htmlspecialchars( $text, ENT_QUOTES | ENT_HTML5 );
					$profileLink = ProfileData::getExternalProfileLink( $service, $text );
					$item = "<li class='{$service}' title='{$service}: {$escapedText}'>";
					$item .= self::generateProfileTooltipHTML( $service, $escapedText, $profileLink );
					$item .= '</li>';
					$html .= $item;
				}
			}
		}
		$html .= '</ul>';
		return $html;
	}

	/**
	 * Creates the HTML for profile links that have a tooltip
	 *
	 * @return string HTML string
	 */
	private static function generateProfileTooltipHTML( $serviceName, $escapedText, $profileLink ) {
		if ( $profileLink !== false ) {
			return Html::element( 'a', [ 'alt' => '','href' => $profileLink, 'target' => '_blank' ] );
		}

		$item = "<a class='profile-icon'></a>";
		$item .= "<div class=\"profile-icon-tooltip\"><div class=\"profile-tooltip-service\">" .
			wfMessage( 'profile-' . $serviceName . '-name' )->text() .
			": </div><div class=\"profile-text\">{$escapedText}</div><button data-profile-text=\"$escapedText\"><i class=\"far fa-copy\"></i></button></div>";

		return $item;
	}

	/**
	 * Performs the work for the parser tag that displays a user's links to other gaming profiles.
	 *
	 * @param Parser|null &$parser parser reference
	 * @return mixed array with HTML string at index 0 or an HTML string
	 */
	public function profileLinks( &$parser = null ) {
		return [
			$this->profile->getProfileLinksHtml(),
			'isHTML' => true
		];
	}

	/**
	 * Performs the work for the parser tag that displays the user's chosen favorite wiki
	 *
	 * @param Parser &$parser parser reference
	 * @return mixed array with HTML string at index 0 or an HTML string
	 */
	public function favoriteWiki( &$parser ) {
		$wiki = $this->profile->getFavoriteWiki();
		if ( empty( $wiki ) ) {
			return '';
		}

		$imgPath = $this->wikiVariablesDataService->getVarValueByName( 'wgGpAvatarImage', $wiki['wiki_id'], false, '' );

		$linkContent = !empty( $imgPath ) && !empty( $wiki['md5_key'] ) ?
			Html::element(
				'img',
				[ 'src' => $imgPath, 'height' => 118, 'width' => 157, 'alt' => $wiki['wiki_name'] ]
			) :
			htmlspecialchars( $wiki['wiki_name'] );

		$title = Title::newFromText( 'UserProfile:' . $this->user->getTitleKey() );
		$link = $wiki['wiki_url'] . $title->getLocalURL();

		$html = wfMessage( 'favoritewiki' )->plain() . "<br/><a target='_blank' href='{$link}'>$linkContent</a>";

		return [ $html, 'isHTML' => true, ];
	}

	/**
	 * Performs the work for the parser tag that displays user statistics.
	 * The numbers themselves are pulled from the Cheevos API.
	 *
	 * @return string generated HTML fragment
	 */
	public function userStats() {
		$stats = [];
		$wikisEdited = 0;
		try {
			$stats = Cheevos::getStatProgress( [ 'global' => true ], $this->user );
			$stats = CheevosHelper::makeNiceStatProgressArray( $stats );
		} catch ( CheevosException $e ) {
			wfDebug( "Encountered Cheevos API error getting Stat Progress." );
		}
		try {
			$wikisEdited = (int)Cheevos::getUserSitesCountByStat( $this->user, 'article_edit' );
		} catch ( CheevosException $e ) {
			wfDebug( "Encountered Cheevos API error getting getUserSitesCountByStat." );
		}

		// Keys are message keys fed to wfMessage().
		// Values are numbers or an array of sub-stats with a number at key 0.
		$userId = $this->user->getId();
		if ( !empty( $stats ) ) {
			$statsOutput = [
				'wikisedited' => $wikisEdited,
				'totalcontribs' => [
					'totalcreations' =>
						( isset( $stats[$userId]['article_create'] ) ? $stats[$userId]['article_create']['count'] : 0 ),
					'totaledits' =>
						( isset( $stats[$userId]['article_edit'] ) ? $stats[$userId]['article_edit']['count'] : 0 ),
					'totaldeletes' =>
						( isset( $stats[$userId]['article_delete'] ) ? $stats[$userId]['article_delete']['count'] : 0 ),
					'totalpatrols' =>
						( isset( $stats[$userId]['admin_patrol'] ) ? $stats[$userId]['admin_patrol']['count'] : 0 ),
				],
				// data for these fills in below
				'localrank' => '',
				'globalrank' => '',
				'totalfriends' => '',
			];
		} else {
			$statsOutput = [
				'achievementsearned' => 0,
				'wikisedited' => 0,
				'totalcontribs' => [
					'totalcreations' => 0,
					'totaledits' => 0,
					'totaldeletes' => 0,
					'totalpatrols' => 0,
				],
			];
		}

		try {
			$statsOutput['localrank'] = Cheevos::getUserPointRank(
				$this->user,
				$this->config->has( 'dsSiteKey' ) ? $this->config->get( 'dsSiteKey' ) : null
			);
			$statsOutput['globalrank'] = Cheevos::getUserPointRank( $this->user );

			if ( empty( $statsOutput['localrank'] ) ) {
				unset( $statsOutput['localrank'] );
			}
			if ( empty( $statsOutput['globalrank'] ) ) {
				unset( $statsOutput['globalrank'] );
			}
		} catch ( CheevosException $e ) {
			wfDebug( __METHOD__ . ": Caught CheevosException - " . $e->getMessage() );
		}

		$statsOutput['totalfriends'] = FriendDisplay::count( null, $this->user->getId() );

		return $this->generateStatsDL( $statsOutput );
	}

	/**
	 * Recursive function for parsing out and stringifying the stats array above
	 *
	 * @param mixed $input arrays will generate a new list, other values will be directly returned
	 * @return string html DL fragment or $input if it is not an array
	 */
	public function generateStatsDL( $input ) {
		$lang = $this->getContext()->getSkin()->getLanguage();

		// just a simple value
		if ( is_numeric( $input ) ) {
			return $lang->formatNum( $input );
		}

		if ( $input === null ) {
			return '0';
		}

		if ( !is_array( $input ) ) {
			return $input;
		}

		$output = "<dl>";
		foreach ( $input as $msgKey => $value ) {
			if ( is_string( $msgKey ) ) {
				$output .= "<dt>" . wfMessage(
					$msgKey,
					$this->user->getId(),
					$this->getContext()->getUser()->getId()
					)->plain() . "</dt>";
			}
			// check for sub-list, if there is one
			if ( is_array( $value ) ) {
				// might have a roll-up total for the sub-list's header
				if ( array_key_exists( 0, $value ) ) {
					$output .= "<dd>" . $this->generateStatsDL( $value[0] ) . "</dd>";
					// Discard the value for the sublist header so
					// it isn't printed a second time as a member of the sublist
					array_shift( $value );
				}
				// generate the sub-list
				$output .= $this->generateStatsDL( $value );
			} else {
				// not a sub-list, just add a plain value
				$output .= "<dd>" . $this->generateStatsDL( $value ) . "</dd>";
			}
		}
		$output .= "</dl>";
		return $output;
	}

	/**
	 * Display the icons of the recent achievements the user has earned, for the sidebar
	 *
	 * @param Parser &$parser parser reference
	 * @param string $type type of query. one of: local, master (default)
	 * @param int $limit maximum number to display
	 * @return array
	 */
	public function recentAchievements( &$parser, $type = 'special', $limit = 0 ) {
		$dsSiteKey = $this->config->has( 'dsSiteKey' ) ? $this->config->get( 'dsSiteKey' ) : null;
		$achievements = [];
		try {
			$achievements = Cheevos::getAchievements( $dsSiteKey );
		} catch ( CheevosException $e ) {
			wfDebug( "Encountered Cheevos API error getting site achievements." );
		}
		if ( $type === 'special' ) {
			try {
				foreach ( Cheevos::getAchievements() as $achievement ) {
					$achievements[$achievement->getId()] = $achievement;
				}
			} catch ( CheevosException $e ) {
				wfDebug( "Encountered Cheevos API error getting all achievements." );
			}
		}
		$achievements = CheevosAchievement::correctCriteriaChildAchievements( $achievements );

		$progresses = [];
		if ( $type === 'general' ) {
			try {
				$progresses = Cheevos::getAchievementProgress(
					[
						'site_key' => $dsSiteKey,
						'earned' => true,
						'special' => false,
						'shown_on_all_sites' => true,
						'limit' => (int)$limit,
					],
					$this->user
				);
			} catch ( CheevosException $e ) {
				wfDebug( "Encountered Cheevos API error getting Achievement Progress." );
			}
		}

		if ( $type === 'special' ) {
			try {
				$progresses =
					Cheevos::getAchievementProgress(
						[
							'earned' => true,
							'special' => true,
							'shown_on_all_sites' => true,
							'limit' => (int)$limit,
						],
						$this->user
					);
			} catch ( CheevosException $e ) {
				wfDebug( "Encountered Cheevos API error getting Achievement Progress." );
			}
		}
		[ $achievements, $progresses ] =
			CheevosAchievement::pruneAchievements( [ $achievements, $progresses ], true, true );

		if ( empty( $progresses ) ) {
			return [ '', 'isHTML' => true ];
		}

		$output = '';
		if ( count( $progresses ) ) {
			$output .= '<h4>' . $parser->recursiveTagParse( wfMessage( 'achievements-' . $type )->text() ) . '</h4>';
		}
		foreach ( $progresses as $progress ) {
			if ( !isset( $achievements[$progress->getAchievement_Id()] ) ) {
				continue;
			}
			$ach = $achievements[$progress->getAchievement_Id()];
			$output .= Html::rawElement(
				'div',
				[
					'class'	=> [ 'icon', $type ]
				],
				Html::element(
					'img',
					[
						'src'	=> $ach->getImageUrl(),
						'title'	=> $ach->getName() . "\n" . $ach->getDescription()
					]
				) . ( $type === 'special' ? Html::rawElement(
					'span',
					[ 'title' => $ach->getName( $progress->getSite_Key() ) . "\n" . $ach->getDescription() ],
					$ach->getName( $progress->getSite_Key() )
				) : null )
			);
		}
		return [ $output, 'isHTML' => true ];
	}

	/**
	 * Performs the work for the parser tag that displays the user's level (based on wikipoints)
	 *
	 * @param mixed &$parser parser reference
	 * @return mixed array with HTML string at index 0 or an HTML string
	 */
	public function userLevel( &$parser ) {
		$userPoints = PointsDisplay::getWikiPointsForRange( $this->user );

		$levelDefinitions = $this->config->get( 'PointsLevels' );

		if ( !is_array( $levelDefinitions ) || empty( $levelDefinitions ) ) {
			return '';
		}

		$html = '';
		foreach ( $levelDefinitions as $tier ) {
			// assuming that the definitions array is sorted by level ASC, overwriting previous iterations
			if ( $userPoints >= $tier['points'] ) {
				$html = Html::element(
					'img',
					[
						'class' => 'level',
						'title' => $tier['text'],
						'src' => $tier['image_large']
					]
				);
			} else {
				break;
			}
		}

		return [ $html, 'isHTML' => true, ];
	}

	/**
	 * Parser hook function that inserts either an "edit profile" button or a "add/remove friend" button
	 *
	 * @param Parser &$parser
	 * @return array with html as the first element
	 */
	public function editOrFriends( &$parser ) {
		$html = FriendDisplay::addFriendButton( $this->getUser(), $this->getContext()->getUser() );

		if ( $this->profile->isViewingSelf() ) {
			$html .= Html::element(
				'button',
				[
					'data-href' =>
						SpecialPage::getSafeTitleFor( 'Preferences' )->getFullURL() . '#mw-prefsection-personal',
					'class' => 'linksub wds-button wds-is-secondary'
				],
				wfMessage( 'cp-editprofile' )->plain()
			);
		}

		return [ $html, 'isHTML' => true, ];
	}

	/**
	 * Defines the HTML structure of the profile page.
	 *
	 * @return string
	 */
	protected function profileLayout() {
		$classes = false;
		if ( !empty( $this->user ) && $this->user->getId() ) {
			$classes = $this->subscription->getFlairClasses( $this->user->getId() );
			if ( empty( $classes ) ) {
				$classes = false;
				// Enforce sanity.
			}
		}

		$tabsMarkup = $this->getTabsMarkup();

		return '
<div class="curseprofile" data-user_id="' . $this->user->getId() . '">
	<div class="leftcolumn">
		' . $tabsMarkup . '
		<div class="userinfo borderless section">
			<div class="mainavatar">{{#avatar: 96 | ' .
			( $this->user->getBlock() !== null ? '' : self::emailToMD5Hash( $this->user->getEmail() ) ) .
			' | ' . $this->user->getName() . '}}</div>
			<div class="profile-info">
				<div class="headline">
					<h1' . ( $classes !== false ? ' class="' . implode( ' ', $classes ) . '"' : '' ) .
			'>' . $this->user->getName() . '</h1>
					{{#groups:}}
				</div>
				<div id="profile-user-fields">
					<div id="profile-location" data-field="location">{{#profilefield:location}}</div>
					{{#profilelinks:}}
				</div>
				<div id="profile-aboutme" data-field="aboutme">
					{{#profilefield:aboutme}}
				</div>
			</div>
		</div>
		<div class="activity section">
			<p class="rightfloat">[[Special:Contributions/' . $this->user->getName() . '|' .
			wfMessage( 'contributions' )->text() . ']]</p>
			<h3>' . wfMessage( 'cp-recentactivitysection' ) . '</h3>
			{{#recentactivity: ' . $this->user->getId() . '}}
		</div>
		<div class="comments section">
			<p class="rightfloat">[[Special:CommentBoard/' . $this->user->getId() .
			'/' . $this->user->getTitleKey() . '|' .
			wfMessage( 'commentarchivelink' )->plain() . ']]</p>
			<h3>' . wfMessage( 'cp-recentcommentssection' ) . '</h3>
			{{#comments: ' . $this->user->getId() . '}}
		</div>
	</div>
	<div class="rightcolumn">
		<div class="borderless section">
			<div class="rightfloat">
				<div class="score">{{#Points:' . $this->user->getName() . '|1|global|badged}}</div>
				<div class="level">{{#userlevel:}}</div>
				<div>{{#editorfriends:}}</div>
			</div>
			<div class="favorite">{{#favwiki:}}</div>
		</div>
		<div class="section stats">
			<h3>' . wfMessage( 'cp-statisticssection' )->plain() . '</h3>
			<USERSTATS>
		</div>
		<div class="section friends">
			<h3>' . wfMessage( 'cp-friendssection' )->plain() . '</h3>
			{{#friendlist: ' . $this->user->getId() . '}}
			<div style="float: right;">' .
			wfMessage(
				'cp-friendssection-all',
				$this->user->getId(),
				$this->getContext()->getUser()->getId(),
				$this->user->getTitleKey()
			)->plain() . '</div>
		</div>
		<div class="section achievements">
			<h3>' . wfMessage( 'cp-achievementssection' )->plain() . '</h3>
			{{#achievements:general}}
			{{#achievements:special}}
			<div style="clear: both;"></div>
			<div style="float: right; clear: both;">' .
			wfMessage( 'cp-achievementssection-all', $this->user->getName() )->plain() . '</div>
		</div>
	</div>
	{{#if: ' . ( $this->user->getBlock() !== null ? 'true' : '' ) . ' | <div class="blocked"></div> }}
</div>
__NOTOC__
__NOINDEX__
';
	}

	/**
	 * Defines the HTML structure of the profile page for mobile devices.
	 *
	 * @return string
	 */
	protected function mobileProfileLayout() {
		return '
<div class="curseprofile" id="mf-curseprofile" data-user_id="' . $this->user->getId() . '">
		<div class="userinfo section">
			<div class="mainavatar">{{#avatar: 96 | ' .
			( $this->user->getBlock() !== null ? '' : self::emailToMD5Hash( $this->user->getEmail() ) ) .
			' | ' . $this->user->getName() . '}}</div>
			<div class="usericons rightfloat">
				<div class="score">{{#Points:' . $this->user->getName() . '|1|global|badged}}</div>
				{{#profilelinks:}}
			</div>
		</div>
		<h1>' . wfMessage( 'cp-mobile-aboutme' ) . '</h1>
		{{#profilefield:aboutme}}
		<h1>' . wfMessage( 'cp-mobile-groups' ) . '</h1>
		{{#groups:}}
		<h1>' . wfMessage( 'cp-statisticssection' ) . '</h1>
		<USERSTATS>
		<h1>' . wfMessage( 'cp-friendssection' )->plain() . '</h1>
		{{#friendlist: ' . $this->user->getId() . '}}
		<div style="float: right;">' .
			wfMessage(
				'cp-friendssection-all',
				$this->user->getId(),
				$this->getContext()->getUser()->getId(),
				$this->user->getTitleKey()
			)->plain() . '</div>
		<h1>' . wfMessage( 'cp-achievementssection' )->plain() . '</h1>
		<div class="section achievements">
			{{#achievements:general}}
			{{#achievements:special}}
			<div style="clear: both;"></div>
			<div style="float: right; clear: both;">' .
			wfMessage( 'cp-achievementssection-all', $this->user->getName() )->plain() . '</div>
		</div>
		<h1>' . wfMessage( 'cp-recentactivitysection' ) . '</h1>
		<p>[[Special:Contributions/' . $this->user->getName() . '|' . wfMessage( 'contributions' )->text() . ']]</p>
		{{#recentactivity: ' . $this->user->getId() . '}}
		<div class="comments section">
		    <h1>' . wfMessage( 'cp-recentcommentssection' ) . '</h1>
		    <p>[[Special:CommentBoard/' . $this->user->getId() . '/' . $this->user->getTitleKey() . '|' .
			wfMessage( 'commentarchivelink' ) . ']]</p>
		    {{#comments: ' . $this->user->getId() . '}}
		</div>
	{{#if: ' . ( $this->user->getBlock() !== null ? 'true' : '' ) . ' | <div class="blocked"></div> }}
</div>
__NOTOC__
__NOINDEX__
';
	}

	protected function getTabsMarkup() {
		if ( $this->getContext()->getSkin()->getSkinName() !== 'fandomdesktop' ) {
			return '';
		}

		$userName = $this->user->getName();
		$queryParam = $this->userOptionsLookup->getIntOption( $this->user, 'profile-pref' ) ? '|profile=no' : '';

		$userTalkTab = $this->userOptionsLookup->getIntOption( $this->user, 'comment-pref' ) !== 1 ?
			'<li class="user-profile-navigation__link">
				[{{fullurl:User_talk:' . $userName . $queryParam . '}} ' .
			wfMessage( 'userprofile-userprofilenavigation-link-user-talk' )->plain() . ']
			</li>' : '';

		return '<ul class="user-profile-navigation">
			<li class="user-profile-navigation__link is-active">
				[[UserProfile:' . $userName . '|' .
			wfMessage( 'userprofile-userprofilenavigation-link-profile' )->plain() . ']]
			</li>
			<li class="user-profile-navigation__link">
				[{{fullurl:User:' . $userName . $queryParam . '}} ' .
			wfMessage( 'userprofile-userprofilenavigation-link-about' )->plain() . ']
			</li>'
			. $userTalkTab . '
			<li class="user-profile-navigation__link">
				[[Special:Contributions/' . $userName . '|' .
			wfMessage( 'userprofile-userprofilenavigation-link-contributions' )->plain() . ']]
			</li>
			<li class="user-profile-navigation__link">
				[[Special:UserProfileActivity/' . $userName . '|' .
			wfMessage( 'userprofile-userprofilenavigation-link-activity' )->plain() . ']]
			</li>
		</ul>';
	}
}
