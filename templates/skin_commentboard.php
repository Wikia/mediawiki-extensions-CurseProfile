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

class skin_commentboard {
	/**
	 * Output HTML
	 *
	 * @var		string
	 */
	private $HTML;

	/**
	 * Comments display
	 *
	 * @access	public
	 * @param	object	user reference
	 * @param	string	text title of the pageq
	 * @return	string	Built HTML
	 */
	public function header($user, $title) {
		return "<h1>$title</h1>".
		'<p>'.
			Html::element('a', ['href'=>(new CurseProfile\ProfileData($user->getId()))->getProfilePath()], wfMessage('commentboard-link-backtoprofile')).
		'</p>';
	}

	public function permalinkHeader($user, $title) {
		return "<h1>$title</h1>".
		'<p>'.
			Html::element('a', ['href'=>(new CurseProfile\ProfileData($user->getId()))->getProfilePath()], wfMessage('commentboard-link-backtoprofile')).
			' | '.
			Html::element('a', ['href'=>SpecialPage::getTitleFor('CommentBoard', $user->getId())->getFullURL()], wfMessage('commentboard-link-backtoboard')).
		'</p>';
	}

	/**
	 * Comments display
	 *
	 * @access	public
	 * @param	array	array of comments
	 * @param	string	[optional] built HTML fragment for pagination
	 * @return	string	Built HTML
	 */
	public function comments($comments, $user_id, $pagination = '') {
		$this->HTML = '';
		$this->HTML .= '<div>'.$pagination.'</div>';

		$this->HTML .= '<div class="comments curseprofile noreplies" data-userid="'.$user_id.'">';
		foreach ($comments as $comment) {
			$this->HTML .= CurseProfile\CommentDisplay::singleComment($comment);
		}
		$this->HTML .= '</div>';

		$this->HTML .= '<div>'.$pagination.'</div>';
		return $this->HTML;
	}
}
