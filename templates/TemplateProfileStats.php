<?php
/**
 * CurseProfile
 * Profile Stats Templates
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		CurseProfile
 * @link		http://www.curse.com/
 *
**/

class TemplateProfileStats {
	/**
	 * Profile Stats Reports
	 *
	 * @access	public
	 * @param	array	Reports
	 * @return	string	HTML
	 */
	static public function statisticsPage($statistics) {
		$html = wfMessage('profilestats_last_run_time', ($statistics['lastRunTime'] > 0 ? wfTimestamp(TS_DB, intval($statistics['lastRunTime'])) : wfMessage('last_run_never')))->escaped();

		$html .= "
			<div id='ps-adoption'>
				".TemplateHydralytics::chartJS('Overall Adoption', $statistics['users'], 'pie')."
				<div class='chart' id='chart-".md5('Overall Adoption')."'>
					<div class='loading'>Loading...</div>
				</div>
				<div id='adoption-chart'>".self::buildTable($statistics['users'], ['key' => 'Profile Type', 'value' => 'Users'])."</div>
			</div>";

		$html .= "<h2>Actual Usage Stats</h2>"
		."<div id='friends-system'><h3>Friends System</h3>"
			.self::buildTable($statistics['friends'], ['key' => 'Number of Friends', 'value' => 'Users'])
			."<p>".wfMessage('profilestats-avgFriends', $statistics['avgFriends'])->escaped()."</p>"
		."</div>";
		foreach ($statistics['profileContent'] as $field => $count) {
			if ($field === 'profile-favwiki') {
				continue;
			}
			$html .= "<div id='{$field}'>".self::buildTable($statistics['profileContent'][$field], ['key' => wfMessage($field)->escaped(), 'value' => 'Users'])."</div>";
		}
		$html .= "<div id='favorite-wikis'><h3>Favorite Wikis</h3>".self::buildTable($statistics['favoriteWikis'], ['key' => 'Wiki', 'value' => 'Favorites'], [self, 'wikiNameFromHash'], true)."</div>";

		return $html;
	}

	/**
	 * Builds a HTML table for display
	 *
	 * @param	array		data in { key: value } format
	 * @param	array		text to use for column headers e.g. [ 'key' => 'Key Header', 'label' => 'Label Header']
	 * @param	callable	[optional] callback to use for converting the data keys to strings for display. default uses wfMessage('profilestats-'.$key)
	 * @param	boolean		[optional] when true adds a left-most rank column counting up from 1
	 * @return	string		HTML table
	 */
	static private function buildTable($data, $units, $labelCallback = false, $withRank = false) {
		$html = '';
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
			$html .= "<tr><td>{$key}<td class='numeric'>{$value}<td class='numeric'>{$percentage}%</tr>";
		}

		if ($withRank) {
			$units['key'] = 'Rank<th>'.$units['key'];
		}
		$html = "<table class='wikitable'><thead><th>{$units['key']}<th>{$units['value']}<th>Percentage</thead><tbody>$html</tbody></table>";
		return $html;
	}

	/**
	 * Returns a printable wiki name for a wiki key
	 * @param	string	md5 key for a wiki
	 * @param	string	human-readable name and language of the wiki
	 */
	static private function wikiNameFromHash($siteKey) {
		$wiki = \DynamicSettings\Wiki::loadFromHash($siteKey);
		return (!$wiki ? $siteKey : $wiki->getNameForDisplay());
	}
}
