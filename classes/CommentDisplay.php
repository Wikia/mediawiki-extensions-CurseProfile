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
	/**
	 * Responds to the comments parser hook that displays recent comments on a profile
	 *
	 * @param	object	parser instance
	 * @param	int		id of the user whose recent comments should be displayed
	 * @return	array	with html at index 0
	 */
	public static function comments(&$parser, $user_id = '') {
		$user_id = intval($user_id);
		if ($user_id < 1) {
			return 'Invalid user ID given';
		}
		$HTML = '';

		$HTML .= self::newCommentForm($user_id);

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

	/**
	 * Returns the HTML text for a comment entry form if the current user is logged in and not blocked
	 *
	 * @param	int		id of the user whose comment board will recieve a new comment via this form
	 * @param	bool	if true, the form will have an added class to be hidden by css
	 * @return	string	html fragment or empty string
	 */
	public static function newCommentForm($user_id, $hidden = false) {
		global $wgUser;
		if ($wgUser->isLoggedIn() && !$wgUser->isBlocked()) {
			$commentPlaceholder = wfMessage('commentplaceholder')->escaped();
			$replyPlaceholder = wfMessage('commentreplyplaceholder')->escaped();
			return '
			<div class="commentdisplay add-comment'.($hidden ? ' hidden' : '').'">
				<div class="avatar">'.ProfilePage::userAvatar($nothing, 48, $wgUser->getEmail(), $wgUser->getName())[0].'</div>
				<div class="entryform">
					<form action="/Special:AddComment/'.$user_id.'" method="post">
						<textarea name="message" maxlength="'.CommentBoard::MAX_LENGTH.'" data-replyplaceholder="'.$replyPlaceholder.'" placeholder="'.$commentPlaceholder.'"></textarea>
						<button name="inreplyto" value="0">'.wfMessage('commentaction')->escaped().'</button>
						'.\Html::hidden('token', $wgUser->getEditToken()).'
					</form>
				</div>
			</div>';
		} else {
			return '';
		}
	}

	/**
	 * Returns html display for a single profile comment
	 *
	 * @param	array	structured comment data as returned by CommentBoard
	 * @param	int		[optional] id of a comment to highlight from among those displayed
	 * @return	string	html for display
	 */
	public static function singleComment($comment, $highlight = false) {
		global $wgOut, $wgUser;

		$HTML = '';
		$cUser = \User::newFromId($comment['ub_user_id_from']);

		$type = '';
		switch ($comment['ub_type']) {
			case CommentBoard::PRIVATE_MESSAGE:
			$type = 'private';
			break;

			case CommentBoard::DELETED_MESSAGE:
			$type = 'deleted';
			break;

			case CommentBoard::PUBLIC_MESSAGE:
			$type = 'public';
			break;
		}

		if ($highlight == $comment['ub_id']) {
			$type .= ' highlighted';
		}

		$HTML .= '
		<div class="commentdisplay '.$type.'" data-id="'.$comment['ub_id'].'">
			<div class="avatar">'.ProfilePage::userAvatar($nothing, 48, $cUser->getEmail(), $cUser->getName())[0].'</div>
			<div>
				<div class="right">
					'.\Html::rawElement('a', ['href'=>\SpecialPage::getTitleFor('CommentPermalink', $comment['ub_id'])->getFullURL()], self::timestamp($comment)).' '
					.(CommentBoard::canReply($comment) ? \Html::element('a', ['href'=>'#', 'class'=>'newreply', 'title'=>wfMessage('replylink-tooltip')], wfMessage('replylink')).' ' : '')
					.(CommentBoard::canEdit($comment) ? \Html::element('a', ['href'=>'#', 'class'=>'edit', 'title'=>wfMessage('commenteditlink-tooltip')], wfMessage('commenteditlink')).' ' : '')
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
					$HTML .= self::singleComment($reply, $highlight);
				}
				$HTML .= '</div>';
			}
		$HTML .= '
		</div>';
		return $HTML;
	}

	/**
	 * Returns a <time> tag with a comment's post date or last edited date
	 *
	 * @param	array	comment data
	 * @return	stirng	html fragment
	 */
	private static function timestamp($comment){
		if (is_null($comment['ub_edited'])) {
			return CP::timeTag($comment['ub_date']);
		} else {
			return wfMessage('cp-commentedited').' '.CP::timeTag($comment['ub_edited']);
		}
	}

	/**
	 * Unlike the previous comments function, this will create a new CommentBoard instance to fetch the data for you
	 *
	 * @param	int		the id of the user the parent comment belongs to
	 * @param	int		the id of the comment for which replies need to be loaded
	 * @return	string	html for display
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
			$HTML = wfMessage('cp-nocommentreplies');
		} else {
			foreach ($comments as $comment) {
				$HTML .= self::singleComment($comment);
			}
		}

		return $HTML;
	}
}
