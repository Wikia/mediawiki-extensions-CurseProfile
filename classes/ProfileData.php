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

use Hooks;
use Html;
use HydraCore;
use ManualLogEntry;
use MWException;
use Redis;
use RedisCache;
use RequestContext;
use Throwable;
use Title;
use User;

/**
 * Class for reading and saving custom user-set profile data
 */
class ProfileData {
	/**
	 * @var integer
	 */
	protected $user_id;

	/**
	 * @var object
	 */
	protected $user;

	/**
	 * Basic profile fields.
	 *
	 * @var array
	 */
	static private $basicProfileFields = [
		'profile-aboutme',
		'profile-favwiki',
		'profile-location'
	];

	/**
	 * External Profile Fields
	 *
	 * @var array
	 */
	static private $externalProfileFields = [
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
	static private $externalProfiles = [
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

	/**
	 * Create a new ProfileData instance
	 *
	 * @param User $user local user ID or User instance
	 */
	public function __construct($user) {
		if (is_a($user, 'User')) {
			$this->user = $user;
			$this->user_id = $user->getId();
		} else {
			$this->user_id = intval($user);
			if ($this->user_id < 1) {
				// if a user hasn't saved a profile yet, just use the default values
				$this->user_id = 0;
			}
			$this->user = User::newFromId($user);
		}
	}

	/**
	 * Return basic plus external profile fields.
	 *
	 * @return array	Edit Profile Fields
	 */
	public static function getValidEditFields() {
		return array_merge(self::$basicProfileFields, self::$externalProfileFields);
	}

	/**
	 * Get an URL to an external profile.
	 *
	 * @param  string $service Service Name
	 * @param  string $text    Text Replacement/User Name
	 * @return string|boolean	URL to the external profile or false.
	 */
	public static function getExternalProfileLink($service, $text) {
		if (!isset((self::$externalProfiles[$service]['link']))) {
			return false;
		}
		return sprintf(self::$externalProfiles[$service]['link'], urlencode($text));
	}

	/**
	 * Returns the canonical URL path to a user's profile.
	 *
	 * @return string
	 */
	public function getProfilePageUrl() {
		$title = Title::newFromText('UserProfile:' . $this->user->getTitleKey());
		return $title->getFullURL();
	}

	/**
	 * Get the url for the User page based on preferences
	 *
	 * @param  Title $title
	 * @return string
	 */
	public function getUserPageUrl($title) {
		$args = [];
		if ($this->getProfileTypePreference()) {
			$args['profile'] = 'no';
		}

		return $this->getFullURL($title, $args);
	}

	/**
	 * Get the url for the User Talk Page based on preferences
	 *
	 * @param  Title $title
	 * @return string
	 */
	public function getTalkPageUrl($title) {
		$args = [];
		if ($this->getCommentTypePreference()) {
			$args['profile'] = 'no';
		}

		return $this->getFullURL($title, $args);
	}

	/**
	 * Get the full url from Title with the provided arguments
	 *
	 * @param  Title $title
	 * @param  array $args
	 * @return string
	 */
	private function getFullURL($title, $args) {
		if (!$title->isKnown()) {
			$args['redlink'] = 1;
		}

		return $title->getFullURL($args);
	}

	/**
	 * Inserts curse profile fields into the user preferences form.
	 *
	 * @param  array &$preferences Data for HTMLForm to generate the Special:Preferences form
	 * @return void
	 */
	public static function insertProfilePrefs(&$preferences) {
		global $wgUser;

		$preferences['profile-pref'] = [
			'type' => 'select',
			'label-message' => 'profileprefselect',
			'section' => 'personal/info/public',
			'options' => [
				wfMessage('profilepref-profile')->plain() => 1,
				wfMessage('profilepref-wiki')->plain() => 0,
			],
		];

		$preferences['comment-pref'] = [
			'type' => 'select',
			'label-message' => 'commentprefselect',
			'section' => 'personal/info/public',
			'options' => [
				wfMessage('commentpref-profile')->plain() => 1,
				wfMessage('commentpref-wiki')->plain() => 0,
			],
		];

		$preferences['profile-favwiki-display'] = [
			'type' => 'text',
			'label-message' => 'favoritewiki',
			'section' => 'personal/info/public',
		];

		$preferences['profile-favwiki'] = [
			'type' => 'hidden',
			'section' => 'personal/info/public',
		];

		$preferences['profile-aboutme'] = [
			'type' => 'textarea',
			'label-message' => 'aboutme',
			'section' => 'personal/info/public',
			'rows' => 6,
			'maxlength' => 5000,
			'placeholder' => wfMessage('aboutmeplaceholder')->plain(),
			'help-message' => 'aboutmehelp',
		];

		$preferences['profile-avatar'] = [
			'type' => 'info',
			'label-message' => 'avatar',
			'section' => 'personal/info/public',
			'default' => wfMessage('avatar-help')->parse(),
			'raw' => true,
		];

		$preferences['profile-location'] = [
			'type' => 'text',
			'label-message' => 'locationlabel',
			'section' => 'personal/info/location',
		];

		foreach (self::$externalProfileFields as $index => $field) {
			$service = str_replace('profile-link-', '', $field);
			$preferences[$field] = [
				'type' => 'text',
				'maxlength' => 2083,
				'label-message' => $service . 'link',
				'section' => 'personal/info/profiles',
				'placeholder' => wfMessage($service . 'linkplaceholder')->plain()
			];
			if (count(self::$externalProfileFields) - 1 == $index) {
				$preferences[$field]['help-message'] = 'profilelink-help';
			}
		}

		if ($wgUser->isBlocked()) {
			foreach (self::getValidEditFields() as $field) {
				$preferences[$field]['help-message'] = 'profile-blocked';
				$preferences[$field]['disabled'] = true;
			}
		}
	}

	/**
	 * Adds default values for preferences added by curse profile
	 *
	 * @param  array &$defaultOptions Default Values
	 * @return null
	 */
	public static function insertProfilePrefsDefaults(&$defaultOptions) {
		$defaultOptions['echo-subscriptions-web-profile-friendship'] = 1;
		$defaultOptions['echo-subscriptions-email-profile-friendship'] = 1;
		$defaultOptions['echo-subscriptions-web-profile-comment'] = 1;
		$defaultOptions['echo-subscriptions-email-profile-comment'] = 1;
		$defaultOptions['echo-subscriptions-web-profile-report'] = 1;
		$defaultOptions['echo-subscriptions-email-profile-report'] = 1;

		// Allow overriding by setting the value in the global $wgDefaultUserOptions
		if (!isset($defaultOptions['profile-pref'])) {
			$defaultOptions['profile-pref'] = 1;
		}

		if (!isset($defaultOptions['comment-pref'])) {
			$defaultOptions['comment-pref'] = 0;
		}
	}

	/**
	 * Runs when the user saves their preferences.
	 *
	 * @param  User  $user         User
	 * @param  array &$preferences User Preferences
	 * @return null
	 */
	public static function processPreferenceSave($user, &$preferences) {
		global $wgUser;

		// don't allow blocked users to change their about me text
		// Deep in the logic of isBlocked() it tries to call on $wgUser for some unknown reason, but $wgUser can be null.
		if ($user->isSafeToLoad() && $wgUser !== null && $user->isBlocked() && isset($preferences['profile-aboutme']) && $preferences['profile-aboutme'] != $user->getOption('profile-aboutme')) {
			$preferences['profile-aboutme'] = $user->getOption('profile-aboutme');
		}

		// run hooks on profile preferences (mostly for achievements)
		foreach (self::getValidEditFields() as $field) {
			if (!empty($preferences[$field])) {
				Hooks::run('CurseProfileEdited', [$user, $field, $preferences[$field]]);
			}
		}

		foreach (self::$externalProfileFields as $field) {
			if (!isset($preferences[$field])) {
				continue;
			}
			$valid = self::validateExternalProfile(str_replace('profile-link-', '', $field), $preferences[$field]);
			if ($valid === false) {
				$preferences[$field] = '';
				continue;
			}
			$preferences[$field] = $valid;
		}
	}

	/**
	 * Can the given user edit this profile profile?
	 *
	 * @param  object $performer User, the performer that needs to make changes.
	 * @return mixed	Boolean true if allowed, otherwise error message string to display.
	 */
	public function canEdit($performer) {
		global $wgEmailAuthentication;

		if ($performer->isBlocked()) {
			return 'profile-blocked';
		}

		if ($performer->isAllowed('profile-moderate')) {
			// Moderators can always edit a profile to remove spam.
			return true;
		}

		if ($performer->getId() !== $this->user->getId()) {
			// Users can only edit their own profiles if they are not a moderator.
			return 'no-perm-profile-moderate';
		}

		if ($wgEmailAuthentication && (!boolval($performer->getEmailAuthenticationTimestamp()) || !\Sanitizer::validateEmail($performer->getEmail()))) {
			// If email authentication is turned on and their email address is invalid then prevent editing.
			return 'email-auth-required';
		}

		return true;
	}

	/**
	 * Get a the profile field text.
	 *
	 * @param  string $field Field Name - Examples: aboutme, location, link_twitch
	 * @return string
	 */
	public function getField($field) {
		$field = 'profile-' . $field;
		if (!in_array($field, self::getValidEditFields())) {
			throw new MWException(__METHOD__ . ': Invalid profile field.');
		}
		return (string)$this->user->getOption($field);
	}

	/**
	 * Set a profile field.
	 *
	 * @param  string      $field     Field Name - Examples: aboutme, location, link_twitch
	 * @param  string      $text      the new text for the user's aboutme
	 * @param  object|null $performer [Optional] User who performed the action.  Null to use the current user.
	 * @return void
	 */
	public function setField($field, $text, $performer = null) {
		$field = 'profile-' . $field;
		if (!in_array($field, self::getValidEditFields())) {
			throw new MWException(__METHOD__ . ': Invalid profile field (' . $field . ').');
		}

		$this->user->setOption($field, $text);
		$this->user->saveSettings();

		if ($performer === null) {
			$performer = $this->user;
		}

		self::logProfileChange($field, $text, $this->user, $performer);
	}

	/**
	 * Extracts the username from a profile link.
	 *
	 * @param  string $service Name of service to validate.
	 * @param  string $test    Raw text to test for an URL or user name to extract.
	 * @return mixed	False or validated string value.
	 */
	public static function validateExternalProfile($service, $test) {
		$service = strtolower($service);

		if (!isset(self::$externalProfiles[$service])) {
			return false;
		}

		$patterns = self::$externalProfiles[$service];

		foreach ($patterns as $pattern) {
			$result = preg_match("#" . str_replace('#', '\#', $pattern) . "#", $test, $matches);
			if ($result > 0 && isset($matches[1]) && !empty($matches[1])) {
				return $matches[1];
			}
		}

		return false;
	}

	/**
	 * Performs the work for the parser tag that displays the user's "About Me" text
	 *
	 * @param  string $field Field name to retrieve.
	 * @return mixed	array with HTML string at index 0 or an HTML string
	 */
	public function getFieldHtml($field) {
		global $wgOut;
		$wgUser = RequestContext::getMain()->getUser();

		$fieldHtml = $wgOut->parseAsContent($this->getField($field));

		if ($this->canEdit($wgUser) === true) {
			if (empty($fieldHtml)) {
				$fieldHtml = Html::element('em', [], wfMessage(($this->isViewingSelf() ? 'empty-' . $field . '-text' : 'empty-' . $field . '-text-mod'))->params($this->user->getName(), $wgUser->getName())->text());
			}

			$fieldHtml = Html::rawElement(
				'a',
				[
					'class'	=> 'rightfloat profileedit is-icon',
					'href'	=> '#',
					'title' => wfMessage('editfield-' . $field . '-tooltip')->params($this->user->getName())->text()
				],
				ProfilePage::PENCIL_ICON_SVG
			) . $fieldHtml;
		}

		return $fieldHtml;
	}

	/**
	 * Performs the work for the parser tag that displays a user's links to other gaming profiles.
	 *
	 * @return mixed	Array with HTML string at index 0 or an HTML string.
	 */
	public function getProfileLinksHtml() {
		$wgUser = RequestContext::getMain()->getUser();

		$profileLinks = $this->getExternalProfiles();

		$html = "";
		if ($this->canEdit($wgUser) === true) {
			if (!count($profileLinks)) {
				$html .= "" . Html::element('em', [], wfMessage(($this->isViewingSelf() ? 'empty-social-text' : 'empty-social-text-mod'))->params($this->user->getName(), $wgUser->getName())->text()) . "";
			}
			$html .= "" . Html::rawElement(
				'a',
				[
					'class'	=> 'rightfloat socialedit is-icon',
					'href'	=> '#',
					'title' => wfMessage('editfield-social-tooltip')->plain()
				],
				ProfilePage::PENCIL_ICON_SVG
			);
		}

		$html .= ProfilePage::generateProfileLinks($profileLinks);
		// Get all of the possible external profiles to shove into the data-field for Javascript to know which ones to be able to edit.
		foreach (self::$externalProfiles as $field => $unused) {
			$fields[] = 'link-' . $field;
		}
		sort($fields);
		$html = "<div id='profile-social' data-field='" . implode(" ", $fields) . "'>" . $html . "</div>";

		return $html;
	}

	/**
	 * Check whether we are viewing the profile of the logged-in user
	 *
	 * @return boolean
	 */
	public function isViewingSelf() {
		global $wgUser;

		return $wgUser->isLoggedIn() && $wgUser->getID() == $this->user->getID();
	}

	/**
	 * Log a profile change.
	 *
	 * @param  string $section   Section string of the profile.  Example: profile-aboutme
	 * @param  string $comment   Comment for the log, usually the text of the change.
	 * @param  object $target    User targeted for the action.
	 * @param  object $performer User who performed the action.  Null to use the current user.
	 * @return void
	 */
	public static function logProfileChange($section, $comment, User $target, User $performer) {
		if (strlen($comment) > 140) {
			$comment = substr($comment, 0, 140) . "...";
		}

		// Insert an entry into the Log.
		$log = new ManualLogEntry('curseprofile', 'profile-edited');
		$log->setPerformer($performer);
		$log->setTarget(Title::makeTitle(NS_USER_PROFILE, $target->getName()));
		$log->setComment($comment);
		$log->setParameters(
			[
				'4:section' => $section
			]
		);
		$logId = $log->insert();
		$log->publish($logId);
	}

	/**
	 * Returns all the user's location profile data
	 *
	 * @return array	Possibly including key: location
	 */
	public function getLocation() {
		$profile = [
			'location' => $this->user->getOption('profile-location')
		];
		return array_filter($profile);
	}

	/**
	 * Returns all the user's external social profiles.
	 *
	 * @return array	Possibly including keys: Twitter, Facebook, Reddit, Steam, VK, XBL, PSN
	 */
	public function getExternalProfiles() {
		foreach (self::$externalProfiles as $service => $data) {
			$profile[$service] = self::validateExternalProfile($service, $this->user->getOption('profile-link-' . $service));
		}
		return array_filter($profile);
	}

	/**
	 * Returns the md5_key for the wiki the user has selected as a favorite
	 *
	 * @return string
	 */
	public function getFavoriteWikiHash() {
		return $this->user->getOption('profile-favwiki');
	}

	/**
	 * Returns more complete info on the wiki chosen as the user's favorite
	 *
	 * @return array
	 */
	public function getFavoriteWiki() {
		if ($this->user->getOption('profile-favwiki')) {
			$sites = self::getWikiSites($this->user->getOption('profile-favwiki'));
			return current($sites) ? current($sites) : [];
		}
		return [];
	}

	/**
	 * Get information about wiki sites from Redis for searching.
	 *
	 * @param  string $search Search Term
	 * @return array	Search Results
	 */
	public static function getWikiSitesSearch($search) {
		$redis = RedisCache::getClient('cache');
		if ($redis === false) {
			return [];
		}

		$search = is_string($search) ? "*" . mb_strtolower($search, "UTF-8") . "*" : null;

		$siteKeys = [];

		$it = null;
		$redis->setOption(Redis::OPT_SCAN, Redis::SCAN_RETRY);
		while (($arrKeys = $redis->hScan('dynamicsettings:siteNameKeys', $it, $search)) !== false) {
			foreach ($arrKeys as $strField => $strValue) {
				$siteKeys[] = $strValue;
			}
			if (count($siteKeys) >= 15) {
				break;
			}
		}

		return self::getWikiSites($siteKeys);
	}

	/**
	 * Returns the decoded wiki data available in redis
	 *
	 * @param  string|array|null $siteKey md5 key for wanted site, or array of keys.
	 * @return array	Wiki data arrays.
	 */
	public static function getWikiSites($siteKey = null) {
		$redis = RedisCache::getClient('cache');
		if ($redis === false) {
			return [];
		}
		if ($siteKey === null) {
			$sites = $redis->sMembers('dynamicsettings:siteHashes');
		} else {
			if (!is_array($siteKey)) {
				$sites = [$siteKey];
			} else {
				$sites = $siteKey;
			}
		}
		$ret = [];
		if (!empty($sites)) {
			foreach ($sites as $md5) {
				$data = $redis->hGetAll('dynamicsettings:siteInfo:' . $md5);
				if (empty($data)) {
					continue;
				}
				$deleted = false;
				foreach ($data as $field => $val) {
					$val = unserialize($val);
					if ($field === 'deleted' && $val == 1) {
						$deleted = true;
						break;
					}
					$data[$field] = $val;
				}
				if (!$deleted) {
					$ret[$md5] = $data;
				}
			}
		}
		return $ret;
	}

	/**
	 * Returns true if the profile page should be used, false if the wiki should be used
	 *
	 * @return boolean
	 */
	public function getProfileTypePreference() {
		// override preference for non existent user
		if ($this->user_id == 0) {
			return false;
		}
		return $this->user->getIntOption('profile-pref');
	}

	/**
	 * Returns true if the profile page should be used, false if the wiki should be used
	 *
	 * @return boolean
	 */
	public function getCommentTypePreference() {
		// override preference for non existent user
		if ($this->user_id == 0) {
			return false;
		}
		return $this->user->getIntOption('comment-pref');
	}
}
