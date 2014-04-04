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
			$commentPlaceholder = wfMessage('commentplaceholder')->escaped();
			$replyPlaceholder = wfMessage('commentreplyplaceholder')->escaped();
			$HTML .= '
			<div class="commentdisplay add-comment">
				<div class="avatar">'.ProfilePage::userAvatar($nothing, 48, $wgUser->getEmail(), $wgUser->getName())[0].'</div>
				<div class="entryform">
					<form action="/Special:AddComment/'.$user_id.'" method="post">
						<textarea name="message" maxlength="'.CommentBoard::MAX_LENGTH.'" data-replyplaceholder="'.$replyPlaceholder.'" placeholder="'.$commentPlaceholder.'"></textarea>
						<button name="inreplyto" value="0">'.wfMessage('commentaction')->escaped().'</button>
						'.\Html::hidden('token', $wgUser->getEditToken()).'
					</form>
				</div>
			</div>';
		}

		$board = new CommentBoard($user_id);
		$comments = $board->getComments();

		foreach ($comments as $comment) {
			$HTML .= self::singleComment($comment);
		}

		return [
			$HTML,
			'isHTML' => true,
		];
	}

	public static function singleComment($comment) {
		global $wgOut;

		$HTML = '';
		$cUser = \User::newFromId($comment['ub_user_id_from']);
		$HTML .= '
		<div class="commentdisplay" data-id="'.$comment['ub_id'].'">
			<div class="avatar">'.ProfilePage::userAvatar($nothing, 48, $cUser->getEmail(), $cUser->getName())[0].'</div>
			<div>
				<div class="right">
					'.CP::timeTag($comment['ub_date']).' '
					.\Html::element('a', ['href'=>'#', 'class'=>'newreply'], wfMessage('replylink')).' '
					.(CommentBoard::canRemove($comment) ? \Html::element('a', ['href'=>'#', 'class'=>'remove', 'title'=>wfMessage('removelink-tooltip')], wfMessage('removelink')) : '')
				.'</div>
				'.CP::userLink($comment['ub_user_id_from'])
				.'</div>
			<div class="commentbody">
				'.$wgOut->parseInline($comment['ub_message']).'
			</div>';
			if (isset($comment['replies'])) {
				$HTML .= '<div class="replyset">';

				// perhaps there are more replies not yet loaded
				if ($comment['reply_count'] > count($comment['replies'])) {
					if (!isset($repliesTooltip)) {
						$repliesTooltip = htmlspecialchars(wfMessage('repliestooltip')->plain(), ENT_QUOTES);
					}
					// force parsing this message because MW won't replace plurals as expected
					// due to this all happening inside the wfMessage()->parse() call that
					// generates the entire profile
					$viewReplies = $wgOut->parseInline(wfMessage('viewearlierreplies', $comment['reply_count'] - count($comment['replies']))->escaped());
					$HTML .= "
					<button type='button' class='reply-count' data-id='{$comment['ub_id']}' title='$repliesTooltip'>$viewReplies</button>";
				}

				foreach ($comment['replies'] as $reply) {
					$HTML .= self::singleComment($reply);
				}
				$HTML .= '</div>';
			}
		$HTML .= '
		</div>';
		return $HTML;
	}

	/**
	 * Unlike the previous comments function
	 */
	public static function repliesTo($user_id, $comment_id) {
		$user_id = intval($user_id);
		if ($user_id < 1) {
			return 'Invalid user ID given';
		}
		$HTML = '';

		$board = new CommentBoard($user_id);
		$comments = $board->getReplies($comment_id, null, -1);

		if (empty($comments)) {
			$HTML = 'No replies were found';
		} else {
			foreach ($comments as $comment) {
				$HTML .= self::singleComment($comment);
			}
		}

		return $HTML;
	}
}
