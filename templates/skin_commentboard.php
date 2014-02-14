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
	 * @return	string	Built HTML
	 */
	public function header($user, $title) {
		return "<h1>$title</h1>".'<p><a href="'.(new CurseProfile\ProfileData($user->getId()))->getProfilePath().'">back to profile</a></p>';
	}

	/**
	 * Comments display
	 *
	 * @access	public
	 * @param	array	array of comments
	 * @param	string	[optional] built HTML fragment for pagination
	 * @return	string	Built HTML
	 */
	public function comments($comments, $user_id, $pagination = null) {
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
