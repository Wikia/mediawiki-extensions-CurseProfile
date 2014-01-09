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

class ProfilePage extends \Article {

	// protected $user_name;
	// protected $user_id;
	protected $user;
	protected $profile;
	protected $viewing_self;

	public function __construct($title) {
		parent::__construct($title);
		$this->user = \User::newFromId(\User::idFromName($title->getText()));
		$this->profile = new ProfileData($this->user->getID());
	}

	public function view() {
		// TODO check user prefs, call parent::view and return nothing if wiki page preferred over profile

		$user_name = $this->user->getName();
		$output = $this->getContext()->getOutput();
		$output->setPageTitle($this->mTitle->getPrefixedText());

		$outputString = wfMessage('userprofilelayout')->params($user_name, $this->user->getId(), $this->user->getEmail())->parse();
		$output->addHtml($outputString); // Removed in favor of using an editable message during development
		// $output->addWikiMsg('userprofilelayout', [$user_name]);
	}

	public static function isProfilePage($title) {
		return strpos( $title->getText(), '/' ) === false &&
		( NS_USER == $title->getNamespace() /*|| NS_USER_PROFILE == $title->getNamespace()*/ );
	}

	public static function gravatar(&$parser, $email) {
		$html = "<img height='160' width='160' class='mainavatar' src='http://www.gravatar.com/avatar/00000000000000000000000000000000?s=160&d=mm&r=pg'>";
		return [
			$html,
			'isHTML' => true
		];
	}

	public static function location(&$parser, $user_id = '') {
		$user_id = intval($user_id);
		if ($user_id < 1) {
			return 'Invalid user ID given';
		}
		$mouse = CP::loadMouse();
		$profile = new ProfileData($user_id);
		$locations = $profile->getLocations();
		return implode(', ', $locations);
	}

	public static function aboutBlock(&$parser, $user_id = '') {
		$user_id = intval($user_id);
		if ($user_id < 1) {
			return 'Invalid user ID given';
		}
		$mouse = CP::loadMouse();
		$profile = new ProfileData($user_id);
		return $profile->getAboutText();
	}

	public static function profileLinks(&$parser, $user_id = '') {
		$user_id = intval($user_id);
		if ($user_id < 1) {
			return 'Invalid user ID given';
		}
		$mouse = CP::loadMouse();
		$profile = new ProfileData($user_id);
		$profileLinks = $profile->getProfileLinks();

		if (count($profileLinks) == 0) {
			return '';
		}

		$HTML = '
		<ul class="profilelinks">';
		foreach ($profileLinks as $key => $link) {
			$HTML .= "<li class='$key'>";
			switch ($key) {
				case 'Steam':
					$steamName = htmlspecialchars(self::parseSteamUrl($link));
					$link = htmlspecialchars($link, ENT_QUOTES);
					$HTML .= "<a href='$link'>$steamName</a>";
					break;
				case 'XBL':
				case 'PSN':
				default:
					$HTML .= htmlspecialchars($link);
			}
			$HTML .= '</li>';
		}
		$HTML .= '
		</ul>';

		return [
			$HTML,
			'isHTML' => true,
		];
	}

	/**
	 * Extracts the username from a steamcommunity.com profile link
	 *
	 * @param	string	url to profile
	 * @return	string	username or id
	 */
	private static function parseSteamUrl($url) {
		preg_match('|https?://steamcommunity\\.com/id/(\\w+)/?|', $url, $match);
		return $match[1];
	}
}
