<?php
/**
 * Curse Inc.
 * Curse Profile
 * Display stats on the adoption rate of CurseProfile across hydra
 *
 * @author		Noah Manneschmidt
 * @copyright	(c) 2014 Curse Inc.
 * @license		All Rights Reserved
 * @package		CurseProfile
 * @link		http://www.curse.com/
 *
**/
namespace CurseProfile;

class SpecialProfileStats extends \SpecialPage {
	public function __construct() {
		parent::__construct( 'ProfileStats' );

		global $wgRequest, $wgUser, $wgOut;
		$this->wgRequest	= $wgRequest;
		$this->wgUser		= $wgUser;
		$this->output		= $this->getOutput();
	}

	public function execute( $path ) {
		$this->setHeaders();
		if (!$this->wgUser->isAllowed('profilestats')) {
			throw new \PermissionsError('profilestats');
		}

		$this->mouse = \mouseNest::getMouse();
		global $IP;
		$this->mouse->output->addTemplateFolder($IP.'/extensions/Hydralytics/templates');
		$this->mouse->output->loadTemplate('hydralytics');

		$data = $this->mouse->redis->hgetall('profilestats');
		foreach ($data as $k => $v) {
			$this->$k = unserialize($v);
		}

		$this->output->addModules('ext.curseprofile.profilestats');
		$this->output->addHTML($this->buildOutput());
	}

	public function isListed() {
		return $this->wgUser->isAllowed('profilestats');
	}

	public function isRestricted() {
		return true;
	}

	private function buildOutput() {
		$HTML = '';

		$HTML .= "
			<div id='ps-adoption'>
				".$this->mouse->output->hydralytics->chartJS('Overall Adoption', $this->users, 'pie')."
				<div class='chart' id='chart-".md5('Overall Adoption')."'>
					<div class='loading'>Loading...</div>
				</div>
				<div id='adoption-chart'>".$this->buildTable($this->users,['key'=>'Profile Type','value'=>'Users'])."</div>
			</div>";

		$this->avgFriends = number_format($this->avgFriends, 2);
		$HTML .= "<h2>Actual Usage Stats</h2>"
		."<div id='friends-system'><h3>Friends System</h3>"
			.$this->buildTable($this->friends, ['key'=>'Number of Friends', 'value'=>'Users'])
			."<p>Average number of friends from non-zero users: {$this->avgFriends}</p>"
		."</div>"
		."<div id='profile-creation'><h3>Profile Creation</h3>".$this->buildTable($this->profileContent, ['key'=>'Content in Profile','value'=>'Users'])."</div>"
		."<div id='favorite-wikis'><h3>Favorite Wikis</h3>".$this->buildTable($this->favoriteWikis, ['key'=>'Wiki','value'=>'Favorites'], [$this, 'wikiNameFromHash'], true)."</div>";

		return $HTML;
	}

	private function wikiNameFromHash($md5_key) {
		$wiki = \DynamicSettings\Wiki::loadFromHash($md5_key);
		return $wiki->getName().' ('.$wiki->getLanguage().')';
	}

	private function buildTable($data, $units, $labelCallback = false, $withRank = false) {
		$HTML = '';
		if (empty($data)) {
			return '';
		}

		$customLabel = is_callable($labelCallback);
		$rows = 0;
		$total = array_sum($data);
		if ($withRank) {
			asort($data);
			$data = array_reverse($data);
		}

		foreach ($data as $key => $value) {
			$percentage = number_format(100 * $value / $total, 1);
			$value = number_format($value);
			if ($customLabel) {
				$key = call_user_func($labelCallback, $key);
			}
			if ($withRank) {
				$rows += 1;
				$key = $rows."<td>".$key;
			}
			$HTML .= "<tr><td>{$key}<td class='numeric'>{$value}<td class='numeric'>{$percentage}%</tr>";
		}

		if ($withRank) {
			$units['key'] = 'Rank<th>'.$units['key'];
		}
		$HTML = "<table class='wikitable'><thead><th>{$units['key']}<th>{$units['value']}<th>Percentage</thead><tbody>$HTML</tbody></table>";
		return $HTML;
	}
}
