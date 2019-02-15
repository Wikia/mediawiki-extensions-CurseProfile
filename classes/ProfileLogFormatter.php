<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2016 Curse Inc.
 * @license		GNU General Public License v2.0 or later
 * @package		CurseProfile
 * @link		https://gitlab.com/hydrawiki
 *
 **/
namespace CurseProfile;

use LogFormatter;

/**
 * A class that will handle log formating for Recent Changes
 */
class ProfileLogFormatter extends LogFormatter {
	/**
	 * Handle custom log parameters for profile edits.
	 *
	 * @access	public
	 * @return	array	Extract and parsed parameters.
	 */
	protected function getMessageParameters() {
		$parameters = parent::getMessageParameters();

		// 4:section
		if (!empty($parameters[3])) {
			$parameters[3] = ['raw' => wfMessage('log-' . $parameters[3])];
		}

		return $parameters;
	}
}
