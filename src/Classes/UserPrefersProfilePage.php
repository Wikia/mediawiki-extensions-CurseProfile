<?php

namespace CurseProfile\Classes;

use Article;
use Title;

class UserPrefersProfilePage extends Article {
	public function __construct( Title $title, private string $preferenceMsg, private string $username ) {
		parent::__construct( $title );
	}

	public function view() {
		$outputPage = $this->getContext()->getOutput();
		$outputPage->wrapWikiMsg(
			"<div class=\"curseprofile-userprefersprofile error\">\n$1\n</div>",
			[ $this->preferenceMsg, wfEscapeWikiText( $this->username ) ]
		);

		parent::view();
	}
}
