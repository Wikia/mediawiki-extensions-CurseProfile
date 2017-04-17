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
	private $hiddenGroups = ['hydra_admin', '*', 'autoconfirmed', 'checkuser', 'ads_manager', 'widget_editor', 'wiki_manager'];

	/**
	 * Whether or not we're on a mobile view.
	 * @var	bool
	 */
	private $mobile;

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

		$this->mobile = \HydraCore::isMobileSkin($this->getContext()->getSkin());
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

		$layout = ($this->mobile ? $this->mobileProfileLayout() : $this->profileLayout());
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
			&& in_array($this->getTitle()->getNamespace(), [NS_USER, NS_USER_PROFILE]);
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
	 * True if we are viewing a user_talk namespace page.
	 *
	 * @return	bool
	 */
	public function isTalkPage() {
		return $this->getTitle()->getNamespace() == NS_USER_TALK;
	}

	/**
	 * True if we need to render the user's profile page on either namespace
	 *
	 * @param	bool	[optional] if true (default), will return false for any action other than 'view'
	 * @return	bool
	 */
	public function isProfilePage($onlyView = true) {
		return $this->isUserPage($onlyView) && (
				(	$this->profile->getTypePref() &&
					$this->getTitle()->getNamespace() == NS_USER &&
					$this->getContext()->getRequest()->getVal('profile') !== "no" ) ||
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
					(!$this->profile->getTypePref() && $this->getTitle()->getNamespace() == NS_USER)
				);
		} else {
			return $this->isUserWikiPage(true) || (
				$this->isUserPage(false) && ($this->getTitle()->getNamespace() == NS_USER && !$this->actionIsView)
			);
		}
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
	 * Returns the title object for the user's page in the UserProfile namespace
	 * @return	\Title instance
	 */
	public function getCustomUserProfileTitle() {
		return \Title::makeTitle(NS_USER_PROFILE, $this->user->getName());
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

			$links['namespaces']['userprofile'] = [
				'href' => $this->profile->getProfilePath(),
				'text' => wfMessage('userprofiletab')->text(),
				'class' => 'selected'
			];

			// add link to user wiki
			$links['namespaces']['user'] = [
				'class'		=> false,
				'text'		=> wfMessage('userwikitab')->text(),
				'href'		=> $this->profile->getUserWikiPath(),
			];

			$links['namespaces']['user_talk'] = [
				'class'		=> false,
				'text'		=> wfMessage('talk')->text(),
				'href'		=> $this->user->getTalkPage()->getLinkURL(),
			];

			$links['views']['contribs'] = [
				'class'		=> false,
				'text'		=> wfMessage('contributions')->text(),
				'href'		=> \SpecialPage::getTitleFor('Contributions', $this->user_name)->getFullURL(),
			];
		}

		if($this->isDefaultPage() || $this->isTalkPage()) {
			// add user profile link to user page
			$links['namespaces']['userprofile'] = [
				'href' => $this->profile->getProfilePath(),
				'text' => wfMessage('userprofiletab')->text(),
				'class' => false
			];
			/*$links['views']['contribs'] = [
				'class'		=> false,
				'text'		=> wfMessage('contributions')->text(),
				'href'		=> \SpecialPage::getTitleFor('Contributions', $this->user_name)->getFullURL(),
			];*/
		}

		// links specific to a user wiki page
		if ($this->isUserWikiPage(false)) {
			$links['namespaces']['userprofile'] = [
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
	 * @param	integer	the square size of the avatar to display
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

		$specialListUsersTitle = \SpecialPage::getTitleFor('ListUsers');
		$HTML = '<ul class="grouptags">';
		foreach ($groups as $group) {
			if (in_array($group, $this->hiddenGroups)) {
				continue;
			}
			$groupMessage = new \Message('group-'.$group);
			if ($groupMessage->exists()) {
				$HTML .= '<li>'.\Linker::linkKnown($specialListUsersTitle, $groupMessage->text(), [], ['group' => $group]).'</li>';
			} else {
				//Legacy fall back to make the group name appear pretty.  This handles cases of user groups that are central to one wiki and are not localized.
				$HTML .= '<li>'.mb_convert_case(str_replace("_", " ", htmlspecialchars($group)), MB_CASE_TITLE, "UTF-8").'</li>';
			}
		}
		$HTML .= '</ul>';

		return [
			$HTML,
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
		$locations = $this->profile->getLocations();

		$HTML = '';

		if (isset($locations['country-flag'])) {
			$src = \FlagFinder::getFlagPath($locations['country-flag']);
			$HTML = "<img src='$src' class='countryflag' alt='flag for {$locations['country-flag']}'/> ".$HTML;
			unset($locations['country-flag']);
		}

		$HTML .= implode(', ', array_map('htmlentities', $locations));

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

		if ($wgUser->isAllowed('profile-moderate') || $this->viewingSelf()) {
			if (empty($aboutText)) {
				$aboutText = \Html::element('em', [], wfMessage(($this->viewingSelf() ? 'empty_about_text' : 'empty_about_text_mod'))->plain());
			}

			$aboutText = \Html::rawElement(
				'a',
				[
					'class'	=> 'rightfloat profileedit',
					'href'	=> '#',
					'title' =>	wfMessage('editaboutme-tooltip')->plain()
				],
				\HydraCore::awesomeIcon('pencil')
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
					$item .= \Html::element('a', ['href' => "https://live.xbox.com/en-US/Profile?gamertag=$link", 'target' => '_blank']);
					break;
				case 'PSN':
					$link = urlencode($link);
					$item .= \Html::element('a', ['href' => "http://psnprofiles.com/$link", 'target' => '_blank']);
					break;
				case 'Twitter':
					if (!self::validateUrl($key, $link)) {
						$item = '';
					} else {
						$item .= \Html::element('a', ['href' => "https://twitter.com/$link", 'target' => '_blank']);
					}
					break;
				case 'Reddit':
					if (!self::validateUrl($key, $link)) {
						$item = '';
					} else {
						$item .= \Html::element('a', ['href' => "https://www.reddit.com/user/$link", 'target' => '_blank']);
					}
					break;
				default:
					if (self::validateUrl($key, $link)) {
						$item .= \Html::element('a', ['href' => $link, 'target' => '_blank']);
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
		$wiki = $this->profile->getFavoriteWiki();
		if (empty($wiki)) {
			return '';
		}

		$imgPath = CP::getWikiImageByHash($wiki['md5_key']);
		if (!empty($imgPath) && !empty($wiki['md5_key'])) {
			$HTML = \Html::element('img', ['src' => $imgPath, 'height' => 118, 'width' => 157, 'alt' => $wiki['wiki_name']]);
		} else {
			$HTML = htmlspecialchars($wiki['wiki_name']);
		}
		$HTML = "<a target='_blank' href='https://{$wiki['wiki_domain']}/'>".$HTML."</a>";
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
		global $wgServer, $dsSiteKey;

		$lookup = \CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($this->user, \CentralIdLookup::AUDIENCE_RAW);

		$stats = [];
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
				wfDebug("Encountered Cheevos API error getting Stat Progress");
			}
		}

		//Keys are message keys fed to wfMessage().
		//Values are numbers or an array of sub-stats with a number at key 0.
		if (!empty($stats)) {
			$statsOutput = [
				'wikisedited' => $stats[$globalId]['other']['wikis_contributed'],
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

		if ($globalId > 0 && method_exists('PointsDisplay', 'pointsForGlobalId')) {
			$statsOutput['localrank'] = \PointsDisplay::pointsForGlobalId($globalId, \WikiPoints::extractDomain($wgServer))['rank'];
			$statsOutput['globalrank'] = \PointsDisplay::pointsForGlobalId($globalId)['rank'];

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
	public function generateStatsDL($input) {
		global $wgUser;
		if (is_array($input)) {
			$output = "<dl>";
			foreach ($input as $msgKey => $value) {
				if (is_string($msgKey)) {
					$output .= "<dt>".wfMessage($msgKey, $this->user_id, $wgUser->getId())->plain()."</dt>";
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
			try {
				$achievements = \Cheevos\Cheevos::getAchievements($dsSiteKey);
			} catch (\Cheevos\CheevosException $e) {
				wfDebug("Encountered Cheevos API error getting all achievements.");
			}
			if ($type === 'general') {
				try {
					$_progresses = \Cheevos\Cheevos::getAchievementProgress(['user_id' => $globalId, 'site_key' => $dsSiteKey, 'earned' => true, 'special' => false, 'shown_on_all_sites' => true, 'limit' => intval($limit)]);
					if (!empty($_progresses)) {
						foreach ($_progresses as $_progress) {
							$earned[$_progress->getAchievement_Id()] = $_progress;
						}
					}
				} catch (\Cheevos\CheevosException $e) {
					wfDebug("Encountered Cheevos API error getting Achievement Progress");
				}
			}

			if ($type === 'special') {
				try {
					$_progresses = \Cheevos\Cheevos::getAchievementProgress(['user_id' => $globalId, 'site_key' => $dsSiteKey, 'earned' => true, 'special' => true, 'shown_on_all_sites' => true, 'limit' => intval($limit)]);
					if (!empty($_progresses)) {
						foreach ($_progresses as $_progress) {
							$earned[$_progress->getAchievement_Id()] = $_progress;
						}
					}
				} catch (\Cheevos\CheevosException $e) {
					wfDebug("Encountered Cheevos API error getting Achievement Progress");
				}
			}
		}

		if (empty($earned)) {
			return [
				$output,
				'isHTML'	=> true
			];
		}

		if (count($earned)) {
			$output .= '<h4>'.$parser->recursiveTagParse(wfMessage('achievements-'.$type)->text()).'</h4>';
		}
		foreach ($earned as $progress) {
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
		if (!$globalId || !method_exists('PointsDisplay', 'pointsForGlobalId')) {
			return '';
		}

		$redis = \RedisCache::getClient('cache');
		$userPoints = \PointsDisplay::pointsForGlobalId($globalId)['score'];
		try {
			if ($redis !== false) {
				$levelDefinitions = unserialize($redis->get('wikipoints::levels'));
			}
		} catch (\Throwable $e) {
			wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			return '';
		}

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
				<div class="location">{{#location:}}</div>
				{{#profilelinks:}}
			</div>
			<div class="aboutme">
				{{#aboutme:}}
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
				<div class="score">{{#Points: User:'.$this->user->getName().' | all | badged}}</div>
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
				<div class="score">{{#Points: User:'.$this->user->getName().' | all | badged}}</div>
				{{#profilelinks:}}
			</div>
		</div>
		<h1>'.wfMessage('cp-mobile-aboutme').'</h1>
		{{#aboutme:}}
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
';
	}
}
