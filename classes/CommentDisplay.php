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
	 * @param	integer	id of the user whose recent comments should be displayed
	 * @return	array	with html at index 0
	 */
	static public function comments(&$parser, $userId = '') {
		$userId = intval($userId);
		if ($userId < 1) {
			return 'Invalid user ID given';
		}

		$HTML = '';

		$HTML .= self::newCommentForm($userId, false);

		$board = new CommentBoard($userId);
		$comments = $board->getComments();

		foreach ($comments as $comment) {
			$HTML .= self::singleComment($comment, false);
		}

		return [
			$HTML,
			'isHTML' => true,
		];
	}

	/**
	 * Returns the HTML text for a comment entry form if the current user is logged in and not blocked
	 *
	 * @param	integer	ID of the user whose comment board will recieve a new comment via this form
	 * @param	boolean	If true, the form will have an added class to be hidden by css/
	 * @return	string	html fragment or empty string
	 */
	static public function newCommentForm($userId, $hidden = false) {
		global $wgUser;

		$targetUser = \User::newFromId($userId);
		if (CommentBoard::canComment($targetUser)) {
			$commentPlaceholder = wfMessage('commentplaceholder')->escaped();
			$replyPlaceholder = wfMessage('commentreplyplaceholder')->escaped();
			$page = \Title::newFromText("Special:AddComment/".$userId);
			return '
			<div class="commentdisplay add-comment'.($hidden ? ' hidden' : '').'">
				<div class="avatar">'.ProfilePage::userAvatar($nothing, 48, $wgUser->getEmail(), $wgUser->getName())[0].'</div>
				<div class="entryform">
					<form action="'.$page->getFullUrl().'" method="post">
						<textarea name="message" maxlength="'.CommentBoard::MAX_LENGTH.'" data-replyplaceholder="'.$replyPlaceholder.'" placeholder="'.$commentPlaceholder.'"></textarea>
						<button name="inreplyto" class="submit" value="0">'.wfMessage('commentaction')->escaped().'</button>
						'.\Html::hidden('token', $wgUser->getEditToken()).'
					</form>
				</div>
			</div>';
		} else {
			return "<div class='errorbox'>".wfMessage('no-perm-profile-addcomment', \Linker::linkKnown(\Title::newFromText('Special:ConfirmEmail'), wfMessage('no-perm-validate-email')->text()))->text()."</div>";
		}
	}

	/**
	 * Returns html display for a single profile comment
	 *
	 * @param	array	structured comment data as returned by CommentBoard
	 * @param	integer	[optional] id of a comment to highlight from among those displayed
	 * @return	string	html for display
	 */
	static public function singleComment($comment, $highlight = false) {
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

		if (\HydraCore::isMobileSkin(\RequestContext::getMain()->getSkin())) {
			$avatarSize = 36;
		} else {
			$avatarSize = 48;
		}

		$HTML .= '
		<div class="commentdisplay '.$type.'" data-id="'.$comment['ub_id'].'">
			<a name="comment'.$comment['ub_id'].'"></a>
			<div class="commentblock">
				<div class="avatar">'.ProfilePage::userAvatar($nothing, $avatarSize, $cUser->getEmail(), $cUser->getName())[0].'</div>
				<div class="commentheader">';
		$HTML .= '
					<div class="right">'
				.($comment['ub_admin_acted'] ? self::adminAction($comment).', ' : '')
				.\Html::rawElement('a', ['href'=>\SpecialPage::getTitleFor('CommentPermalink', $comment['ub_id'])->getLinkURL()], self::timestamp($comment)).' '
				.(CommentBoard::canReply($comment) ? \Html::rawElement('a', ['href' => '#', 'class' => 'icon newreply', 'title' => wfMessage('replylink-tooltip')], \HydraCore::awesomeIcon('reply')) . ' ' : '')
				.(CommentBoard::canEdit($comment) ? \Html::rawElement('a', ['href' => '#', 'class' => 'icon edit', 'title' => wfMessage('commenteditlink-tooltip')], \HydraCore::awesomeIcon('pencil-alt')) . ' ' : '')
				.(CommentBoard::canRemove($comment) ? \Html::rawElement('a', ['href' => '#', 'class' => 'icon remove', 'title' => wfMessage('removelink-tooltip')], \HydraCore::awesomeIcon('trash-alt')) : '')
				.(CommentBoard::canRestore($comment) ? \Html::rawElement('a', ['href' => '#', 'class' => 'icon restore', 'title' => wfMessage('restorelink-tooltip')], \HydraCore::awesomeIcon('undo')) : '')
				.(CommentBoard::canPurge() ? \Html::rawElement('a', ['href' => '#', 'class' => 'icon purge', 'title' => wfMessage('purgelink-tooltip')], \HydraCore::awesomeIcon('eraser')) : '')
				.(CommentBoard::canReport($comment) ? \Html::rawElement('a', ['href' => '#', 'class' => 'icon report', 'title' => wfMessage('reportlink-tooltip')], \HydraCore::awesomeIcon('flag')) : '')
				.'
					</div>'
				.CP::userLink($comment['ub_user_id_from'], "commentUser");
		$HTML .= '
				</div>
				<div class="commentbody">
					'.self::sanitizeComment($comment['ub_message']).'
				</div>
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
					$viewReplies = \Parser::stripOuterParagraph($wgOut->parse(wfMessage('viewearlierreplies', $comment['reply_count'] - count($comment['replies']))->escaped()));
					$HTML .= "<button type='button' class='reply-count' data-id='{$comment['ub_id']}' title='{$repliesTooltip}'>{$viewReplies}</button>";
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
	 * Returns extra info visible only to admins on who and when admin action was taken on a comment
	 * @param	array	comment data
	 * @return	string	html fragment
	 */
	private static function adminAction($comment) {
		$lookup = \CentralIdLookup::factory();
		$admin = $lookup->localUserFromCentralId($comment['ub_admin_acted']);
		if (!$admin->getName()) {
			return '';
		}

		return wfMessage('cp-commentmoderated', $admin->getName())->text().' '.CP::timeTag($comment['ub_admin_acted_at']);
	}

	/**
	 * Returns a <time> tag with a comment's post date or last edited date
	 *
	 * @param	array	comment data
	 * @return	string	html fragment
	 */
	private static function timestamp($comment){
		if (is_null($comment['ub_edited'])) {
			return wfMessage('cp-commentposted')->text().' '.CP::timeTag($comment['ub_date']);
		} else {
			return wfMessage('cp-commentedited')->text().' '.CP::timeTag($comment['ub_edited']);
		}
	}

	/**
	 * Returns a <time> tag with a comment's post date or last edited date for mobile.
	 *
	 * @param	array	comment data
	 * @return	string	html fragment
	 */
	private static function mobileTimestamp($comment) {
		if (is_null($comment['ub_edited'])) {
			return CP::timeTag($comment['ub_date'], true);
		} else {
			return CP::timeTag($comment['ub_edited'], true);
		}
	}

	/**
	 * Unlike the previous comments function, this will create a new CommentBoard instance to fetch the data for you
	 *
	 * @param	integer	the id of the user the parent comment belongs to
	 * @param	integer	the id of the comment for which replies need to be loaded
	 * @return	string	html for display
	 */
	static public function repliesTo($userId, $commentId) {
		$userId = intval($userId);
		if ($userId < 1) {
			return 'Invalid user ID given';
		}
		$HTML = '';

		$board = new CommentBoard($userId);
		$comments = $board->getReplies($commentId, null, -1);

		if (empty($comments)) {
			$HTML = wfMessage('cp-nocommentreplies');
		} else {
			foreach ($comments as $comment) {
				$HTML .= self::singleComment($comment);
			}
		}

		return $HTML;
	}

	/**
	 * Sanitizes a comment for display in HTML.
	 *
	 * @access	public
	 * @param	string	Comment as typed by user.
	 * @return	string	Comment sanitized for usage in HTML.
	 */
	static public function sanitizeComment($comment) {
		global $wgOut;

		return $wgOut->parse(str_replace(['&lt;nowiki&gt;', '&lt;pre&gt;', '&lt;/nowiki&gt;', '&lt;/pre&gt;'], ['<nowiki>', '<pre>', '</nowiki>', '</pre>'], htmlentities($comment, ENT_QUOTES)));
	}
}
