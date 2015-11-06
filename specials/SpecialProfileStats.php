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

class SpecialProfileStats extends \Curse\SpecialPage {
	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		parent::__construct('ProfileStats', 'profilestats');
	}

	/**
	 * Main Executor
	 *
	 * @access	public
	 * @return	void	[Outputs to screen]
	 */
	public function execute( $path ) {
		$this->setHeaders();
		$this->checkPermissions();

		$this->redis = \RedisCache::getClient('cache');

		$this->templateHydralytics = new \TemplateHydralytics();

		// Data built by StatsRecache job, refer to its contents for data format
		$this->profileStats = [];
		$profileStats = $this->redis->hGetAll('profilestats');
		if (is_array($profileStats) && count($profileStats)) {
			foreach ($profileStats as $key => $value) {
				$this->profileStats[$key] = unserialize($value);
			}
		}

		$this->output->addModules('ext.curseprofile.profilestats');
		$this->output->addHTML($this->buildOutput());
	}

	/**
	 * Builds the HTML output of the special page.
	 *
	 * @access	private
	 * @return	string	html content
	 */
	private function buildOutput() {
		$lastRunTime = intval($this->profileStats['lastRunTime']);
		$HTML = wfMessage('profilestats_last_run_time', ($lastRunTime > 0 ? wfTimestamp(TS_DB, intval($this->profileStats['lastRunTime'])) : wfMessage('last_run_never')))->escaped();

		$HTML .= "
			<div id='ps-adoption'>
				".$this->templateHydralytics->chartJS('Overall Adoption', $this->profileStats['users'], 'pie')."
				<div class='chart' id='chart-".md5('Overall Adoption')."'>
					<div class='loading'>Loading...</div>
				</div>
				<div id='adoption-chart'>".$this->buildTable($this->profileStats['users'], ['key' => 'Profile Type', 'value' => 'Users'])."</div>
			</div>";

		$HTML .= "<h2>Actual Usage Stats</h2>"
		."<div id='friends-system'><h3>Friends System</h3>"
			.$this->buildTable($this->profileStats['friends'], ['key' => 'Number of Friends', 'value' => 'Users'])
			."<p>".wfMessage('profilestats-avgFriends', $this->profileStats['avgFriends'])->escaped()."</p>"
		."</div>"
		."<div id='profile-creation'><h3>Profile Creation</h3>".$this->buildTable($this->profileContent, ['key'=>'Content in Profile','value'=>'Users'])."</div>"
		."<div id='favorite-wikis'><h3>Favorite Wikis</h3>".$this->buildTable($this->favoriteWikis, ['key'=>'Wiki','value'=>'Favorites'], [$this, 'wikiNameFromHash'], true)."</div>";

		return $HTML;
	}

	/**
	 * Returns a printable wiki name for a wiki key
	 * @param	string	md5 key for a wiki
	 * @param	string	human-readable name and language of the wiki
	 */
	private function wikiNameFromHash($md5_key) {
		$wiki = \DynamicSettings\Wiki::loadFromHash($md5_key);
		return $wiki->getName().' ('.$wiki->getLanguage().')';
	}

	/**
	 * Builds a HTML table for display
	 *
	 * @param	array		data in { key: value } format
	 * @param	array		text to use for column headers e.g. [ 'key'=>'Key Header', 'label'=>'Label Header']
	 * @param	callable	[optional] callback to use for converting the data keys to strings for display. default uses wfMessage('profilestats-'.$key)
	 * @param	boolean		[optional] when true adds a left-most rank column counting up from 1
	 * @return	string		HTML table
	 */
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
			} else {
				$key = wfMessage('profilestats-'.$key)->escaped();
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
