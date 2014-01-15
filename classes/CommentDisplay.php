<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @author		Noah Manneschmidt
 * @copyright	(c) 2013 Curse Inc.
 * @license		All Rights Reserved
 * @package		CurseProfile
 * @link		http://www.curse.com/
 *
**/
namespace CurseProfile;

/**
 * A class to manage displaying a list of friends on a user profile
 */
class CommentDisplay {
	public static function comments(&$parser, $user_id = '') {
		$user_id = intval($user_id);
		if ($user_id < 1) {
			return 'Invalid user ID given';
		}
		$HTML = '';

		global $wgUser;
		if ($wgUser->isLoggedIn()) {
			$HTML .= '
			<div class="commentdisplay">
				<div class="avatar">'.ProfilePage::userAvatar($nothing, 48, $wgUser->getEmail(), $wgUser->getName())[0].'</div>
				<div class="entryform"><form action="/Special:AddComment/'.$user_id.'" method="post">
					<textarea name="message" placeholder="'.wfMessage('commentplaceholder')->escaped().'"></textarea>
					<input type="submit" value="'.wfMessage('commentaction')->escaped().'">
				</form></div>
			</div>';
		}

		$mouse = CP::loadMouse();
		$board = new CommentBoard($user_id);
		$comments = $board->getComments();

		foreach ($comments as $comment) {
			$cUser = \User::newFromId($comment['ub_user_id_from']);
			$HTML .= '
			<div class="commentdisplay">
				<div class="avatar">'.ProfilePage::userAvatar($nothing, 48, $cUser->getEmail(), $cUser->getName())[0].'</div>
				<div>'.CP::timeTag($comment['ub_date']).CP::userLink($comment['ub_user_id_from']).'</div>
				<div class="commentbody">
					'.$parser->recursiveTagParse($comment['ub_message']).'
				</div>
			</div>';
		}

		return [
			$HTML,
			'isHTML' => true,
		];
	}
}
