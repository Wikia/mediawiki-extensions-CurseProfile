<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @author		Tim Aldridge
 * @copyright	(c) 2015 Curse Inc.
 * @license		All Rights Reserved
 * @package		CurseProfile
 * @link		http://www.curse.com/
 *
 **/
namespace CurseProfile;

/**
 * A class that will handle log formating for Recent Changes
 */
class CommentLogFormatter extends \LogFormatter {
	/**
	 * Handle custom log parameters for SpriteSheet class.
	 *
	 * @access	public
	 * @return	array	Extract and parsed parameters.
	 */
	protected function getMessageParameters() {
		$parameters = parent::getMessageParameters();

		return $parameters;
	}
}