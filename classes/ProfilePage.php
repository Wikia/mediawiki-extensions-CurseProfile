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

	protected $user_name;
	protected $user_id;
	protected $user;
	protected $profile;

	public function __construct($title) {
		parent::__construct($title);
		$this->user_name = $title->getText();
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
		$output = $this->getContext()->getOutput();
		$output->setPageTitle($this->mTitle->getPrefixedText());

		$outputString = \MessageCache::singleton()->parse($this->profileLayout(), $this->mTitle);
		if ($outputString instanceOf \ParserOutput) {
			$outputString = $outputString->getText();
		}
		$output->addHTML($outputString);
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
		global $wgUser;

		// links specific to the profile page
		if ($this->isProfilePage()) {
			$oldLinks = $links;
			// let's start with a fresh array
			$links = [
				'namespaces' => [],
				'views' => [],
				'actions' => [],
				'variants' => [],
			];

			$links['namespaces']['user'] = $oldLinks['namespaces']['user'];
			$links['namespaces']['user']['text'] = 'User profile'; // rename from "User page"
			// add link to user wiki
			$links['namespaces']['user_wiki'] = [
				'class'		=> false,
				'text'		=> 'User Wiki',
				'href'		=> "/UserWiki:{$this->user_name}",
			];

			// only offer to edit the profile if you are the owner
			if ($this->viewingSelf()) {
				$links['views']['edit_profile'] = [
					'class'		=> false,
					'text'		=> wfMessage('editprofile')->plain(),
					'href'		=> '/Special:Preferences#mw-prefsection-personal-info-public',
				];
			} elseif ($wgUser->isLoggedIn()) { // only offer friending when not viewing yourself
				// add link to add, confirm, or remove friend
				FriendDisplay::addFriendLink($this->user_id, $links);
			}
		}

		// links specific to a user wiki page
		if ($this->isUserWikiPage()) {
			$links['namespaces']['user_profile'] = [
				'class'		=> false,
				'text'		=> 'User Profile',
				'href'		=> "/UserProfile:{$this->user_name}",
			];
		}

		// Always visible regardless of page type
		if ($this->viewingSelf()) {
			$links['actions']['switch_type'] = [
				'class'		=> false,
				'text'		=> wfMessage('toggletypepref')->plain(),
				'title'		=> wfMessage('toggletypetooltip')->plain(),
				'href'		=> '/Special:ToggleProfilePreference',
			];
		}
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

	/**
	 * Performs the work for the parser tag that displays the groups to which a user belongs
	 *
	 * @param	object	parser reference
	 * @param	int		ID of a user
	 * @return	mixed	array with HTML string at index 0 or an HTML string
	 */
	public static function groupList(&$parser, $user_id) {
		$user_id = intval($user_id);
		if ($user_id < 1) {
			return '';
		}

		$user = \User::newFromId($user_id);
		$groups = $user->getGroups();
		if (count($groups) == 0) {
			return '';
		}

		$HTML = '<ul class="grouptags">';
		foreach ($groups as $group) {
			$HTML .= '<li>'.htmlspecialchars($group).'</li>';
		}
		$HTML .= '</ul>';

		return [$HTML, 'isHTML' => true];
	}

	/**
	 * Performs the work for the parser tag that displays the user's location.
	 *
	 * @param	object	parser reference
	 * @param	int		ID of a user
	 * @return	mixed	array with HTML string at index 0 or an HTML string
	 */
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

	/**
	 * Performs the work for the parser tag that displays the user's "About Me" text
	 *
	 * @param	object	parser reference
	 * @param	int		ID of a user
	 * @return	mixed	array with HTML string at index 0 or an HTML string
	 */
	public static function aboutBlock(&$parser, $user_id = '') {
		$user_id = intval($user_id);
		if ($user_id < 1) {
			return 'Invalid user ID given';
		}
		$mouse = CP::loadMouse();
		$profile = new ProfileData($user_id);
		return $profile->getAboutText();
	}

	/**
	 * Performs the work for the parser tag that displays a user's links to other gaming profiles.
	 *
	 * @param	object	parser reference
	 * @param	int		ID of a user
	 * @return	mixed	array with HTML string at index 0 or an HTML string
	 */
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

	/**
	 * Performs the work for the parser tag that displays the user's chosen favorite wiki
	 *
	 * @param	object	parser reference
	 * @param	int		ID of a user
	 * @return	mixed	array with HTML string at index 0 or an HTML string
	 */
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

		$HTML = CP::placeholderImage($nothing, 157, 118, ['title'=>$wiki['wiki_name'], 'alt'=>$wiki['wiki_name']])[0];
		$HTML = "<a target='_blank' href='http://{$wiki['wiki_domain']}'>".$HTML."</a>";
		$HTML = wfMessage('favoritewiki')->plain().'<br>' . $HTML;

		return [
			$HTML,
			'isHTML' => true,
		];
	}

	/**
	 * Performs the work for the parser tag that displays user statistics.
	 * The numbers themselves are pulled from the dataminer api
	 *
	 * @param	object	parser reference
	 * @param	int		ID of a user
	 * @return	mixed	array with HTML string at index 0 or an HTML string
	 */
	public static function userStats(&$parser, $user_id = '') {
		$user_id = intval($user_id);
		if ($user_id < 1) {
			return 'Invalid user ID given';
		}
		$curse_id = CP::curseIDfromUserID($user_id);

		$mouse = CP::loadMouse(['curl' => 'mouseTransferCurl']);
		global $wgServer;
		$jsonStats = $mouse->curl->fetch($wgServer.'/api.php?action=dataminer&do=getUserGlobalStats&curse_id='.$curse_id);
		$stats = json_decode($jsonStats, true);

		// keys are message keys fed to wfMessage()
		// values are numbers or an array of sub-stats with a number at key 0
		if ($stats) {
			$totalStats = $stats[$curse_id]['global']['total'];
			$statsOutput = [
				'wikisedited' => $stats[$curse_id]['other']['wikis_contributed'],
				'totalcontribs' => [ $totalStats['actions'],
					'totaledits'   => $totalStats['edits'],
					'totaldeletes' => $totalStats['deletes'],
					'totalpatrols' => $totalStats['patrols'],
				],
			];
		} else {
			$statsOutput = [
				'wikisedited' => 0,
				'totalcontribs' => [ 0,
					'totaledits' => 0,
					'totaldeletes' => 0,
					'totalpatrols' => 0,
				],
			];
		}
		$statsOutput['friends'] = FriendDisplay::count($parser, $user_id);

		$HTML = self::generateStatsDL($statsOutput);

		return [
			$HTML,
			'isHTML' => true,
		];
	}

	/**
	 * Recursive function for parsing out and stringifying the stats array above
	 *
	 * @param	mixed	arrays will generate a new list, other values will be directly returned
	 * @return	string	html DL fragment or $input if it is not an array
	 */
	private static function generateStatsDL($input) {
		if (is_array($input)) {
			$output = '';
			if (isset($input[0])) {
				// handle a value with a sub-list
				// TODO handle output with commas for better human readability
				$output .= $input[0];
			}
			$output .= "\n<dl>";
			foreach ($input as $msgKey => $value) {
				if (!is_string($msgKey)) {
					continue;
				}
				$output .= "\n<dt>".wfMessage($msgKey)->escaped()."</dt><dd>".self::generateStatsDL($value)."</dd>";
			}
			$output .= "\n</dl>";
			return $output;
		} else {
			// just a simple value
			return $input;
		}
	}

	/**
	 * Performs the work for the parser tag that displays the user's level (based on wikipoints)
	 *
	 * @param	object	parser reference
	 * @param	int		ID of a user
	 * @return	mixed	array with HTML string at index 0 or an HTML string
	 */
	public static function userLevel(&$parser, $user_id = '') {
		$user_id = intval($user_id);
		if ($user_id < 1) {
			return 'Invalid user ID given';
		}
		$curse_id = CP::curseIDfromUserID($user_id);
		if ($curse_id < 1) {
			return '';
		}

		$mouse = CP::loadMouse();
		$userPoints = intval($mouse->redis->zscore('wikipoints:usertotals', $curse_id));
		$levelDefinitions = $mouse->redis->getUnserialized('wikipoints::levels');

		if (!is_array($levelDefinitions)) {
			return '';
		}

		$HTML = '';
		foreach ($levelDefinitions as $tier) {
			if ($userPoints >= $tier['points']) {
				$HTML = $tier['text'];
				// TODO display $tier['image_icon'] or $tier['image_large']
			} else {
				break; // assuming that the definitions array is sorted by level ASC
			}
		}

		return [
			$HTML,
			'isHTML' => true,
		];
	}

	/**
	 * Defines the HTML structure of the profile page.
	 *
	 * @return	string
	 */
	protected function profileLayout() {
		return sprintf('
<div class="curseprofile" data-userid="%2$s">
	<div class="leftcolumn">
		<div class="borderless section">
			{{#avatar: 160 | %3$s | you | class="mainavatar"}}
			<div class="headline">
				{{#groups: %2$s}}
				<h1>%1$s</h1>
			</div>
			<div>
				{{#profilelinks: %2$s}}
				{{#location: %2$s}}
			</div>
			<div class="aboutme">
				{{#aboutme: %2$s}}
			</div>
		</div>
		<div class="activity section">
			<h3>Recent Wiki Activity</h3>
			{{#recentactivity: %2$s}}
		</div>
		<div class="comments section">
			<h3>Comments</h3>
			{{#comments: %2$s}}
		</div>
	</div>
	<div class="rightcolumn">
		<div class="rightfloat">
			<div class="title">{{#userlevel: %2$s}}</div>
			<div class="score">{{#Points: User:%1$s | all | raw}} GP</div>
		</div>
		<div>{{#favwiki: %2$s}}</div>
		<div class="section">
			<h3>Total Statistics</h3>
			{{#userstats: %2$s}}
			{{#friendlist: %2$s}}
		</div>
	</div>
</div>
__NOTOC__
',
			$this->user_name,
			$this->user->getID(),
			$this->user->getEmail()
		);
	}
}
