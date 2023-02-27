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

use Fandom\Includes\Util\UrlUtilityService;
use Fandom\WikiConfig\WikiVariablesDataService;
use Html;
use ManualLogEntry;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserOptionsLookup;
use MediaWiki\User\UserOptionsManager;
use MWException;
use RequestContext;
use Sanitizer;
use Title;
use User;

/**
 * Class for reading and saving custom user-set profile data
 */
class ProfileData {
	/**
	 * Basic profile fields.
	 *
	 * @var array
	 */
	private const BASIC_PROFILE_FIELDS = [
		'profile-aboutme',
		'profile-favwiki',
		'profile-location'
	];

	/**
	 * External Profile Fields
	 *
	 * @var array
	 */
	public const EXTERNAL_PROFILE_FIELDS = [
		'profile-link-battlenet',
		'profile-link-discord',
		'profile-link-facebook',
		'profile-link-psn',
		'profile-link-reddit',
		'profile-link-steam',
		'profile-link-twitch',
		'profile-link-twitter',
		'profile-link-vk',
		'profile-link-xbl'
	];

	/**
	 * Validation matrixes for external profiles.
	 *
	 * @var array
	 */
	private const EXTERNAL_PROFILES = [
		'battlenet'	=> [
			'user'	=> '^(\w{3,12}#\d{3,6})$'
		],
		'discord'	=> [
			'user'	=> '^([^@#:]{2,32}#\d{4,6})$'
		],
		'facebook'	=> [
			'url'	=> '^https?://(?:www\.)?facebook\.com/([\w\.]+)$',
			'user'	=> '^([\w\.]+)$',
			'link'	=> 'https://www.facebook.com/%s'
		],
		'psn'	=> [
			'url'	=> '^https?://(?:www\.)?psnprofiles\.com/(\w+?)/?$',
			'user'	=> '^(\w+?)$',
			'link'	=> 'https://psnprofiles.com/%s'
		],
		'reddit'	=> [
			'url'	=> '^https?://(?:www\.)?reddit\.com/u(?:ser)?/([\w\-_]{3,20})/?$',
			'user'	=> '^([\w\-_]{3,20})$',
			'link'	=> 'https://www.reddit.com/user/%s'
		],
		'steam'	=> [
			'url'	=> '^https?://(?:www\.)?steamcommunity\.com/id/([\w-]+?)/?$',
			'user'	=> '^([\w-]+?)$',
			'link'	=> 'https://steamcommunity.com/id/%s'
		],
		'twitch'	=> [
			'url'	=> '^https?://(?:www\.)?twitch\.tv/([a-zA-Z0-9\w_]{3,24})/?$',
			'user'	=> '^([a-zA-Z0-9\w_]{3,24})$',
			'link'	=> 'https://www.twitch.tv/%s'
		],
		'twitter'	=> [
			'url'	=> '^https?://(?:www\.)?twitter\.com/@?(\w{1,15})$',
			'user'	=> '^@?(\w{1,15})$',
			'link'	=> 'https://www.twitter.com/@%s'
		],
		'vk'	=> [
			'url'	=> '^https?://(?:www\.)?vk\.com/([\w\.]+)$',
			'user'	=> '^([\w\.]+)$',
			'link'	=> 'https://vk.com/%s'
		],
		'xbl'	=> [
			'url'	=> '^https?://(?:live|account)\.xbox\.com/..-../Profile\?gamerTag=(\w+?)&?$',
			'user'	=> '^(\w+?)$',
			'link'	=> 'https://account.xbox.com/en-US/Profile?gamerTag=%s'
		]
	];

	private UserOptionsLookup $userOptionsLookup;
	private UserOptionsManager $userOptionsManager;
	private User $user;

	/**
	 * Create a new ProfileData instance
	 *
	 * @param int|User $user local user ID or User instance
	 */
	public function __construct( $user ) {
		$services = MediaWikiServices::getInstance();
		$this->userOptionsLookup = $services->getUserOptionsLookup();
		$this->userOptionsManager = $services->getUserOptionsManager();
		if ( $user instanceof User ) {
			$this->user = $user;
		} else {
			$userId = (int)$user;
			$userFactory = MediaWikiServices::getInstance()->getUserFactory();
			$this->user = $userId < 1 ? $userFactory->newAnonymous() : $userFactory->newFromId( $userId );
		}
	}

	/**
	 * Return basic plus external profile fields.
	 *
	 * @return array Edit Profile Fields
	 */
	public static function getValidEditFields() {
		return array_merge( self::BASIC_PROFILE_FIELDS, self::EXTERNAL_PROFILE_FIELDS );
	}

	/**
	 * Get an URL to an external profile.
	 *
	 * @param string $service Service Name
	 * @param string $text Text Replacement/User Name
	 * @return string|bool URL to the external profile or false.
	 */
	public static function getExternalProfileLink( $service, $text ) {
		if ( !isset( self::EXTERNAL_PROFILES[$service]['link'] ) ) {
			return false;
		}
		return sprintf( self::EXTERNAL_PROFILES[$service]['link'], urlencode( $text ) );
	}

	public function getProfilePageUrl(): string {
		return Title::makeTitle( NS_USER_PROFILE, $this->user->getTitleKey() )->getFullURL();
	}

	/**
	 * Get the url for the User page based on preferences
	 *
	 * @param Title $title
	 * @return string
	 */
	public function getUserPageUrl( Title $title ): string {
		return $this->getFullURL(
			$title,
			$this->getProfileTypePreference() ? [ 'profile' => 'no' ] : []
		);
	}

	/**
	 * Get the url for the User Talk Page based on preferences
	 *
	 * @param Title $title
	 * @return string
	 */
	public function getTalkPageUrl( $title ) {
		$args = [];
		if ( $this->getCommentTypePreference() ) {
			$args['profile'] = 'no';
		}

		return $this->getFullURL( $title, $args );
	}

	/**
	 * Get the full url from Title with the provided arguments
	 *
	 * @param Title $title
	 * @param array $args
	 * @return string
	 */
	private function getFullURL( $title, $args ) {
		if ( !$title->isKnown() ) {
			$args['redlink'] = 1;
		}

		return $title->getFullURL( $args );
	}

	/**
	 * Can the given user edit this profile profile?
	 *
	 * @param mixed $performer User, the performer that needs to make changes.
	 * @return mixed Boolean true if allowed, otherwise error message string to display.
	 */
	public function canEdit( $performer ) {
		if ( $performer->isBlocked() ) {
			return 'profile-blocked';
		}

		if ( $performer->isAllowed( 'profile-moderate' ) ) {
			// Moderators can always edit a profile to remove spam.
			return true;
		}

		if ( $performer->getId() !== $this->user->getId() ) {
			// Users can only edit their own profiles if they are not a moderator.
			return 'no-perm-profile-moderate';
		}

		if ( MediaWikiServices::getInstance()->getMainConfig()->get( 'EmailAuthentication' ) &&
			(
				!$performer->getEmailAuthenticationTimestamp() ||
				!Sanitizer::validateEmail( $performer->getEmail() )
			)
		) {
			// If email authentication is turned on and their email address is invalid then prevent editing.
			return 'email-auth-required';
		}

		return true;
	}

	/**
	 * Get a the profile field text.
	 *
	 * @param string $field Field Name - Examples: aboutme, location, link_twitch
	 * @return string
	 */
	public function getField( string $field ): string {
		$field = 'profile-' . $field;
		if ( !in_array( $field, self::getValidEditFields() ) ) {
			throw new MWException( __METHOD__ . ': Invalid profile field.' );
		}
		return (string)$this->userOptionsLookup->getOption( $this->user, $field );
	}

	/**
	 * Set profile fields.
	 *
	 * @param string[] $fields [ 'aboutme' => 'value' ] Field Name - Examples: aboutme, location, link_twitch
	 * @param User $performer User who performed the action.
	 * @return void
	 */
	public function setFields( array $fields, User $performer ): void {
		foreach ( $fields as $field => $text ) {
			$field = 'profile-' . $field;
			if ( !in_array( $field, self::getValidEditFields() ) ) {
				throw new MWException( __METHOD__ . ': Invalid profile field (' . $field . ').' );
			}

			$this->userOptionsManager->setOption( $this->user, $field, $text );
		}
		$this->userOptionsManager->saveOptions( $this->user );

		// Add logs when option save succeed
		foreach ( $fields as $field => $text ) {
			$field = 'profile-' . $field;
			$this->logProfileChange( $field, $text, $this->user, $performer );
		}
	}

	/**
	 * Extracts the username from a profile link.
	 *
	 * @param string $service Name of service to validate.
	 * @param string $test Raw text to test for an URL or user name to extract.
	 * @return mixed False or validated string value.
	 */
	public static function validateExternalProfile( $service, $test ) {
		$service = strtolower( $service );

		if ( !isset( self::EXTERNAL_PROFILES[$service] ) ) {
			return false;
		}

		$patterns = self::EXTERNAL_PROFILES[$service];

		foreach ( $patterns as $pattern ) {
			$result = preg_match( "#" . str_replace( '#', '\#', $pattern ) . "#", $test, $matches );
			if ( $result > 0 && isset( $matches[1] ) && !empty( $matches[1] ) ) {
				return $matches[1];
			}
		}

		return false;
	}

	/**
	 * Performs the work for the parser tag that displays the user's "About Me" text
	 *
	 * @param string $field Field name to retrieve.
	 * @return mixed array with HTML string at index 0 or an HTML string
	 */
	public function getFieldHtml( $field ) {
		if ( !in_array( "profile-$field", self::getValidEditFields() ) ) {
			return '';
		}

		$fieldHtml = RequestContext::getMain()->getOutput()->parseAsContent( $this->getField( $field ) );

		$user = RequestContext::getMain()->getUser();
		if ( $this->canEdit( $user ) === true ) {
			if ( empty( $fieldHtml ) ) {
				$fieldHtml = Html::element(
					'em',
					[],
					wfMessage(
						( $this->isViewingSelf() ? 'empty-' . $field . '-text' : 'empty-' . $field . '-text-mod' )
					)
						->params( $this->user->getName(), $user->getName() )
						->text()
				);
			}

			$fieldHtml = Html::rawElement(
				'a',
				[
					'class'	=> 'rightfloat profileedit is-icon',
					'href'	=> '#',
					'title' => wfMessage( 'editfield-' . $field . '-tooltip' )->params( $this->user->getName() )->text()
				],
				ProfilePage::PENCIL_ICON_SVG
			) . $fieldHtml;
		}

		return $fieldHtml;
	}

	/**
	 * Performs the work for the parser tag that displays a user's links to other gaming profiles.
	 *
	 * @return mixed Array with HTML string at index 0 or an HTML string.
	 */
	public function getProfileLinksHtml() {
		$user = RequestContext::getMain()->getUser();

		$profileLinks = $this->getExternalProfiles();

		$html = "";
		if ( $this->canEdit( $user ) === true ) {
			if ( !count( $profileLinks ) ) {
				$html .= "" . Html::element(
					'em',
					[],
					wfMessage( ( $this->isViewingSelf() ? 'empty-social-text' : 'empty-social-text-mod' ) )
						->params( $this->user->getName(), $user->getName() )
						->text()
					) . "";
			}
			$html .= "" . Html::rawElement(
				'a',
				[
					'class'	=> 'rightfloat socialedit is-icon',
					'href'	=> '#',
					'title' => wfMessage( 'editfield-social-tooltip' )->plain()
				],
				ProfilePage::PENCIL_ICON_SVG
			);
		}

		$html .= ProfilePage::generateProfileLinks( $profileLinks );
		// Get all of the possible external profiles to shove into the data-field
		// for Javascript to know which ones to be able to edit.
		foreach ( self::EXTERNAL_PROFILES as $field => $unused ) {
			$fields[] = 'link-' . $field;
		}
		sort( $fields );
		$html = "<div id='profile-social' data-field='" . implode( " ", $fields ) . "'>" . $html . "</div>";

		return $html;
	}

	public function isViewingSelf(): bool {
		$performer = RequestContext::getMain()->getUser();
		return $performer && $performer->isRegistered() && $performer->getId() === $this->user->getId();
	}

	/**
	 * Log a profile change.
	 *
	 * @param string $section Section string of the profile.  Example: profile-aboutme
	 * @param string $comment Comment for the log, usually the text of the change.
	 * @param mixed $target User targeted for the action.
	 * @param mixed $performer User who performed the action.  Null to use the current user.
	 * @return void
	 */
	private function logProfileChange( string $section, string $comment, User $target, User $performer ): void {
		if ( strlen( $comment ) > 140 ) {
			$comment = substr( $comment, 0, 140 ) . "...";
		}

		// Insert an entry into the Log.
		$log = new ManualLogEntry( 'curseprofile', 'profile-edited' );
		$log->setPerformer( $performer );
		$log->setTarget( Title::makeTitle( NS_USER_PROFILE, $target->getName() ) );
		$log->setComment( $comment );
		$log->setParameters( [ '4:section' => $section ] );
		$logId = $log->insert();
		$log->publish( $logId );
	}

	/**
	 * Returns all the user's location profile data
	 *
	 * @return array Possibly including key: location
	 */
	public function getLocation(): array {
		$profile = [ 'location' => $this->userOptionsLookup->getOption( $this->user, 'profile-location' ) ];
		return array_filter( $profile );
	}

	/**
	 * Returns all the user's external social profiles.
	 *
	 * @return array Possibly including keys: Twitter, Facebook, Reddit, Steam, VK, XBL, PSN
	 */
	private function getExternalProfiles(): array {
		foreach ( self::EXTERNAL_PROFILES as $service => $data ) {
			$profile[$service] = self::validateExternalProfile(
				$service,
				$this->userOptionsLookup->getOption( $this->user, 'profile-link-' . $service )
			);
		}
		return array_filter( $profile );
	}

	/**
	 * Returns more complete info on the wiki chosen as the user's favorite
	 *
	 * @return array
	 */
	public function getFavoriteWiki() {
		$profileFavWiki = $this->userOptionsLookup->getOption( $this->user, 'profile-favwiki' );
		return $profileFavWiki ? self::getWikiSite( $profileFavWiki ) : [];
	}

	/**
	 * Get information about wiki sites from WikiVariables for searching.
	 *
	 * @param string $search Search Term
	 * @return array Search Results
	 */
	public static function getWikiSitesSearch( $search ): array {
		$wikis = self::getWikisFromCityList();
		$result = [];
		foreach ( $wikis as $wiki ) {
			$urlUtils = MediaWikiServices::getInstance()->getUrlUtils();
			$domain = $urlUtils->parse( (string)$wiki['wiki_url'] )['host'] ?? '';
			if (
				mb_stripos( $wiki['wiki_name_display'], $search ) === false &&
				mb_stripos( $domain, $search ) === false
			) {
				continue;
			}
			$result[] = $wiki;
			if ( count( $result ) >= 15 ) {
				break;
			}
		}

		return $result;
	}

	/**
	 * Returns the decoded wiki data available in redis
	 *
	 * @param string $siteKey md5 key for wanted site, or array of keys.
	 * @return array Wiki data
	 */
	public static function getWikiSite( string $siteKey ) {
		if ( empty( $siteKey ) ) {
			return [];
		}

		return self::getWikisFromCityList( $siteKey )[0] ?? [];
	}

	/**
	 * @param mixed $cityId
	 * @param array $info Element of array returned by getListOfWikisWithVar
	 * @return array Array used by Profile controller
	 */
	private static function convertCityInfoToSiteData( $cityId, $info ) {
		$lang = mb_strtoupper( $info['city_lang'] );
		$urlUtilityService = MediaWikiServices::getInstance()->getService( UrlUtilityService::class );
		return [
			'wiki_id' => $cityId,
			'wiki_name' => $info['city_title'],
			'wiki_name_display' => "{$info['city_title']} ($lang)",
			'wiki_url' => $urlUtilityService->forceHttps( $info['city_url'] ),
			'md5_key' => $info['value'],
		];
	}

	/**
	 * Get wikis from city_list by $dsSiteKey, or all wikis with a $dsSiteKey set.
	 * @return array
	 */
	private static function getWikisFromCityList( $siteKey = null ) {
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		return $cache->getWithSetCallback(
			$cache->makeGlobalKey( 'CurseProfile', 'wiki-info-v3', $siteKey ?? 'all' ),
			14400,
			function ( $oldValue, &$ttl, array &$setOpts ) use ( $siteKey ) {
				/** @var WikiVariablesDataService $wikiVariables */
				$wikiVariables = MediaWikiServices::getInstance()->getService( WikiVariablesDataService::class );
				$dsSiteKeyVar = $wikiVariables->getVariableInfo( null, 'dsSiteKey' );
				if ( empty( $dsSiteKeyVar ) ) {
					return [];
				}

				$wikis = $wikiVariables->getListOfWikisWithVar(
					$dsSiteKeyVar['variable_id'],
					$siteKey ? '=' : '!=',
					$siteKey ? json_encode( $siteKey ) : '',
					'$',
					0,
					$siteKey ? 1 : 5000
				)['result'];
				foreach ( $wikis as $cityId => $wiki ) {
					if ( empty( $wiki['value'] ) ) {
						continue;
					}
					$info[] = self::convertCityInfoToSiteData( $cityId, $wiki );
				}
				return $info ?? false;
			}
		) ?: [];
	}

	/**
	 * Returns true if the profile page should be used, false if the wiki should be used
	 *
	 * @return bool
	 */
	public function getProfileTypePreference() {
		// override preference for non existent user
		if ( $this->user->isAnon() ) {
			return false;
		}
		return $this->userOptionsLookup->getIntOption( $this->user, 'profile-pref' );
	}

	/**
	 * Returns true if the profile page should be used, false if the wiki should be used
	 *
	 * @return bool
	 */
	public function getCommentTypePreference(): bool {
		// override preference for non existent user
		if ( $this->user->isAnon() ) {
			return false;
		}
		return (bool)$this->userOptionsLookup->getIntOption( $this->user, 'comment-pref' );
	}
}
