<?php
/**
 * CurseProfile
 * Profile Stats Templates
 *
 * @author    Alexia E. Smith
 * @copyright (c) 2017 Curse Inc.
 * @license   GNU General Public License v2.0 or later
 * @package   CurseProfile
 * @link      https://gitlab.com/hydrawiki
 **/

use DynamicSettings\Wiki;

class TemplateProfileStats {
	/**
	 * Profile Stats Statistics
	 *
	 * @access public
	 * @param  array $statistics    Statistics
	 * @param  array $favoriteWikis Favorite Wikis
	 * @return string	HTML
	 */
	public static function statisticsPage($statistics, $favoriteWikis) {
		$html = wfMessage('profilestats_last_run_time', (isset($statistics['last_run_time']) && $statistics['last_run_time'] > 0 ? wfTimestamp(TS_DB, intval($statistics['last_run_time'])) : wfMessage('last_run_never')))->escaped();

		$html .= "<h2>Actual Usage Stats</h2>";

		$html .= "
		<table class='wikitable'>
			<thead>
				<tr>
					<th>" . wfMessage('stat_stat')->escaped() . "</th>
					<th>" . wfMessage('stat_count')->escaped() . "</th>
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
					<td>" . wfMessage($field)->escaped() . "</td>
					<td>{$count}</td>
					<td>" . number_format(floor($count / $statistics['users-tallied'] * 10000) / 100, 2) . "%</td>
				</tr>";
		}
		$html .= "
			</tbody>
		</table>";

		$html .= "
		<table class='wikitable'>
			<thead>
				<tr>
					<th>" . wfMessage('wiki')->escaped() . "</th>
					<th>" . wfMessage('stat_count')->escaped() . "</th>
					<th>&nbsp;</th>
				</tr>
			</thead>
			<tbody>";
		foreach ($favoriteWikis as $siteKey => $count) {
			$html .= "
				<tr>
					<td>" . self::wikiNameFromHash($siteKey) . "</td>
					<td>{$count}</td>
					<td>" . number_format(floor($count / $statistics['profile-favwiki'] * 10000) / 100, 2) . "%</td>
				</tr>";
		}
		$html .= "
			</tbody>
		</table>";

		return $html;
	}

	/**
	 * Returns a printable wiki name for a wiki key
	 *
	 * @param string	md5 key for a wiki
	 * @param string	human-readable name and language of the wiki
	 */
	private static function wikiNameFromHash($siteKey) {
		$wiki = Wiki::loadFromHash($siteKey);
		return (!$wiki ? $siteKey : $wiki->getNameForDisplay());
	}
}
