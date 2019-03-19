<?php
/**
 * Curse Inc.
 * Curse Profile
 * Dummy Page for Invalid Users.
 *
 * @author    Samuel Hilson
 * @copyright (c) 2018 Curse Inc.
 * @license   GPL-2.0-or-later
 * @package   CurseProfile
 * @link      https://gitlab.com/hydrawiki
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
		$output->setPageTitle($this->getTitle()->getPrefixedText());
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
