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

class TemplateCommentBoard {
	/**
	 * Output HTML
	 *
	 * @var		string
	 */
	private $HTML;

	/**
	 * Header for comments archive board
	 *
	 * @access	public
	 * @param	object	user reference
	 * @param	string	text title of the pageq
	 * @return	string	Built HTML
	 */
	public function header($user, $title) {
		return
		'<p>'.
			Html::element('a', ['href'=>(new CurseProfile\ProfileData($user->getId()))->getProfilePath()], wfMessage('commentboard-link-backtoprofile')).
		'</p>';
	}

	/**
	 * Header for single comment permalink page
	 *
	 * @access	public
	 */
	public function permalinkHeader($user, $title) {
		return
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
	 * @param	int		id of user to whom this comment list belongs
	 * @param	string	[optional] built HTML fragment for pagination
	 * @return	string	Built HTML
	 */
	public function comments($comments, $user_id, $pagination = '', $mobile = false) {
		$this->HTML = '';
		$this->HTML .= '<div>'.$pagination.'</div>';

		$this->HTML .= '<div class="comments curseprofile" data-userid="'.$user_id.'">';

		// add hidden compose form, to support replies
		$this->HTML .= CurseProfile\CommentDisplay::newCommentForm($user_id, true, $mobile);

		foreach ($comments as $comment) {
			$this->HTML .= CurseProfile\CommentDisplay::singleComment($comment, false, $mobile);
		}

		$this->HTML .= '</div>';

		$this->HTML .= '<div>'.$pagination.'</div>';
		return $this->HTML;
	}
}
