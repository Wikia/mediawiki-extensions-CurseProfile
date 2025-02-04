<?php

namespace CurseProfile\Classes;

use Article;
use MediaWiki\Title\Title;
use PermissionsError;

class UserPrefersProfilePage extends Article {
	public function __construct( Title $title, private string $preferenceMsg, private string $username ) {
		parent::__construct( $title );
	}

	/**
	 * @throws PermissionsError
	 */
	public function view(): void {
		$outputPage = $this->getContext()->getOutput();
		$outputPage->wrapWikiMsg(
			"<div class=\"curseprofile-userprefersprofile error\">\n$1\n</div>",
			[ $this->preferenceMsg, wfEscapeWikiText( $this->username ) ]
		);

		parent::view();
	}
}
