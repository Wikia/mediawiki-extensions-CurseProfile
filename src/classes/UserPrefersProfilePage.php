<?php

namespace CurseProfile\Classes;

use Article;
use Title;

class UserPrefersProfilePage extends Article {

	private $preferenceMsg;

	private $username;

	public function __construct(Title $title, string $preferenceMsg, string $username) {
		parent::__construct($title);
		$this->preferenceMsg = $preferenceMsg;
		$this->username = $username;
	}

	public function view() {
		$outputPage = $this->getContext()->getOutput();
		$outputPage->wrapWikiMsg(
			"<div class=\"curseprofile-userprefersprofile error\">\n$1\n</div>",
			[$this->preferenceMsg, wfEscapeWikiText($this->username)]
		);

		parent::view();
	}
}
