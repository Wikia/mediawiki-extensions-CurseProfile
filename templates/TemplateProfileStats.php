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
	 * Profile Stats Statistics
	 *
	 * @access	public
	 * @param	array	Statistics
	 * @param	array	Favorite Wikis
	 * @return	string	HTML
	 */
	static public function statisticsPage($statistics, $favoriteWikis) {
		$html = wfMessage('profilestats_last_run_time', (isset($statistics['last_run_time']) && $statistics['last_run_time'] > 0 ? wfTimestamp(TS_DB, intval($statistics['last_run_time'])) : wfMessage('last_run_never')))->escaped();

		$html .= "<h2>Actual Usage Stats</h2>";

		$html .= "
		<table class='wikitable'>
			<thead>
				<tr>
					<th>".wfMessage('stat_stat')->escaped()."</th>
					<th>".wfMessage('stat_count')->escaped()."</th>
					<th>&nbsp;</th>
				</tr>
			</thead>
			<tbody>";
		foreach ($statistics as $field => $count) {
			if ($field == 'last_run_time') {
				continue;
			}
			$html .= "
				<tr>
					<td>".wfMessage($field)->escaped()."</td>
					<td>{$count}</td>
					<td>".sprintf('%1.2f', ($count / $statistics['users-tallied'] * 100))."%</td>
				</tr>";
		}
		$html .= "
			</tbody>
		</table>";

		$html .= "
		<table class='wikitable'>
			<thead>
				<tr>
					<th>".wfMessage('wiki')->escaped()."</th>
					<th>".wfMessage('stat_count')->escaped()."</th>
					<th>&nbsp;</th>
				</tr>
			</thead>
			<tbody>";
		foreach ($favoriteWikis as $siteKey => $count) {
			$html .= "
				<tr>
					<td>".self::wikiNameFromHash($siteKey)."</td>
					<td>{$count}</td>
					<td>".sprintf('%1.2f', ($count / $statistics['profile-favwiki'] * 100))."%</td>
				</tr>";
		}
		$html .= "
			</tbody>
		</table>";

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
