<?php

/**
 * Curse Inc.
 * Curse Profile
 * Dummy Page for Invalid Users.
 *
 * @author		Samuel Hilson
 * @copyright	(c) 2018 Curse Inc.
 * @license		Proprietary
 * @package		CurseProfile
 * @link		http://www.curse.com/
 *
 **/

namespace CurseProfile;

use Article;

class NoProfilePage extends Article {
	/**
	 * Primary rendering function for mediawiki's Article
	 *
	 * @return void
	 */
	public function view() {
		$output = $this->getContext()->getOutput();
		$outputString = $this->getOutput();
		$output->addHTML($outputString);
	}

	/**
	 * Get the HTML output
	 *
	 * @return string
	 */
	private function getOutput() {
		$username = $this->getTitle()->getText();
		$text = wfMessage('cp-user-does-not-exist', wfEscapeWikiText($username))->text();
		$text .= ' ' . wfMessage('cp-enhanced-profile-unavailable')->text();
		$html = '<div class="curseprofile">';
		$html .= '<h1>User:' . $username . '</h1>';
		$html .= '<div class="mw-userpage-userdoesnotexist error">' . $text . '</div>';
		$html .= '</div>';

		return $html;
	}
}
