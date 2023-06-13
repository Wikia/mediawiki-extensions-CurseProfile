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
 */

namespace CurseProfile\Classes;

use Article;

class NoProfilePage extends Article {
	/**
	 * Primary rendering function for mediawiki's Article
	 * @inheritDoc
	 */
	public function view() {
		$output = $this->getContext()->getOutput();
		$output->setRobotPolicy( 'noindex,nofollow' );
		$output->setPageTitle( $this->getTitle()->getPrefixedText() );
		$output->addHTML( $this->getOutputHtml() );
	}

	private function getOutputHtml(): string {
		$username = $this->getTitle()->getText();
		$text = wfMessage( 'cp-user-does-not-exist', wfEscapeWikiText( $username ) )->text();
		$text .= ' ' . wfMessage( 'cp-enhanced-profile-unavailable' )->text();
		$html = '<div class="curseprofile">';
		$html .= '<h1>User:' . $username . '</h1>';
		$html .= '<div class="mw-userpage-userdoesnotexist error">' . $text . '</div>';
		$html .= '</div>';

		return $html;
	}
}
