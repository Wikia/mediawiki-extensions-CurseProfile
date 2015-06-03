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

/**
 * Class ProfilePage
 * Holds the primary logic over how and when a profile page is displayed
 */
class ProfilePage extends \Article {

	/**
	 * @var String
	 */
	protected $user_name;

	/**
	 * @var int
	 */
	protected $user_id;

	/**
	 * @var \User instance
	 */
	protected $user;

	/**
	 * @var ProfileData instance
	 */
	protected $profile;

	/**
	 * Whether or not the current page being rendered is with action=view
	 * @var bool
	 */
	private $actionIsView;

	/**
	 * An array of groups to be excluded from display on profiles
	 * @var array
	 */
	private $restrictedGroups = ['Curse_Admin', '*', 'autoconfirmed', 'checkuser', 'Ads_Manager', 'widget_editor', 'Wiki_Manager'];

	/**
	 * @param \Title $title
	 * @param IContextSource $context
	 */
	public function __construct($title, $context = null) {
		parent::__construct($title);
		if ($context) {
			$this->setContext($context);
		}
		$this->actionIsView = \Action::getActionName($this->getContext()) == 'view';
		$this->user_name = $title->getText();
		$this->user = \User::newFromName($title->getText());
		if ($this->user) {
			$this->user->load();
			$this->user_id = $this->user->getID();
		} else {
			$this->user = \User::newFromId(0);
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

	/**
	 * Primary rendering function for mediawiki's Article
	 */
	public function view() {
		$output = $this->getContext()->getOutput();
		$output->setPageTitle($this->getTitle()->getPrefixedText());
		$output->setArticleFlag(false);

		$layout = $this->profileLayout();
		$layout = str_replace('<USERSTATS>', $this->userStats(), $layout);

		$outputString = \MessageCache::singleton()->parse($layout, $this->getTitle());
		if ($outputString instanceOf \ParserOutput) {
			$outputString = $outputString->getText();
		}
		$output->addHTML($outputString);
	}

	/**
	 * Shortcut method to retrieving the user's profile page preference
	 * @return	bool	true if profile page is preferred, false if wiki is preferred
	 */
	public function profilePreferred() {
		return $this->profile->getTypePref();
	}

	/**
	 * True if we are not on a subpage, and if we are in the basic User namespace,
	 * or either of the custom UserProfile/UserWiki namespaces.
	 *
	 * @param	bool	[optional] if true (default), will return false for any action other than 'view'
	 * @return	bool
	 */
	public function isUserPage($onlyView = true) {
		return $this->user_id && !$this->getTitle()->isSubpage()
			&& (!$onlyView || $this->actionIsView)
			&& in_array($this->getTitle()->getNamespace(), [NS_USER, NS_USER_PROFILE, NS_USER_WIKI]);
	}

	/**
	 * True if we are viewing the user's default option (in the default User namespace)
	 *
	 * @return	bool
	 */
	public function isDefaultPage() {
		return $this->isUserPage() && $this->getTitle()->getNamespace() == NS_USER;
	}

	/**
	 * True if we need to render the user's profile page on either namespace
	 *
	 * @param	bool	[optional] if true (default), will return false for any action other than 'view'
	 * @return	bool
	 */
	public function isProfilePage($onlyView = true) {
		return $this->isUserPage($onlyView) && (
				($this->profile->getTypePref() && $this->getTitle()->getNamespace() == NS_USER) ||
				($this->getTitle()->getNamespace() == NS_USER_PROFILE)
			) && (
				$this->getContext()->getRequest()->getInt('diff') == 0 &&
				$this->getContext()->getRequest()->getInt('oldid') == 0 &&
				// The rcid parameter is deprecated and nonfunctional in any recent version of MW.
				// However, CurseProfile creates unexpected behavior if left unsupported here
				$this->getContext()->getRequest()->getInt('rcid') == 0
			);
	}

	/**
	 * True when we need to render the user's wiki page on either namespace
	 *
	 * @param	bool	[optional] if true (default), will return false for any action other than 'view'
	 * @return	bool
	 */
	public function isUserWikiPage($onlyView = true) {
		if ($onlyView) {
			return $this->isUserPage($onlyView) && (
					(!$this->profile->getTypePref() && $this->getTitle()->getNamespace() == NS_USER) ||
					($this->getTitle()->getNamespace() == NS_USER_WIKI)
				);
		} else {
			return $this->isUserWikiPage(true) || (
				$this->isUserPage(false) && ($this->getTitle()->getNamespace() == NS_USER && !$this->actionIsView)
			);
		}
	}

	/**
	 * True if we are on the custom UserWiki namespace
	 * @return	bool
	 */
	public function isSpoofedWikiPage() {

		return $this->getTitle()->getNamespace() == NS_USER_WIKI && $this->actionIsView;
	}

	/**
	 * Returns the article object for the User's page in the standard User namespace
	 * @return	Article instance
	 */
	public function getUserWikiArticle() {
		$article = new \Article($this->user->getUserPage());
		$article->setContext($this->getContext());
		return $article;
	}

	/**
	 * Returns the title object for the user's page in the UserWiki namespace
	 * @return	\Title instance
	 */
	public function getCustomUserWikiTitle() {
		return \Title::makeTitle(NS_USER_WIKI, $this->user->getName());
	}

	/**
	 * Adjusts the links in the primary action bar on profile pages and user wiki pages
	 * @param	array	structured info on what links will appear on the rendered page
	 */
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
			$links['namespaces']['user']['href'] = $this->getTitle()->getLinkURL();
			$links['namespaces']['user']['text'] = wfMessage('userprofiletab')->text(); // rename from "User page"
			$links['namespaces']['user']['class'] = 'selected';
			// add link to user wiki
			$links['namespaces']['user_wiki'] = [
				'class'		=> false,
				'text'		=> wfMessage('userwikitab')->text(),
				'href'		=> $this->profile->getUserWikiPath(),
			];

			// show link to usertalk page if on non-default profile
			if (!$this->profile->getTypePref()) {
				$links['namespaces']['user_talk'] = [
					'class'		=> false,
					'text'		=> wfMessage('talk')->text(),
					'href'		=> $this->user->getTalkPage()->getLinkURL(),
				];
			}

			$links['views']['contribs'] = [
				'class'		=> false,
				'text'		=> wfMessage('contributions')->text(),
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
		if ($this->isUserWikiPage(false)) {
			$links['namespaces']['user_profile'] = [
				'class'		=> false,
				'text'		=> wfMessage('userprofiletab')->text(),
				'href'		=> $this->profile->getProfilePath(),
				'primary'	=> true,
			];

			// correct User profile to "User wiki" and use appropriate link
			$links['namespaces']['user']['text'] = wfMessage('userwikitab')->text();
			$links['namespaces']['user']['href'] = $this->profile->getUserWikiPath();
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
			"<img src='//www.gravatar.com/avatar/".md5(strtolower(trim($email)))."?d=mm&amp;s=$size' height='$size' width='$size' alt='".wfMessage('avataralt', $user_name)->escaped()."' $attributeString />",
			'isHTML' => true,
		];
	}

	/**
	 * Performs the work for the parser tag that displays the groups to which a user belongs
	 *
	 * @param	object	parser reference
	 * @return	mixed	array with HTML string at index 0 or an HTML string
	 */
	public function groupList(&$parser) {
		$groups = $this->user->getEffectiveGroups();
		if (count($groups) == 0) {
			return '';
		}

		$HTML = '<ul class="grouptags">';
		foreach ($groups as $group) {
			if (in_array($group, $this->restrictedGroups)) {
				continue;
			}
			$HTML .= '<li>'.mb_convert_case(str_replace("_", " ", htmlspecialchars($group)), MB_CASE_TITLE, "UTF-8").'</li>';

		}
		$HTML .= '</ul>';

		return [$HTML, 'isHTML' => true];
	}

	/**
	 * Performs the work for the parser tag that displays the user's location.
	 *
	 * @param	object	parser reference
	 * @return	mixed	array with HTML string at index 0 or an HTML string
	 */
	public function location(&$parser) {
		$mouse = CP::loadMouse();
		$locations = $this->profile->getLocations();

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
	 * @return	mixed	array with HTML string at index 0 or an HTML string
	 */
	public function aboutBlock(&$parser) {
		global $wgOut, $wgUser;
		$aboutText = $wgOut->parse($this->profile->getAboutText());
		if (empty($aboutText)) {
			$aboutText = wfMessage('empty_about_text')->plain();
		}
		if ($wgUser->isAllowed('profile-modcomments') || $wgUser->isLoggedIn()) {
			$aboutText = \Html::rawElement(
				'a',
				[
					'class'	=> 'rightfloat profileedit',
					'href'	=> '#',
					'title' =>	wfMessage('editaboutme-tooltip')->plain()
				],
				\Curse::awesomeIcon('pencil')
			).$aboutText;
		}
		return [
			$aboutText,
			'isHTML' => true
		];
	}

	/**
	 * Performs the work for the parser tag that displays a user's links to other gaming profiles.
	 *
	 * @param	object	parser reference
	 * @return	mixed	array with HTML string at index 0 or an HTML string
	 */
	public function profileLinks(&$parser) {
		$mouse = CP::loadMouse();
		$profileLinks = $this->profile->getProfileLinks();

		if (count($profileLinks) == 0) {
			return '';
		}

		$HTML = '
		<ul class="profilelinks">';
		foreach ($profileLinks as $key => $link) {
			$item = "<li class='$key' title='$key'>";
			switch ($key) {
				case 'XBL':
					$link = urlencode($link);
					$item .= \Html::element('a', ['href'=>"https://live.xbox.com/en-US/Profile?gamertag=$link", 'target'=>'_blank']);
					break;
				case 'PSN':
					$link = urlencode($link);
					$item .= \Html::element('a', ['href'=>"http://psnprofiles.com/$link", 'target'=>'_blank']);
					break;
				case 'Twitter':
					if (!self::validateUrl($key, $link)) {
						$item = '';
					} else {
						$item .= \Html::element('a', ['href'=>"https://twitter.com/$link", 'target'=>'_blank']);
					}
					break;
				case 'Reddit':
					if (!self::validateUrl($key, $link)) {
						$item = '';
					} else {
						$item .= \Html::element('a', ['href'=>"http://www.reddit.com/user/$link", 'target'=>'_blank']);
					}
					break;
				default:
					if (self::validateUrl($key, $link)) {
						$item .= \Html::element('a', ['href'=>$link, 'target'=>'_blank']);
					} else {
						$item = '';
					}
			}
			$item .= '</li>';
			$HTML .= $item;
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
	 * @param	string	name of service to validate
	 * @param	string	url to profile or username, may be modified to strip leading @ from twitter
	 * @return	mixed	false or validated string value
	 */
	private static function validateUrl($service, &$url) {
		$patterns = [
			'Steam'		=> '|^https?://steamcommunity\\.com/id/[\\w-]+/?$|',
			'Twitter'	=> '|^@?(\\w{1,15})$|',
			'Reddit'	=> '|^\\w{3,20}$|',
			'Facebook'	=> '|^https?://www\\.facebook\\.com/[\\w\\.]+$|',
			'Google'	=> '~^https?://(?:plus|www)\\.google\\.com/(?:u/\\d/)?\\+?\\w+(?:/(?:posts|about)?)?$~',
		];
		if (isset($patterns[$service])) {
			$pattern = $patterns[$service];
		} else {
			return false;
		}
		$result = preg_match($pattern, $url, $matches);
		if (count($matches) > 1) {
			$url = $matches[1];
		}
		return $result;
	}

	/**
	 * Performs the work for the parser tag that displays the user's chosen favorite wiki
	 *
	 * @param	object	parser reference
	 * @return	mixed	array with HTML string at index 0 or an HTML string
	 */
	public function favoriteWiki(&$parser) {
		$mouse = CP::loadMouse();
		$wiki = $this->profile->getFavoriteWiki();
		if (empty($wiki)) {
			return '';
		}

		$imgPath = CP::getWikiImageByHash($wiki['md5_key']);
		if (!empty($imgPath)) {
			$HTML = \Html::element('img', ['src'=>$imgPath, 'height'=>118, 'width'=>157, 'alt'=>$wiki['wiki_name']]);
		} else {
			$HTML = htmlspecialchars($wiki['wiki_name']);
		}
		$HTML = "<a target='_blank' href='http://{$wiki['wiki_domain']}/'>".$HTML."</a>";
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
	 * @return	string	generated HTML fragment
	 */
	public function userStats() {
		$curse_id = $this->user->curse_id;

		if ($curse_id > 0) {
			$mouse = CP::loadMouse(['curl' => 'mouseTransferCurl']);
			$jsonStats = $mouse->curl->fetch(wfExpandUrl(MASTER_WIKI_URL, PROTO_CURRENT).'/api.php?action=dataminer&do=getUserGlobalStats&curse_id='.$curse_id, [], ['username'=>'hydraStats', 'password'=>'8_-csYhS']);
			$stats = json_decode($jsonStats, true);
		}

		// keys are message keys fed to wfMessage()
		// values are numbers or an array of sub-stats with a number at key 0
		if ($stats) {
			$totalStats = $stats[$curse_id]['global']['total'];
			$statsOutput = [
				// 'achievementsearned' => 123, /* replace with global achievement count when available */
				// "<dd class='achievements'>{{#achievements:local|5}}</dd>",
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
				'achievementsearned' => 0,
				'wikisedited' => 0,
				'totalcontribs' => [ 0,
					'totaledits' => 0,
					'totaldeletes' => 0,
					'totalpatrols' => 0,
				],
			];
		}

		global $wgServer;

		if ($curse_id > 1 && method_exists('PointsDisplay', 'pointsForCurseId')) {
			$statsOutput['localrank'] = \PointsDisplay::pointsForCurseId($curse_id, \WikiPoints::domainToNamespace($wgServer))['rank'];
			$statsOutput['globalrank'] = \PointsDisplay::pointsForCurseId($curse_id)['rank'];

			if (empty($statsOutput['localrank'])) {
				unset($statsOutput['localrank']);
			}
			if (empty($statsOutput['globalrank'])) {
				unset($statsOutput['globalrank']);
			}

			$statsOutput['totalfriends'] = FriendDisplay::count($nothing, $this->user->getId());
		} else {
			// No curse id or WikiPoints plugin available
			unset($statsOutput['localrank']);
			unset($statsOutput['globalrank']);
		}

		$HTML = $this->generateStatsDL($statsOutput);

		return $HTML;
	}

	/**
	 * Recursive function for parsing out and stringifying the stats array above
	 *
	 * @param	mixed	arrays will generate a new list, other values will be directly returned
	 * @return	string	html DL fragment or $input if it is not an array
	 */
	private function generateStatsDL($input) {
		global $wgUser;
		if (is_array($input)) {
			$output = "<dl>";
			foreach ($input as $msgKey => $value) {
				if (is_string($msgKey)) {
					$output .= "<dt>".wfMessage($msgKey, $this->user_id, $wgUser->getId())->plain()."</dt>";
				}
				$output .= "<dd>".$this->generateStatsDL( ( is_array($value) && isset($value[0]) ) ? $value[0] : $value )."</dd>";
				// add the sub-list if there is one
				if (is_array($value)) {
					// Discard the value for the sublist header so it isn't printed a second time as a member of the sublist
					if (isset($value[0])) {
						array_shift($value);
					}
					$output .= $this->generateStatsDL($value);
				}
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
	 * Display the icons of the recent achievements the user has earned, for the sidebar
	 *
	 * @param	object	parser reference
	 * @param	string	type of query. one of: local, global (default)
	 * @param	int		maximum number to display
	 * @return	array
	 */
	public function recentAchievements(&$parser, $type = 'global', $limit = 10) {
		if ($type === 'local') {
			$earned = \achievementsHooks::getAchievementProgressForDisplay($this->user->curse_id, $limit);
			$output = '';
			foreach ($earned as $ach) {
				$icon = \Html::rawElement('div', ['class'=>'icon'],
					\Html::element('img', ['src'=>$ach['image_url'], 'title'=>$ach['name']])
				);
				$output .= $icon;
			}
			return [$output, 'isHTML' => true];
		}

		if ($type === 'global') {
			return 'TODO: Mega Achievements';
		}
	}

	/**
	 * Performs the work for the parser tag that displays the user's level (based on wikipoints)
	 *
	 * @param	object	parser reference
	 * @return	mixed	array with HTML string at index 0 or an HTML string
	 */
	public function userLevel(&$parser) {
		// Check for existance of wikipoints functions
		if ($this->user->curse_id < 1 || !method_exists('PointsDisplay', 'pointsForCurseId')) {
			return '';
		}

		$mouse = CP::loadMouse();
		$userPoints = \PointsDisplay::pointsForCurseId($this->user->curse_id)['score'];
		$levelDefinitions = $mouse->redis->getUnserialized('wikipoints::levels');

		if (!is_array($levelDefinitions)) {
			return '';
		}

		$HTML = '';
		foreach ($levelDefinitions as $tier) {
			// assuming that the definitions array is sorted by level ASC, overwriting previous iterations
			if ($userPoints >= $tier['points']) {
				// TODO display $tier['image_icon'] or $tier['image_large']
				$HTML = \Html::element(
					'img',
					[
						'class'	=> 'level',
						'title'	=> $tier['text'],
						'src'	=> $tier['image_large']
					]
				);
			} else {
				break;
			}
		}

		return [
			$HTML,
			'isHTML' => true,
		];
	}

	/**
	 * Parser hook function that inserts either an "edit profile" button or a "add/remove friend" button
	 * @param	$parser
	 * @return	array	with html as the first element
	 */
	public function editOrFriends(&$parser) {
		$HTML = FriendDisplay::addFriendButton($this->user_id);

		if ($this->viewingSelf()) {
			$HTML .= \Html::element(
				'button',
				[
					'data-href'	=> \Title::newFromText('Special:Preferences')->getFullURL().'#mw-prefsection-personal-info-public',
					'class'		=> 'linksub'
				],
				wfMessage('cp-editprofile')->plain()
			);
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
		<div class="userinfo borderless section">
			<div class="mainavatar">{{#avatar: 96 | %3$s | %1$s}}</div>
			<div class="headline">
				<h1>%1$s</h1>
				{{#groups:}}
			</div>
			<div>
				<div class="location">{{#location:}}</div>
				{{#profilelinks:}}
			</div>
			<div class="aboutme">
				{{#aboutme:}}
			</div>
		</div>
		<div class="activity section">
			<p class="rightfloat">[[Special:Contributions/%1$s|'.wfMessage('contributions')->text().']]</p>
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
				<div class="score">{{#Points: User:%1$s | all | badged}}</div>
				<div class="level">{{#userlevel:}}</div>
				<div>{{#editorfriends:}}</div>
			</div>
			<div class="favorite">{{#favwiki:}}</div>
		</div>
		<div class="section stats">
			<h3>'.wfMessage('cp-statisticssection').'</h3>
			<USERSTATS>
			{{#friendlist: %2$s}}
		</div>
		{{#if: %5$s | <div class="section achievements">
			<h3>'.wfMessage('cp-achievementssection').'</h3>
			<h4>'.wfMessage('achievements-local')->plain().'</h4>
			{{#achievements:local|20}}
			<h4>'.wfMessage('achievements-global').'</h4>
			{{#achievements:global|20}}
		</div> }}
	</div>
	{{#if: %6$s | <div class="blocked"></div> }}
</div>
__NOTOC__
',
			$this->user_name,
			$this->user->getID(),
			$this->user->getEmail(),
			$this->user->getTitleKey(),
			'', // no achievements
			// ( $this->user->curse_id > 0 ? 'true' : '' ), /* delete above and uncomment for achievement block */
			( $this->user->isBlocked() ? 'true' : '' )
		);
	}
}
