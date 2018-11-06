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
	 * @var		string
	 */
	protected $userName;

	/**
	 * @var		integer
	 */
	protected $userId;

	/**
	 * @var		object	User instance
	 */
	protected $user;

	/**
	 * @var		object	ProfileData instance
	 */
	protected $profile;

	/**
	 * Whether or not the current page being rendered is with action=view.
	 *
	 * @var		boolean
	 */
	private $actionIsView;

	/**
	 * An array of groups to be excluded from display on profiles
	 *
	 * @var		array
	 */
	private $hiddenGroups = [
		'*',
		'ads_manager',
		'autoconfirmed',
		'checkuser',
		'hydra_admin',
		'widget_editor',
		'wiki_manager'
	];

	/**
	 * Whether or not we're on a mobile view.
	 *
	 * @var		boolean
	 */
	private $mobile;

	/**
	 * Namespaces that CurseProfile operates inside.
	 *
	 * @var		array
	 */
	static private $userNamespaces = [NS_USER, NS_USER_TALK, NS_USER_PROFILE];

	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @param	object	Title $title
	 * @param	object	IContextSource $context
	 * @return	void
	 */
	public function __construct($title, $context = null) {
		parent::__construct($title);
		if ($context) {
			$this->setContext($context);
		}
		$this->actionIsView = \Action::getActionName($this->getContext()) == 'view';
		$userName = $title->getText();
		if (strpos($userName, '/') > 0) {
			$_userNames = explode('/', $title->getText());
			$userName = array_shift($_userNames);
		}
		$this->userName = \User::getCanonicalName($userName);
		$this->user = \User::newFromName($userName);
		if ($this->user) {
			$this->user->load();
			$this->userId = $this->user->getID();
		} else {
			$this->user = \User::newFromId(0);
			$this->userId = 0;
		}

		$this->mobile = \HydraCore::isMobileSkin($this->getContext()->getSkin());
		$this->profile = new ProfileData($this->userId);
	}

	/**
	 * Create a new instance from a page title.  This is the preferred entry point since it handles if the title is in an usable namespace.
	 *
	 * @access	public
	 * @param	object	Title $title
	 * @param	object	IContextSource $context
	 * @return	mixed	New self or false for a bad title.
	 */
	static public function newFromTitle($title, \IContextSource $context = null) {
		if (in_array($title->getNamespace(), self::$userNamespaces)) {
			//We do not call the parent newFromTitle since it could return the wrong class.
			return new self($title, $context);
		}
		return false;
	}

	/**
	 * Primary rendering function for mediawiki's Article
	 */
	public function view() {
		$output = $this->getContext()->getOutput();
		$output->setPageTitle($this->getTitle()->getPrefixedText());
		$output->setArticleFlag(false);

		$layout = ($this->mobile ? $this->mobileProfileLayout() : $this->profileLayout());
		$layout = str_replace('<USERSTATS>', $this->userStats(), $layout);

		$outputString = \MessageCache::singleton()->parse($layout, $this->getTitle());
		if ($outputString instanceof \ParserOutput) {
			$outputString = $outputString->getText();
		}
		$output->addHTML($outputString);
	}

	/**
	 * Return the User object for this profile.
	 *
	 * @access	public
	 * @return	object	User
	 */
	public function getUser($audience = \Revision::FOR_PUBLIC, \User $user = null) {
		return $this->user;
	}

	/**
	 * Return the User object for who created this profile.(The user, technically.)
	 *
	 * @access	public
	 * @return	object	User
	 */
	public function getCreator($audience = \Revision::FOR_PUBLIC, \User $user = null) {
		return $this->user;
	}

	/**
	 * Shortcut method to retrieving the user's profile page preference
	 *
	 * @access	public
	 * @return	boolean	True if profile page is preferred, false if wiki is preferred.
	 */
	public function isProfilePreferred() {
		return $this->profile->getProfileTypePreference();
	}

	/**
	 * Shortcut method to retrieving the user's comment page preference
	 *
	 * @access	public
	 * @return	boolean	True if profile comment page is preferred, false if wiki is preferred.
	 */
	public function isCommentsPreferred() {
		return $this->profile->getCommentTypePreference();
	}

	/**
	 * True if we are not on a subpage, and if we are in the basic User namespace,
	 * or either of the custom UserProfile/UserWiki namespaces.
	 *
	 * @access	public
	 * @param	object	[Optional] Title object to check instead of the assumed.
	 * @return	boolean
	 */
	public function isUserPage($title = null) {
		if ($title === null) {
			$title = $this->getTitle();
		}
		return $title->getNamespace() == NS_USER;
	}

	/**
	 * True if we are viewing a user_talk namespace page.
	 *
	 * @access	public
	 * @param	object	[Optional] Title object to check instead of the assumed.
	 * @return	boolean
	 */
	public function isUserTalkPage($title = null) {
		if ($title === null) {
			$title = $this->getTitle();
		}
		return $title->getNamespace() == NS_USER_TALK;
	}

	/**
	 * True if we need to render the user's profile page.
	 *
	 * @access	public
	 * @param	object	[Optional] Title object to check instead of the assumed.
	 * @return	boolean
	 */
	public function isProfilePage($title = null) {
		if ($title === null) {
			$title = $this->getTitle();
		}
		return $title->getNamespace() == NS_USER_PROFILE;
	}

	/**
	 * Is the action for this page 'view'?
	 *
	 * @access	public
	 * @return	boolean
	 */
	public function isActionView() {
		return $this->actionIsView;
	}

	/**
	 * Returns the title object for the user's page in the UserProfile namespace
	 *
	 * @access	public
	 * @return	\Title instance
	 */
	public function getUserProfileTitle() {
		return \Title::makeTitle(NS_USER_PROFILE, $this->user->getName());
	}

	/**
	 * Adjusts the links in the primary action bar on profile pages and user wiki pages.
	 *
	 * @access	public
	 * @param	array	Structured info on what links will appear on the rendered page.
	 * @param	object	Title of the page the user is on in the User or User_talk namespace.
	 * @return	void
	 */
	public function customizeNavBar(&$links, $title) {
		// Using $this->user will result in a bad User object in the case of MediaWiki #REDIRECT pages
		// since the context is switched without performing a HTTP redirect.
		$userName = $title->getText();

		if ($userName === false) {
			if ($this->isProfilePage($title)) {
				$links['views'] = [];
				unset($links['namespaces']['userprofile_talk']);
			}
			return;
		}

		$profileTitle = $this->isProfilePage($title) ? $title : \Title::makeTitle(NS_USER_PROFILE, $userName);
		$userPageTitle = $this->isUserPage($title) ? $title : \Title::makeTitle(NS_USER, $userName);
		$userTalkPageTitle = $this->isUserTalkPage($title) ? $title : \Title::makeTitle(NS_USER_TALK, $userName);

		$links['namespaces'] = [];

		if ($this->isProfilePage($title)) {
			// Reset $links to prevent hidden article.
			$links = [
				'namespaces' => [],
				'views' => [],
				'actions' => [],
				'variants' => []
			];
		}

		// Build Link for Profile
		$links['namespaces']['userprofile'] = [
			'class'		=> $this->isProfilePage($title) ? 'selected' : '',
			'href'		=> $profileTitle->getFullURL(),
			'text'		=> wfMessage('userprofiletab')->text(),
			'primary'	=> true
		];

		// Build Link for User Page
		$class = [];
		if ($this->isUserPage($title)) {
			$class[] = 'selected';
		}
		if (!$userPageTitle->isKnown()) {
			$class[] = 'new';
		}
		$links['namespaces']['user'] = [
			'class'		=> implode(' ', $class),
			'text'		=> wfMessage('nstab-' . $userPageTitle->getNamespaceKey(''))->text(),
			'href'		=> $this->profile->getUserPageUrl($userPageTitle),
			'primary'	=> true
		];

		// Build Link for User Talk Page
		$class = [];
		if ($this->isUserTalkPage($title)) {
			$class[] = 'selected';
		}
		if (!$userTalkPageTitle->isKnown()) {
			$class[] = 'new';
		}
		$links['namespaces']['user_talk'] = [
			'class'		=> implode(' ', $class),
			'text'		=> wfMessage('talk')->text(),
			'href'		=> $this->profile->getTalkPageUrl($userTalkPageTitle),
			'primary'	=> true
		];

		$links['views']['contribs'] = [
			'class'	=> false,
			'text'	=> wfMessage('contributions')->text(),
			'href'	=> \SpecialPage::getTitleFor('Contributions', $userName)->getFullURL(),
		];
	}

	/**
	 * Prints a gravatar image tag for a user
	 *
	 * @param	parser
	 * @param	integer	the square size of the avatar to display
	 * @param	string	user's email address
	 * @param	string	the user's username
	 * @param	string	additional html attributes to include in the IMG tag
	 * @return	string	the HTML fragment containing a IMG tag
	 */
	public static function userAvatar(&$parser, $size, $email, $userName, $attributeString = '') {
		$size = intval($size);
		$userName = htmlspecialchars($userName, ENT_QUOTES);
		return [
			"<img src='//www.gravatar.com/avatar/".md5(strtolower(trim($email)))."?d=mm&amp;s=$size' height='$size' width='$size' alt='".wfMessage('avataralt', $userName)->escaped()."' $attributeString />",
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
		global $wgUser;

		$groups = $this->user->getEffectiveGroups();
		if (count($groups) == 0) {
			return '';
		}

		$specialListUsersTitle = \SpecialPage::getTitleFor('ListUsers');
		$html = '<ul class="grouptags">';
		foreach ($groups as $group) {
			if (in_array($group, $this->hiddenGroups)) {
				continue;
			}
			$groupMessage = new \Message('group-'.$group);
			if ($groupMessage->exists()) {
				$html .= '<li>'.\Linker::linkKnown($specialListUsersTitle, $groupMessage->text(), [], ['group' => $group]).'</li>';
			} else {
				//Legacy fall back to make the group name appear pretty.  This handles cases of user groups that are central to one wiki and are not localized.
				$html .= '<li>'.mb_convert_case(str_replace("_", " ", htmlspecialchars($group)), MB_CASE_TITLE, "UTF-8").'</li>';
			}
		}
		//Check the rights of the person viewing this page.
		if ($wgUser->isAllowed('userrights')) {
			$html .= "<li class=\"edit\">".\Linker::linkKnown(\Title::newFromText('Special:UserRights/'.$this->user->getName()), \HydraCore::awesomeIcon('pencil-alt'))."</li>";
		}
		$html .= '</ul>';

		return [
			$html,
			'isHTML' => true
		];
	}

	/**
	 * Performs the work for the parser tag that displays the user's location.
	 *
	 * @param	object	parser reference
	 * @return	mixed	array with HTML string at index 0 or an HTML string
	 */
	public function location(&$parser) {
		$location = $this->profile->getLocation();

		return [
			array_map('htmlentities', $location),
			'isHTML' => true,
		];
	}

	/**
	 * Performs the work for the parser tag that displays the user's "About Me" text
	 *
	 * @access	public
	 * @param	object	Parser reference.
	 * @param	string	Field name to retrieve.
	 * @return	mixed	array with HTML string at index 0 or an HTML string
	 */
	public function fieldBlock(&$parser, $field) {
		global $wgOut, $wgUser;

		return [
			$this->profile->getFieldHtml($field),
			'isHTML' => true
		];
	}

	/**
	 * Generate Profile Links HTML
	 *
	 * @access	public
	 * @param	array	Profile Links
	 * @return	string	HTML
	 */
	static public function generateProfileLinks($profileLinks) {
		$html = '<ul class="profilelinks">';
		if (count($profileLinks)) {
			foreach ($profileLinks as $service => $text) {
				if (!empty($text)) {
					$item = "<li class='{$service}' title='{$service}'>";
					$html .= "<!-- {$service} $text -->";
					$item .= \Html::element('a', ['href' => ProfileData::getExternalProfileLink($service, $text), 'target' => '_blank']);
					if (!empty($item)) {
						$item .= '</li>';
					}
					$html .= $item;
				}
			}
		}
		$html .= '</ul>';
		return $html;
	}

	/**
	 * Performs the work for the parser tag that displays a user's links to other gaming profiles.
	 *
	 * @param	object	parser reference
	 * @return	mixed	array with HTML string at index 0 or an HTML string
	 */
	public function profileLinks(&$parser = null) {
		global $wgUser;

		return [
			$this->profile->getProfileLinksHtml(),
			'isHTML' => true
		];
	}

	/**
	 * Performs the work for the parser tag that displays the user's chosen favorite wiki
	 *
	 * @param	object	parser reference
	 * @return	mixed	array with HTML string at index 0 or an HTML string
	 */
	public function favoriteWiki(&$parser) {
		$wiki = $this->profile->getFavoriteWiki();
		if (empty($wiki)) {
			return '';
		}

		$imgPath = CP::getWikiImageByHash($wiki['md5_key']);
		if (!empty($imgPath) && !empty($wiki['md5_key'])) {
			$html = \Html::element('img', ['src' => $imgPath, 'height' => 118, 'width' => 157, 'alt' => $wiki['wiki_name']]);
		} else {
			$html = htmlspecialchars($wiki['wiki_name']);
		}

		$title = \Title::newFromText('UserProfile:'.$this->user->getTitleKey());
		$link = "https://".$wiki['wiki_domain'].$title->getLocalURL();

		$html = "<a target='_blank' href='{$link}'>".$html."</a>";
		$html = wfMessage('favoritewiki')->plain().'<br/>' . $html;

		return [
			$html,
			'isHTML' => true,
		];
	}

	/**
	 * Performs the work for the parser tag that displays user statistics.
	 * The numbers themselves are pulled from the Cheevos API.
	 *
	 * @return	string	generated HTML fragment
	 */
	public function userStats() {
		global $wgServer, $dsSiteKey;

		$lookup = \CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($this->user, \CentralIdLookup::AUDIENCE_RAW);

		$stats = [];
		$wikisEdited = 0;
		if ($globalId > 0) {
			try {
				$stats = \Cheevos\Cheevos::getStatProgress(
					[
						'user_id'	=> $globalId,
						'global'	=> true
					]
				);
				$stats = \Cheevos\CheevosHelper::makeNiceStatProgressArray($stats);
			} catch (\Cheevos\CheevosException $e) {
				wfDebug("Encountered Cheevos API error getting Stat Progress.");
			}
			try {
				$wikisEdited = intval(\Cheevos\Cheevos::getUserSitesCountByStat($globalId, 'article_edit'));
			} catch (\Cheevos\CheevosException $e) {
				wfDebug("Encountered Cheevos API error getting getUserSitesCountByStat.");
			}
		}

		//Keys are message keys fed to wfMessage().
		//Values are numbers or an array of sub-stats with a number at key 0.
		if (!empty($stats)) {
			$statsOutput = [
				'wikisedited' => $wikisEdited,
				'totalcontribs' => [
					'totalcreations'   => (isset($stats[$globalId]['article_create']) ? $stats[$globalId]['article_create']['count'] : 0),
					'totaledits'   => (isset($stats[$globalId]['article_edit']) ? $stats[$globalId]['article_edit']['count'] : 0),
					'totaldeletes' => (isset($stats[$globalId]['article_delete']) ? $stats[$globalId]['article_delete']['count'] : 0),
					'totalpatrols' => (isset($stats[$globalId]['admin_patrol']) ? $stats[$globalId]['admin_patrol']['count'] : 0),
				],
				'localrank' => '',
				'globalrank' => '', // data for these fills in below
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

		if ($globalId > 0 && method_exists('\Cheevos\Cheevos', 'getUserPointRank')) {
			try {
				$statsOutput['localrank'] = \Cheevos\Cheevos::getUserPointRank($globalId, $dsSiteKey);
				$statsOutput['globalrank'] = \Cheevos\Cheevos::getUserPointRank($globalId);

				if (empty($statsOutput['localrank'])) {
					unset($statsOutput['localrank']);
				}
				if (empty($statsOutput['globalrank'])) {
					unset($statsOutput['globalrank']);
				}
			} catch (\Cheevos\CheevosException $e) {
				wfDebug(__METHOD__.": Caught CheevosException - ".$e->getMessage());
			}

			$statsOutput['totalfriends'] = FriendDisplay::count($nothing, $this->user->getId());
		} else {
			//No global ID or Cheevos extension available.
			unset($statsOutput['localrank']);
			unset($statsOutput['globalrank']);
		}

		$html = $this->generateStatsDL($statsOutput);

		return $html;
	}

	/**
	 * Recursive function for parsing out and stringifying the stats array above
	 *
	 * @param	mixed	arrays will generate a new list, other values will be directly returned
	 * @return	string	html DL fragment or $input if it is not an array
	 */
	public function generateStatsDL($input) {
		global $wgUser;
		if (is_array($input)) {
			$output = "<dl>";
			foreach ($input as $msgKey => $value) {
				if (is_string($msgKey)) {
					$output .= "<dt>".wfMessage($msgKey, $this->userId, $wgUser->getId())->plain()."</dt>";
				}
				// check for sub-list, if there is one
				if (is_array($value)) {
					// might have a roll-up total for the sub-list's header
					if (array_key_exists(0, $value)) {
						$output .= "<dd>".$this->generateStatsDL( $value[0] )."</dd>";
						// Discard the value for the sublist header so it isn't printed a second time as a member of the sublist
						array_shift($value);
					}
					// generate the sub-list
					$output .= $this->generateStatsDL($value);
				} else {
					// not a sub-list, just add a plain value
					$output .= "<dd>".$this->generateStatsDL( $value )."</dd>";
				}
			}
			$output .= "</dl>";
			return $output;
		} else {
			// just a simple value
			if (is_numeric($input)) {
				return number_format($input);
			} elseif (is_null($input)) {
				return '0';
			} else {
				return $input;
			}
		}
	}

	/**
	 * Display the icons of the recent achievements the user has earned, for the sidebar
	 *
	 * @param	object	parser reference
	 * @param	string	type of query. one of: local, master (default)
	 * @param	integer	maximum number to display
	 * @return	array
	 */
	public function recentAchievements(&$parser, $type = 'special', $limit = 0) {
		global $dsSiteKey;

		$output = '';

		if (!class_exists("\CheevosHooks")) {
			return [
				$output,
				'isHTML'	=> true
			];
		}

		$lookup = \CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($this->user, \CentralIdLookup::AUDIENCE_RAW);

		$earned = [];
		if ($globalId > 0 && class_exists("\CheevosHooks")) {
			$achievements = [];
			$progresses = [];
			try {
				$achievements = \Cheevos\Cheevos::getAchievements($dsSiteKey);
			} catch (\Cheevos\CheevosException $e) {
				wfDebug("Encountered Cheevos API error getting site achievements.");
			}
			if ($type === 'special') {
				try {
					foreach (\Cheevos\Cheevos::getAchievements() as $achievement) {
						$achievements[$achievement->getId()] = $achievement;
					}
				} catch (\Cheevos\CheevosException $e) {
					wfDebug("Encountered Cheevos API error getting all achievements.");
				}
			}
			$achievements = \Cheevos\CheevosAchievement::correctCriteriaChildAchievements($achievements);

			if ($type === 'general') {
				try {
					$progresses = \Cheevos\Cheevos::getAchievementProgress(['user_id' => $globalId, 'site_key' => $dsSiteKey, 'earned' => true, 'special' => false, 'shown_on_all_sites' => true, 'limit' => intval($limit)]);
				} catch (\Cheevos\CheevosException $e) {
					wfDebug("Encountered Cheevos API error getting Achievement Progress.");
				}
			}

			if ($type === 'special') {
				try {
					$progresses = \Cheevos\Cheevos::getAchievementProgress(['user_id' => $globalId, 'earned' => true, 'special' => true, 'shown_on_all_sites' => true, 'limit' => intval($limit)]);
					//list($achievements, $progresses) = \Cheevos\CheevosAchievement::pruneAchievements([$achievements, $progresses], true, true);
				} catch (\Cheevos\CheevosException $e) {
					wfDebug("Encountered Cheevos API error getting Achievement Progress.");
				}
			}
			list($achievements, $progresses) = \Cheevos\CheevosAchievement::pruneAchievements([$achievements, $progresses], true, true);
		}

		if (empty($progresses)) {
			return [
				$output,
				'isHTML'	=> true
			];
		}

		if (count($progresses)) {
			$output .= '<h4>'.$parser->recursiveTagParse(wfMessage('achievements-'.$type)->text()).'</h4>';
		}
		foreach ($progresses as $progress) {
			if (!isset($achievements[$progress->getAchievement_Id()])) {
				continue;
			}
			$ach = $achievements[$progress->getAchievement_Id()];
			$output .= \Html::rawElement(
				'div',
				[
					'class'	=> ['icon', $type]
				],
				\Html::element(
					'img',
					[
						'src'	=> $ach->getImageUrl(),
						'title'	=> $ach->getName()."\n".$ach->getDescription()
					]
				).($type == 'special' ? \Html::rawElement('span', ['title' => $ach->getName($progress->getSite_Key())."\n".$ach->getDescription()], $ach->getName($progress->getSite_Key())) : null)
			);
		}
		return [
			$output,
			'isHTML' => true
		];
	}

	/**
	 * Performs the work for the parser tag that displays the user's level (based on wikipoints)
	 *
	 * @param	object	parser reference
	 * @return	mixed	array with HTML string at index 0 or an HTML string
	 */
	public function userLevel(&$parser) {
		$lookup = \CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($this->user, \CentralIdLookup::AUDIENCE_RAW);

		// Check for existance of wikipoints functions
		if (!$globalId || !method_exists('\Cheevos\Points\PointsDisplay', 'getWikiPointsForRange')) {
			return '';
		}

		$redis = \RedisCache::getClient('cache');
		$userPoints = \Cheevos\Points\PointsDisplay::getWikiPointsForRange($globalId);
		$levelDefinitions = \Cheevos\Points\PointLevels::getLevels();

		if (!is_array($levelDefinitions)) {
			return '';
		}

		$html = '';
		foreach ($levelDefinitions as $tier) {
			// assuming that the definitions array is sorted by level ASC, overwriting previous iterations
			if ($userPoints >= $tier['points']) {
				// TODO display $tier['image_icon'] or $tier['image_large']
				$html = \Html::element(
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
			$html,
			'isHTML' => true,
		];
	}

	/**
	 * Parser hook function that inserts either an "edit profile" button or a "add/remove friend" button
	 *
	 * @access	public
	 * @param	$parser
	 * @return	array	with html as the first element
	 */
	public function editOrFriends(&$parser) {
		$html = FriendDisplay::addFriendButton($this->userId);

		if ($this->profile->isViewingSelf()) {
			$html .= \Html::element(
				'button',
				[
					'data-href'	=> \Title::newFromText('Special:Preferences')->getFullURL().'#mw-prefsection-personal-info-public',
					'class'		=> 'linksub'
				],
				wfMessage('cp-editprofile')->plain()
			);
		}

		return [
			$html,
			'isHTML' => true,
		];
	}

	/**
	 * Defines the HTML structure of the profile page.
	 *
	 * @return	string
	 */
	protected function profileLayout() {
		global $wgUser;

		$lookup = \CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($this->user, \CentralIdLookup::AUDIENCE_RAW);

		$classes = false;
		if (!empty($this->user) && $this->user->getId()) {
			$_cacheSetting = \Hydra\Subscription::skipCache(true);
			$subscription = \Hydra\Subscription::newFromUser($this->user);
			if ($subscription !== false) {
				$classes = $subscription->getFlairClasses();
				if (empty($classes)) {
					$classes = false; //Enforce sanity.
				}
			}
			\Hydra\Subscription::skipCache($_cacheSetting);
		}

		return  '
<div class="curseprofile" data-userid="'.$this->user->getID().'">
	<div class="leftcolumn">
		<div class="userinfo borderless section">
			<div class="mainavatar">{{#avatar: 96 | '.$this->user-> getEmail().' | '.$this->user->getName().'}}</div>
			<div class="headline">
				<h1'.($classes !== false ? ' class="'.implode(' ', $classes).'"' : '').'>'.$this->user->getName().'</h1>
				{{#groups:}}
			</div>
			<div>
				<div id="profile-location" data-field="location">{{#profilefield:location}}</div>
				{{#profilelinks:}}
			</div>
			<div id="profile-aboutme" data-field="aboutme">
				{{#profilefield:aboutme}}
			</div>
		</div>
		<div class="activity section">
			<p class="rightfloat">[[Special:Contributions/'.$this->user->getName().'|'.wfMessage('contributions')->text().']]</p>
			<h3>'.wfMessage('cp-recentactivitysection').'</h3>
			{{#recentactivity: '.$this->user->getID().'}}
		</div>
		<div class="comments section">
			<p class="rightfloat">[[Special:CommentBoard/'.$this->user->getID().'/'.$this->user->getTitleKey().'|'.wfMessage('commentarchivelink')->plain().']]</p>
			<h3>'.wfMessage('cp-recentcommentssection').'</h3>
			{{#comments: '.$this->user->getID().'}}
		</div>
	</div>
	<div class="rightcolumn">
		<div class="borderless section">
			<div class="rightfloat">
				<div class="score">{{#Points:'.$this->user->getName().'|1|global|badged}}</div>
				<div class="level">{{#userlevel:}}</div>
				<div>{{#editorfriends:}}</div>
			</div>
			<div class="favorite">{{#favwiki:}}</div>
		</div>
		<div class="section stats">
			<h3>'.wfMessage('cp-statisticssection')->plain().'</h3>
			<USERSTATS>
		</div>
		<div class="section friends">
			<h3>'.wfMessage('cp-friendssection')->plain().'</h3>
			{{#friendlist: '.$this->user->getID().'}}<br />
			<div style="float: right;">'.wfMessage('cp-friendssection-all', $this->user->getId(), $wgUser->getId(), $this->user->getTitleKey())->plain().'</div>
		</div>
		{{#if: '.( $globalId ? 'true' : '' ).' | <div class="section achievements">
			<h3>'.wfMessage('cp-achievementssection')->plain().'</h3>
			{{#achievements:general}}
			{{#achievements:special}}
			<div style="clear: both;"></div>
			<div style="float: right; clear: both;">'.wfMessage('cp-achievementssection-all', $this->user->getName())->plain().'</div>
		</div> }}
	</div>
	{{#if: '.( $this->user->isBlocked() ? 'true' : '' ).' | <div class="blocked"></div> }}
</div>
__NOTOC__
__NOINDEX__
';
	}

	/**
	 * Defines the HTML structure of the profile page for mobile devices.
	 *
	 * @return	string
	 */
	protected function mobileProfileLayout() {
		global $wgUser;

		$lookup = \CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($this->user, \CentralIdLookup::AUDIENCE_RAW);

		return '
<div class="curseprofile" id="mf-curseprofile" data-userid="'.$this->user->getID().'">
		<div class="userinfo section">
			<div class="mainavatar">{{#avatar: 96 | '.$this->user-> getEmail().' | '.$this->user->getName().'}}</div>
			<div class="usericons rightfloat">
				<div class="score">{{#Points:'.$this->user->getName().'|1|global|badged}}</div>
				{{#profilelinks:}}
			</div>
		</div>
		<h1>'.wfMessage('cp-mobile-aboutme').'</h1>
		{{#profilefield:aboutme}}
		<h1>'.wfMessage('cp-mobile-groups').'</h1>
		{{#groups:}}
		<h1>'.wfMessage('cp-statisticssection').'</h1>
		<USERSTATS>
		<h1>'.wfMessage('cp-friendssection')->plain().'</h1>
		{{#friendlist: '.$this->user->getID().'}}
		<div style="float: right;">'.wfMessage('cp-friendssection-all', $this->user->getId(), $wgUser->getId(), $this->user->getTitleKey())->plain().'</div>
		{{#if: '.( $globalId ? 'true' : '' ).' | <h1>'.wfMessage('cp-achievementssection')->plain().'</h1>
		<div class="section achievements">
			{{#achievements:general}}
			{{#achievements:special}}
			<div style="clear: both;"></div>
			<div style="float: right; clear: both;">'.wfMessage('cp-achievementssection-all', $this->user->getName())->plain().'</div>
		</div>

		}}
		<h1>'.wfMessage('cp-recentactivitysection').'</h1>
		<p>[[Special:Contributions/'.$this->user->getName().'|'.wfMessage('contributions')->text().']]</p>
		{{#recentactivity: '.$this->user->getID().'}}
		<h1>'.wfMessage('cp-recentcommentssection').'</h1>
		<p>[[Special:CommentBoard/'.$this->user->getID().'/'.$this->user->getTitleKey().'|'.wfMessage('commentarchivelink').']]</p>
		{{#comments: '.$this->user->getID().'}}
	{{#if: '.( $this->user->isBlocked() ? 'true' : '' ).' | <div class="blocked"></div> }}
</div>
__NOTOC__
__NOINDEX__
';
	}
}
