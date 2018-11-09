<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @author		Noah Manneschmidt
 * @copyright	(c) 2014 Curse Inc.
 * @license		Proprietary
 * @package		CurseProfile
 * @link		http://www.curse.com/
 *
 **/

use CurseProfile\CommentDisplay;
use CurseProfile\ProfileData;

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
	 * @param	object	$user user reference
	 * @param	string	$title text title of the page
	 * @return	string	Built HTML
	 */
	public function header($user, $title) {
		return '<p>' .
			Html::element('a', ['href' => (new ProfileData($user->getId()))->getProfilePageUrl()], wfMessage('commentboard-link-backtoprofile')) .
		'</p>';
	}

	/**
	 * Header for single comment permalink page
	 *
	 * @param object $user
	 * @param string $title
	 * @return string
	 */
	public function permalinkHeader($user, $title) {
		return '<p>' .
			Html::element('a', ['href' => (new ProfileData($user->getId()))->getProfilePageUrl()], wfMessage('commentboard-link-backtoprofile')) .
			' | ' .
			Html::element('a', ['href' => SpecialPage::getTitleFor('CommentBoard', $user->getId())->getFullURL()], wfMessage('commentboard-link-backtoboard')) .
		'</p>';
	}

	/**
	 * Comments display
	 *
	 * @access	public
	 * @param	array	$comments array of comments
	 * @param	int	$user_id id of user to whom this comment list belongs
	 * @param	string	$pagination [optional] built HTML fragment for pagination
	 * @return	string	Built HTML
	 */
	public function comments($comments, $user_id, $pagination = '') {
		$this->HTML = '';
		$this->HTML .= '<div>' . $pagination . '</div>';

		$this->HTML .= '<div class="comments curseprofile" data-user_id="' . $user_id . '">';

		// add hidden compose form, to support replies
		$this->HTML .= CommentDisplay::newCommentForm($user_id, true);

		foreach ($comments as $comment) {
			$this->HTML .= CommentDisplay::singleComment($comment, false);
		}

		$this->HTML .= '</div>';

		$this->HTML .= '<div>' . $pagination . '</div>';
		return $this->HTML;
	}
}
