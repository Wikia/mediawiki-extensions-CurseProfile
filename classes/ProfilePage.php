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

	static protected $output;
	static private $self;

	public function __construct($title) {
		parent::__construct($title);
		$this->user_name = $title->getText();
		$this->user = \User::newFromName($title->getText());
		if ($this->user) {
			$this->user_id = $this->user->getID();
		} else {
			$this->user = \User::newFromId(0);
			$this->user_id = 0;
		}
		$this->profile = new ProfileData($this->user_id);
		self::$output = $this->getContext()->getOutput();
		self::$self = $this;
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
		self::$output->setPageTitle($this->mTitle->getPrefixedText());
		self::$output->setArticleFlag(false);

		$outputString = \MessageCache::singleton()->parse($this->profileLayout(), $this->mTitle);
		if ($outputString instanceOf \ParserOutput) {
			$outputString = $outputString->getText();
		}
		self::$output->addHTML($outputString);
	}

	public function isUserPage() {
		return $this->user_id && strpos( $this->mTitle->getText(), '/' ) === false
			&& in_array($this->mTitle->getNamespace(), [NS_USER, NS_USER_PROFILE, NS_USER_WIKI]);
	}

	public function isDefaultPage() {
		return $this->isUserPage() && $this->mTitle->getNamespace() == NS_USER;
	}

	public function isProfilePage() {
		return $this->isUserPage() && (
				($this->profile->getTypePref() && $this->mTitle->getNamespace() == NS_USER) ||
				($this->mTitle->getNamespace() == NS_USER_PROFILE)
			);
	}

	public function isUserWikiPage() {
		return $this->isUserPage() && (
				(!$this->profile->getTypePref() && $this->mTitle->getNamespace() == NS_USER) ||
				($this->mTitle->getNamespace() == NS_USER_WIKI)
			);
	}

	public function getUserWikiArticle() {
		$article = new \Article($this->user->getUserPage());
		$article->setContext($this->getContext());
		return $article;
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
			$links['namespaces']['user']['text'] = wfMessage('userprofiletab')->plain(); // rename from "User page"
			$links['namespaces']['user']['class'] = 'selected';
			// add link to user wiki
			$links['namespaces']['user_wiki'] = [
				'class'		=> false,
				'text'		=> wfMessage('userwikitab')->plain(),
				'href'		=> $this->profile->getUserWikiPath(),
			];

			$links['views']['contribs'] = [
				'class'		=> false,
				'text'		=> wfMessage('contributions')->parse(),
				'href'		=> \SpecialPage::getTitleFor('Contributions', $this->user_name)->getFullURL(),
			];

			// only offer to edit the profile if you are the owner
			/* removed because buttons preferred
			if ($this->viewingSelf()) {
				$links['views']['edit_profile'] = [
					'class'		=> false,
					'text'		=> wfMessage('cp-editprofile')->plain(),
					'href'		=> '/Special:Preferences#mw-prefsection-personal-info-public',
				];
			} elseif ($wgUser->isLoggedIn()) { // only offer friending when not viewing yourself
				// add link to add, confirm, or remove friend
				FriendDisplay::addFriendLink($this->user_id, $links);
			}
			*/
		}

		// links specific to a user wiki page
		if ($this->isUserWikiPage()) {
			$links['namespaces']['user_profile'] = [
				'class'		=> false,
				'text'		=> wfMessage('userprofiletab')->plain(),
				'href'		=> $this->profile->getProfilePath(),
				'primary'	=> true,
			];

			if ($this->profile->getTypePref()) {
				// enabling editing while wiki is not the default is a lot more work
				// so it is disabled by removing the link
				unset($links['views']['edit']);
			}
		}

		// Always visible regardless of page type
		if ($this->viewingSelf()) {
			$links['actions']['switch_type'] = [
				'class'		=> false,
				'text'		=> wfMessage('toggletypepref')->plain(),
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
			"<img src='http://www.gravatar.com/avatar/".md5(strtolower(trim($email)))."?d=mm&amp;s=$size' height='$size' width='$size' alt='".wfMessage('avataralt', $user_name)->escaped()."' $attributeString />",
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

		if (isset($locations['country-flag'])) {
			$src = \FlagFinder::getFlagPath($locations['country-flag']);
			$HTML = "<img src='$src' class='countryflag' alt='flag for {$locations['country-flag']}'/> ".$HTML;
			unset($locations['country-flag']);
		}

		$HTML .= implode(', ', $locations);

		return [
			$HTML,
			'isHTML' => true,
		];
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
		$HTML = wfMessage('favoritewiki')->plain().'<br/>' . $HTML;

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
				'localrank' => '',
				'globalrank' => '', // data for these fills in below
				'totalfriends' => '',
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

		if ($curse_id > 1 && method_exists('PointsDisplay', 'pointsForCurseId')) {
			global $wgServer;
			$statsOutput['localrank'] = \PointsDisplay::pointsForCurseId($curse_id, \WikiPoints::domainToNamespace($wgServer))['rank'];
			$statsOutput['globalrank'] = \PointsDisplay::pointsForCurseId($curse_id)['rank'];

			if (empty($statsOutput['localrank'])) {
				unset($statsOutput['localrank']);
			}
			if (empty($statsOutput['globalrank'])) {
				unset($statsOutput['globalrank']);
			}
		} else {
			// No curse id or WikiPoints plugin available
			unset($statsOutput['localrank']);
			unset($statsOutput['globalrank']);
		}

		$statsOutput['totalfriends'] = FriendDisplay::count($parser, $user_id);

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
				if (is_numeric($input[0])) {
					$output .= number_format($input[0]);
				} else {
					$output .= $input[0];
				}
			}
			$output .= "<dl>";
			foreach ($input as $msgKey => $value) {
				if (!is_string($msgKey)) {
					continue;
				}
				$output .= "<dt>".self::$output->parseInline(wfMessage($msgKey, self::$self->user_id))."</dt><dd>".self::generateStatsDL($value)."</dd>";
			}
			$output .= "</dl>";
			return $output;
		} else {
			// just a simple value
			if (is_numeric($input)) {
				return number_format($input);
			} else {
				return $input;
			}
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
		if ($curse_id < 1 || !method_exists('PointsDisplay', 'pointsForCurseId')) {
			return '';
		}

		$mouse = CP::loadMouse();
		$userPoints = \PointsDisplay::pointsForCurseId($curse_id)['score'];
		$levelDefinitions = $mouse->redis->getUnserialized('wikipoints::levels');

		if (!is_array($levelDefinitions)) {
			return '';
		}

		$HTML = '';
		foreach ($levelDefinitions as $tier) {
			if ($userPoints >= $tier['points']) {
				$HTML = $tier['text'];
				// TODO display $tier['image_icon'] or $tier['image_large']
				$HTML .= ' '.CP::placeholderImage($parser, 32, '', ['class'=>'level'])[0];
			} else {
				break; // assuming that the definitions array is sorted by level ASC
			}
		}

		return [
			$HTML,
			'isHTML' => true,
		];
	}

	public static function editOrFriends(&$parser) {
		$HTML = FriendDisplay::addFriendButton(self::$self->user_id);

		if (self::$self->viewingSelf()) {
			$text = wfMessage('cp-editprofile')->plain();
			$HTML .= "<button data-href='/Special:Preferences#mw-prefsection-personal-info-public' class='linksub'>$text</button>";
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
			<div class="mainavatar">{{#avatar: 96 | %3$s | %1$s}}</div>
			<div class="headline">
				<h1>%1$s</h1>
				{{#groups: %2$s}}
			</div>
			<div>
				<div class="location">{{#location: %2$s}}</div>
				{{#profilelinks: %2$s}}
			</div>
			<div class="aboutme">
				{{#aboutme: %2$s}}
			</div>
		</div>
		<div class="activity section">
			<h3>'.wfMessage('cp-recentactivitysection').'</h3>
			{{#recentactivity: %2$s}}
		</div>
		<div class="comments section">
			<p class="rightfloat">[[Special:CommentBoard/%2$s/%4$s|'.wfMessage('commentarchivelink').']]</p>
			<h3>'.wfMessage('cp-recentcommentssection').'</h3>
			{{#comments: %2$s}}
		</div>
	</div>
	<div class="rightcolumn">
		<div class="borderless section">
			<div class="rightfloat">
				<div class="level">{{#userlevel: %2$s}}</div>
				<div class="score">{{#Points: User:%1$s | all | raw}} GP</div>
				<div>{{#editorfriends:}}</div>
			</div>
			<div class="favorite">{{#favwiki: %2$s}}</div>
		</div>
		<div class="section">
			<h3>'.wfMessage('cp-statisticssection').'</h3>
			{{#userstats: %2$s}}
			{{#friendlist: %2$s}}
		</div>
	</div>
</div>
__NOTOC__
',
			$this->user_name,
			$this->user->getID(),
			$this->user->getEmail(),
			$this->user->getTitleKey()
		);
	}
}
