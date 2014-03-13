<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @author		Noah Manneschmidt
 * @copyright	(c) 2014 Curse Inc.
 * @license		All Rights Reserved
 * @package		CurseProfile
 * @link		http://www.curse.com/
 *
**/

class skin_managefriends {
	/**
	 * Output HTML
	 *
	 * @var		string
	 */
	private $HTML;

	public function display($friends) {
		$this->HTML = '<h2>'.wfMessage('friends').'</h2>';
		if (count($friends)) {
			$this->HTML .= CurseProfile\FriendDisplay::listFromArray($friends, [$this, 'removeFriend']);
		} else {
			$this->HTML .= wfMessage('nofriends')->plain();
		}
		return $this->HTML;
	}

	/**
	 * Displays a management page for friends
	 *
	 * @access	public
	 * @param	array	array of current friends Curse IDs
	 * @param	array	array of received friend requests (curse IDs as keys)
	 * @param	array	array of curse ids to whom friend requests are pending
	 * @return	string	Built HTML
	 */
	public function manage($friends, $received, $sent) {
		$this->HTML = '';

		if (count($received)) {
			$this->HTML .= '<h2>'.wfMessage('pendingrequests').'</h2>';
			$this->HTML .= CurseProfile\FriendDisplay::listFromArray(array_keys($received), [$this, 'respondToRequest']);
		}

		$this->HTML .= '<h2>'.wfMessage('friends').'</h2>';
		if (count($friends)) {
			$this->HTML .= CurseProfile\FriendDisplay::listFromArray($friends, [$this, 'removeFriend']);
		} else {
			$this->HTML .= wfMessage('soronery')->plain();
		}

		if (count($sent)) {
			$this->HTML .= '<h2>'.wfMessage('sentrequests').'</h2>';
			$this->HTML .= CurseProfile\FriendDisplay::listFromArray($sent, [$this, 'cancelRequest']);
		}

		return $this->HTML;
	}

	public function respondToRequest($curse_id, $user) {
		return
			$this->friendLink($user->getId(), 'Special:ConfirmFriend', 'confirmfriend-response')
			. ' ' .
			$this->friendLink($user->getId(), 'Special:IgnoreFriend', 'ignorefriend-response')
		;
	}

	public function removeFriend($curse_id, $user) {
		return $this->friendLink($user->getId(), 'Special:RemoveFriend', 'removefriend-response');
	}

	public function cancelRequest($curse_id, $user) {
		return $this->friendLink($user->getId(), 'Special:RemoveFriend', 'friendrequestcancel');
	}

	public function friendLink($id, $page, $msg) {
		return "<a href='/$page/$id'>".wfMessage($msg).'</a>';
	}
}
