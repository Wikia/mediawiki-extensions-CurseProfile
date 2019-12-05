<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @package   CurseProfile
 * @author    Noah Manneschmidt
 * @copyright (c) 2014 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki
 **/

use CurseProfile\FriendDisplay;

class TemplateManageFriends {
	/**
	 * Output HTML
	 *
	 * @var string
	 */
	private $HTML;

	/**
	 * Displays a list of friends.
	 *
	 * @param  array   $friends      Raw friend IDs.
	 * @param  string  $pagination   Pagination HTML
	 * @param  integer $itemsPerPage Items Per Page
	 * @param  integer $start        Start Offset
	 * @return void
	 */
	public function display($friends, $pagination, $itemsPerPage, $start) {
		$this->HTML = '<h2>' . wfMessage('friends') . '</h2>';
		if (count($friends)) {
			$this->HTML .= $pagination;
			$this->HTML .= FriendDisplay::listFromArray($friends, false, $itemsPerPage, $start);
			$this->HTML .= $pagination;
		} else {
			$this->HTML .= wfMessage('nofriends')->plain();
		}
		return $this->HTML;
	}

	/**
	 * Displays a management page for friends
	 *
	 * @param  array   $friends      array of current friends Curse IDs
	 * @param  array   $received     array of received friend requests (curse IDs as keys)
	 * @param  array   $sent         array of curse ids to whom friend requests are pending
	 * @param  integer $itemsPerPage Items Per Page
	 * @param  integer $start        Start Offset
	 * @return string	Built HTML
	 */
	public function manage($friends, $received, $sent, $itemsPerPage, $start) {
		$this->HTML = '';
		$pagination = HydraCore::generatePaginationHtml(SpecialPage::getTitleFor('ManageFriends'), count($friends), $itemsPerPage, $start);

		if (count($received)) {
			$this->HTML .= '<h2>' . wfMessage('pendingrequests') . '</h2>';
			$this->HTML .= FriendDisplay::listFromArray($received, true);
		}

		$this->HTML .= '<h2>' . wfMessage('friends') . '</h2>';
		if (count($friends)) {
			$this->HTML .= $pagination;
			$this->HTML .= FriendDisplay::listFromArray($friends, true, $itemsPerPage, $start);
			$this->HTML .= $pagination;
		} else {
			$this->HTML .= wfMessage('soronery')->plain();
		}

		if (count($sent)) {
			$this->HTML .= '<h2>' . wfMessage('sentrequests') . '</h2>';
			$this->HTML .= FriendDisplay::listFromArray($sent, true);
		}

		$this->HTML .= '<h3>' . wfMessage('senddirectrequest') . '</h3>';
		$this->HTML .= Html::element('input', ['type' => 'text', 'id' => 'directfriendreq', 'placeholder' => wfMessage('directfriendreqplaceholder')->text()]);
		$this->HTML .= Html::element('button', ['id' => 'senddirectreq'], wfMessage('sendrequest')->text());

		return '<div id="managefriends">' . $this->HTML . '</div>';
	}
}
