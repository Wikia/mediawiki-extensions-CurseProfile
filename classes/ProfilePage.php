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
	protected $user_id;
	protected $user;
	protected $profile;

	public function __construct($title) {
		parent::__construct($title);
		$this->user = \User::newFromName($title->getText());
		if ($this->user) {
			$this->user_id = $this->user->getID();
		} else {
			$this->user_id = 0;
		}
		$this->profile = new ProfileData($this->user_id);
	}

	/**
	 * Check whether we are viewing the profile of the logged-in user
	 *
	 * @return	boolean
	 */
	public function viewingSelf() {
		global $wgUser;
		return $wgUser->isLoggedIn() && $wgUser->getID() == $this->user->getID();
	}

	public function view() {
		$user_name = $this->user->getName();
		$output = $this->getContext()->getOutput();
		$output->setPageTitle($this->mTitle->getPrefixedText());

		$outputString = wfMessage('userprofilelayout')->params($user_name, $this->user->getId(), $this->user->getEmail())->parse();
		$output->addHtml($outputString); // Removed in favor of using an editable message during development
		// $output->addWikiMsg('userprofilelayout', [$user_name]);
	}

	public function isUserPage() {
		return $this->user_id && strpos( $this->mTitle->getText(), '/' ) === false
			&& in_array($this->mTitle->getNamespace(), [NS_USER, NS_USER_PROFILE, NS_USER_WIKI]);
	}

	public function isProfilePage() {
		return $this->isUserPage() && $this->profile->getTypePref();
	}

	public function isUserWikiPage() {
		return $this->isUserPage() && !$this->profile->getTypePref();
	}

	public function customizeNavBar(&$links) {
		if ($this->viewingSelf()) {
			$links['views']['edit_profile'] = [
				'class'		=> false,
				'text'		=> wfMessage('editprofile')->plain(),
				'href'		=> '/Special:EditProfile',
				'primary'	=> true,
			];
			$links['actions']['switch_type'] = [
				'class'		=> false,
				'text'		=> wfMessage('toggletypepref')->plain(),
				'title'		=> wfMessage('toggletypetooltip')->plain(),
				'href'		=> '/Special:ToggleProfilePreference',
				'primary'	=> true,
			];
		}

		// TODO move add/remove friend links up here
	}

	/**
	 * Prints a gravatar image tag for a user
	 *
	 * @param	parser
	 * @param	int		the square size of the avatar to display
	 * @param	string	user's email address
	 * @param	string	the user's username
	 * @param	string	additional html attributes to include in the IMG tag
	 * @return	string	the HTML fragment containing a IMG tag
	 */
	public static function userAvatar(&$parser, $size, $email, $user_name, $attributeString = '') {
		$size = intval($size);
		$user_name = htmlspecialchars($user_name, ENT_QUOTES);
		return [
			"<img src='http://www.gravatar.com/avatar/".md5(strtolower(trim($email)))."?d=mm&amp;s=$size' height='$size' width='$size' alt='".wfMessage('avataralt', $user_name)->escaped()."' $attributeString>",
			'isHTML' => true,
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
					$HTML .= "<a href='$link' target='_blank'>$steamName</a>";
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


	public static function favoriteWiki(&$parser, $user_id = '') {
		$user_id = intval($user_id);
		if ($user_id < 1) {
			return 'Invalid user ID given';
		}
		$mouse = CP::loadMouse();
		$profile = new ProfileData($user_id);
		$wiki = $profile->getFavoriteWiki();
		if (empty($wiki)) {
			return '';
		}

		$HTML = wfMessage('favoritewiki')->plain().':<br>';
		$HTML .= CP::placeholderImage($nothing, 157, 118, ['title'=>$wiki['wiki_name'], 'alt'=>$wiki['wiki_name']])[0];

		return [
			$HTML,
			'isHTML' => true,
		];
	}
}
