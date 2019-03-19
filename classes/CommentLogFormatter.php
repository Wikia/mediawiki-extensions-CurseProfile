<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @author    Tim Aldridge
 * @copyright (c) 2015 Curse Inc.
 * @license   GPL-2.0-or-later
 * @package   CurseProfile
 * @link      https://gitlab.com/hydrawiki
 **/

namespace CurseProfile;

use Html;
use LogFormatter;
use SpecialPage;

/**
 * A class that will handle log formating for Recent Changes
 */
class CommentLogFormatter extends LogFormatter {
	/**
	 * Handle custom log parameters for comments.
	 *
	 * @access public
	 * @return array	Extract and parsed parameters.
	 */
	protected function getMessageParameters() {
		$parameters = parent::getMessageParameters();

		// 4:comment_id
		if (!empty($parameters[3])) {
			$parameters[3] = ['raw' => Html::rawElement('a', ['href' => SpecialPage::getTitleFor('CommentPermalink', $parameters[3])->getLinkURL()], 'comment')];
		}

		return $parameters;
	}
}
