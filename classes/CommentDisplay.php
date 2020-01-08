<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @package   CurseProfile
 * @author    Noah Manneschmidt
 * @copyright (c) 2013 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki
**/

namespace CurseProfile;

use CentralIdLookup;
use Html;
use HydraCore;
use Parser;
use RequestContext;
use SpecialPage;
use Title;
use User;

/**
 * A class to manage displaying a list of friends on a user profile
 */
class CommentDisplay {
	/**
	 * Responds to the comments parser hook that displays recent comments on a profile
	 *
	 * @param  object  &$parser parser instance
	 * @param  integer $userId  id of the user whose recent comments should be displayed
	 * @return array	with html at index 0
	 */
	public static function comments(&$parser, int $userId = null) {
		$userId = intval($userId);
		if ($userId < 1) {
			return 'Invalid user ID given';
		}

		$HTML = '';

		$HTML .= self::newCommentForm($userId, false);

		$board = new CommentBoard(User::newFromId($userId));
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
	 * @param  integer $userId ID of the user whose comment board will receive a new comment via this form
	 * @param  bool    $hidden If true, the form will have an added class to be hidden by css/
	 * @return string	html fragment or empty string
	 */
	public static function newCommentForm($userId, $hidden = false) {
		global $wgUser;

		$targetUser = User::newFromId($userId);
		if (CommentBoard::canComment($targetUser, $wgUser)) {
			$commentPlaceholder = wfMessage('commentplaceholder')->escaped();
			$replyPlaceholder = wfMessage('commentreplyplaceholder')->escaped();
			$page = Title::newFromText("Special:AddComment/" . $userId);
			return '
			<div class="commentdisplay add-comment' . ($hidden ? ' hidden' : '') . '">
				<div class="avatar">' . ProfilePage::userAvatar(null, 48, $wgUser->getEmail(), $wgUser->getName())[0] . '</div>
				<div class="entryform">
					<form action="' . $page->getFullUrl() . '" method="post">
						<textarea name="message" maxlength="' . CommentBoard::MAX_LENGTH . '" data-replyplaceholder="' . $replyPlaceholder . '" placeholder="' . $commentPlaceholder . '"></textarea>
						<button name="inreplyto" class="submit" value="0">' . wfMessage('commentaction')->escaped() . '</button>
						' . Html::hidden('token', $wgUser->getEditToken()) . '
					</form>
				</div>
			</div>';
		} else {
			return "<div class='errorbox'>" . wfMessage('no-perm-profile-addcomment', \Linker::linkKnown(Title::newFromText('Special:ConfirmEmail'), wfMessage('no-perm-validate-email')->text()))->text() . "</div>";
		}
	}

	/**
	 * Returns html display for a single profile comment
	 *
	 * @param  array   $comment   structured comment data as returned by CommentBoard
	 * @param  integer $highlight [optional] id of a comment to highlight from among those displayed
	 * @return string	html for display
	 */
	public static function singleComment($comment, $highlight = false) {
		global $wgOut, $wgUser;

		$HTML = '';
		$cUser = User::newFromId($comment['ub_user_id_from']);

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

		if (HydraCore::isMobileSkin(RequestContext::getMain()->getSkin())) {
			$avatarSize = 36;
		} else {
			$avatarSize = 48;
		}

		$HTML .= '
		<div class="commentdisplay ' . $type . '" data-id="' . $comment['ub_id'] . '">
			<a name="comment' . $comment['ub_id'] . '"></a>
			<div class="commentblock">
				<div class="avatar">' . ProfilePage::userAvatar(null, $avatarSize, $cUser->getEmail(), $cUser->getName())[0] . '</div>
				<div class="commentheader">';
		$HTML .= '
					<div class="right">'
				. ($comment['ub_admin_acted_user_id'] ? self::adminAction($comment) . ', ' : '')
				. Html::rawElement('a', ['href' => SpecialPage::getTitleFor('CommentPermalink', $comment['ub_id'], 'comment' . $comment['ub_id'])->getLinkURL()], self::timestamp($comment)) . ' '
				. (CommentBoard::canReply($comment, $wgUser) ? Html::rawElement('a', ['href' => '#', 'class' => 'icon newreply', 'title' => wfMessage('replylink-tooltip')], HydraCore::awesomeIcon('reply')) . ' ' : '')
				. (CommentBoard::canEdit($comment, $wgUser) ? Html::rawElement('a', ['href' => '#', 'class' => 'icon edit', 'title' => wfMessage('commenteditlink-tooltip')], HydraCore::awesomeIcon('pencil-alt')) . ' ' : '')
				. (CommentBoard::canRemove($comment, $wgUser) ? Html::rawElement('a', ['href' => '#', 'class' => 'icon remove', 'title' => wfMessage('removelink-tooltip')], HydraCore::awesomeIcon('trash')) : '')
				. (CommentBoard::canRestore($comment, $wgUser) ? Html::rawElement('a', ['href' => '#', 'class' => 'icon restore', 'title' => wfMessage('restorelink-tooltip')], HydraCore::awesomeIcon('undo')) : '')
				. (CommentBoard::canPurge($wgUser) ? Html::rawElement('a', ['href' => '#', 'class' => 'icon purge', 'title' => wfMessage('purgelink-tooltip')], HydraCore::awesomeIcon('eraser')) : '')
				. (CommentBoard::canReport($comment, $wgUser) ? Html::rawElement('a', ['href' => '#', 'class' => 'icon report', 'title' => wfMessage('reportlink-tooltip')], HydraCore::awesomeIcon('flag')) : '')
				. '
					</div>'
				. CP::userLink($comment['ub_user_id_from'], "commentUser");
		$HTML .= '
				</div>
				<div class="commentbody">
					' . self::sanitizeComment($comment['ub_message']) . '
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
				$viewReplies = Parser::stripOuterParagraph($wgOut->parse(wfMessage('viewearlierreplies', $comment['reply_count'] - count($comment['replies']))->escaped()));
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
	 *
	 * @param  array $comment comment data
	 * @return string	html fragment
	 */
	private static function adminAction($comment) {
		$admin = User::newFromId($comment['ub_admin_acted_user_id']);
		if (!$admin->getName()) {
			return '';
		}

		return wfMessage('cp-commentmoderated', $admin->getName())->text() . ' ' . CP::timeTag($comment['ub_admin_acted_at']);
	}

	/**
	 * Returns a <time> tag with a comment's post date or last edited date
	 *
	 * @param  array $comment comment data
	 * @return string	html fragment
	 */
	private static function timestamp($comment) {
		if ($comment['ub_edited'] === null) {
			return wfMessage('cp-commentposted')->text() . ' ' . CP::timeTag($comment['ub_date']);
		} else {
			return wfMessage('cp-commentedited')->text() . ' ' . CP::timeTag($comment['ub_edited']);
		}
	}

	/**
	 * Returns a <time> tag with a comment's post date or last edited date for mobile.
	 *
	 * @param  array $comment comment data
	 * @return string	html fragment
	 */
	private static function mobileTimestamp($comment) {
		if ($comment['ub_edited'] === null) {
			return CP::timeTag($comment['ub_date'], true);
		} else {
			return CP::timeTag($comment['ub_edited'], true);
		}
	}

	/**
	 * Unlike the previous comments function, this will create a new CommentBoard instance to fetch the data for you
	 *
	 * @param  integer $userId    the id of the user the parent comment belongs to
	 * @param  integer $commentId the id of the comment for which replies need to be loaded
	 * @return string	html for display
	 */
	public static function repliesTo(int $userId, int $commentId) {
		if ($userId < 1) {
			return 'Invalid user ID given';
		}
		$HTML = '';

		$board = new CommentBoard(User::newFromId($userId));
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
	 * @param  string $comment Comment as typed by user.
	 * @return string	Comment sanitized for usage in HTML.
	 */
	public static function sanitizeComment($comment) {
		global $wgOut, $wgParser;

		$popts = $wgOut->parserOptions();
		$oldIncludeSize = $popts->setMaxIncludeSize(0);

		$parserOutput = $wgParser->getFreshParser()->parse(
			str_replace(
				[
					'&lt;nowiki&gt;',
					'&lt;pre&gt;',
					'&lt;/nowiki&gt;',
					'&lt;/pre&gt;',
					'&#039;',
					'&#034;'
				],
				[
					'<nowiki>',
					'<pre>',
					'</nowiki>',
					'</pre>',
					"'",
					'"'
				],
				htmlentities($comment, ENT_QUOTES)
			),
			$wgOut->getTitle(),
			$popts
		);

		$popts->setMaxIncludeSize($oldIncludeSize);
		return $parserOutput->getText();
	}
}
