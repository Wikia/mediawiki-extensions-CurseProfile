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

use Html;
use HydraCore;
use MediaWiki\MediaWikiServices;
use MediaWiki\Session\CsrfTokenSet;
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
	public static function comments(&$parser, int $userId) {
		global $wgUser;

		if ($userId < 1) {
			return 'Invalid user ID given';
		}

		$userFactory = MediaWikiServices::getInstance()->getUserFactory();
		$html = '';

		$boardOwner = $userFactory->newFromId($userId);
		$html .= self::newCommentForm($boardOwner, false);

		$board = new CommentBoard($userFactory->newFromId($userId));
		$comments = $board->getComments($wgUser);

		foreach ($comments as $comment) {
			$html .= self::singleComment($comment, false);
		}

		return [
			$html,
			'isHTML' => true,
		];
	}

	/**
	 * Returns the HTML text for a comment entry form if the current user is logged in and not blocked
	 *
	 * @param  User $owner  User whose comment board will receive a new comment via this form
	 * @param  bool $hidden If true, the form will have an added class to be hidden by css/
	 * @return string	html fragment or empty string
	 */
	public static function newCommentForm(User $owner, bool $hidden = false) {
		global $wgRequest, $wgUser;

		$comment = Comment::newWithOwner($owner);
		if ($comment->canComment($wgUser)) {
			$tokenSet = new CsrfTokenSet($wgRequest);
			$commentPlaceholder = wfMessage('commentplaceholder')->escaped();
			$replyPlaceholder = wfMessage('commentreplyplaceholder')->escaped();
			$page = Title::newFromText("Special:AddComment/" . $owner->getId());
			return '
			<div class="commentdisplay add-comment' . ($hidden ? ' hidden' : '') . '">
				<div class="avatar">' . ProfilePage::userAvatar(null, 48, $wgUser->getEmail(), $wgUser->getName())[0] . '</div>
				<div class="entryform">
					<form action="' . $page->getFullUrl() . '" method="post">
						<textarea name="message" maxlength="' . Comment::MAX_LENGTH . '" data-replyplaceholder="' . $replyPlaceholder . '" placeholder="' . $commentPlaceholder . '"></textarea>
						<button name="inreplyto" class="submit wds-button" value="0">' . wfMessage('commentaction')->escaped() . '</button>
						' . Html::hidden('token', $tokenSet->getToken()) . '
					</form>
				</div>
			</div>';
		} else {
			$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
			$link = $linkRenderer->makeKnownLink(Title::newFromText('Special:ConfirmEmail'), wfMessage('no-perm-validate-email')->text());
			return "<div class='errorbox'>" . wfMessage('no-perm-profile-addcomment', $link)->text() . "</div>";
		}
	}

	/**
	 * Returns html display for a single profile comment
	 *
	 * @param  Comment $comment   Comment instance to pull data from.
	 * @param  integer $highlight [optional] ID of a comment to highlight from among those displayed.
	 * @return string	html for display
	 */
	public static function singleComment(Comment $comment, $highlight = false) {
		global $wgOut, $wgUser;

		$html = '';
		$cUser = $comment->getActorUser();

		$type = '';
		switch ($comment->getType()) {
			case Comment::PRIVATE_MESSAGE:
				$type = 'private';
				break;
			case Comment::DELETED_MESSAGE:
				$type = 'deleted';
				break;
			case Comment::PUBLIC_MESSAGE:
				$type = 'public';
				break;
		}

		if ($highlight === $comment->getId()) {
			$type .= ' highlighted';
		}

		if (HydraCore::isMobileSkin(RequestContext::getMain()->getSkin())) {
			$avatarSize = 36;
		} else {
			$avatarSize = 48;
		}

		$html .= '
		<div class="commentdisplay ' . $type . '" data-id="' . $comment->getId() . '">
			<a name="comment' . $comment->getId() . '"></a>
			<div class="commentblock">
				<div class="avatar">' . ProfilePage::userAvatar(null, $avatarSize, $cUser->getEmail(), $cUser->getName())[0] . '</div>
				<div class="commentheader">';
		$html .= '
					<div class="right">'
				. ($comment->getAdminActedUserId() ? self::adminAction($comment) . ', ' : '')
				. Html::rawElement('a', ['href' => SpecialPage::getTitleFor('CommentPermalink', $comment->getId(), 'comment' . $comment->getId())->getLinkURL()], self::timestamp($comment)) . ' '
				. ($comment->canReply($wgUser) ? Html::rawElement('a', ['href' => '#', 'class' => 'icon newreply', 'title' => wfMessage('replylink-tooltip')], HydraCore::awesomeIcon('reply')) . ' ' : '')
				. ($comment->canEdit($wgUser) ? Html::rawElement('a', ['href' => '#', 'class' => 'icon edit', 'title' => wfMessage('commenteditlink-tooltip')], HydraCore::awesomeIcon('pencil-alt')) . ' ' : '')
				. ($comment->canRemove($wgUser) ? Html::rawElement('a', ['href' => '#', 'class' => 'icon remove', 'title' => wfMessage('removelink-tooltip')], HydraCore::awesomeIcon('trash')) : '')
				. ($comment->canRestore($wgUser) ? Html::rawElement('a', ['href' => '#', 'class' => 'icon restore', 'title' => wfMessage('restorelink-tooltip')], HydraCore::awesomeIcon('undo')) : '')
				. ($comment->canPurge($wgUser) ? Html::rawElement('a', ['href' => '#', 'class' => 'icon purge', 'title' => wfMessage('purgelink-tooltip')], HydraCore::awesomeIcon('eraser')) : '')
				. ($comment->canReport($wgUser) ? Html::rawElement('a', ['href' => '#', 'class' => 'icon report', 'title' => wfMessage('reportlink-tooltip')], HydraCore::awesomeIcon('flag')) : '')
				. '
					</div>'
				. CP::userLink($comment->getActorUserId(), "commentUser");
		$html .= '
				</div>
				<div class="commentbody">
					' . self::sanitizeComment($comment->getMessage()) . '
				</div>
			</div>';
		$replies = $comment->getReplies($wgUser);
		if (!empty($replies)) {
			$html .= '<div class="replyset">';

			// perhaps there are more replies not yet loaded
			if ($comment->getTotalReplies($wgUser) > count($replies)) {
				if (!isset($repliesTooltip)) {
					$repliesTooltip = htmlspecialchars(wfMessage('repliestooltip')->plain(), ENT_QUOTES);
				}
				// force parsing this message because MW won't replace plurals as expected
				// due to this all happening inside the wfMessage()->parse() call that
				// generates the entire profile
				$viewReplies = Parser::stripOuterParagraph($wgOut->parseAsContent(wfMessage('viewearlierreplies', $comment->getTotalReplies($wgUser) - count($replies))->escaped()));
				$html .= "<button type='button' class='reply-count' data-id='{$comment->getId()}' title='{$repliesTooltip}'>{$viewReplies}</button>";
			}

			foreach ($replies as $reply) {
				$html .= self::singleComment($reply, $highlight);
			}
			$html .= '</div>';
		}
		$html .= '
		</div>';

		return $html;
	}

	/**
	 * Returns extra info visible only to admins on who and when admin action was taken on a comment
	 *
	 * @param Comment $comment
	 *
	 * @return string	HTML fragment
	 */
	private static function adminAction(Comment $comment) {
		$admin = $comment->getAdminActedUser();
		if (!$admin->getName()) {
			return '';
		}

		return wfMessage('cp-commentmoderated', $admin->getName())->text() . ' ' . CP::timeTag($comment->getAdminActionTimestamp());
	}

	/**
	 * Returns a <time> tag with a comment's post date or last edited date
	 *
	 * @param Comment $comment
	 *
	 * @return string	HTML fragment
	 */
	private static function timestamp(Comment $comment) {
		if ($comment->getEditTimestamp() === null) {
			return wfMessage('cp-commentposted')->text() . ' ' . CP::timeTag($comment->getPostTimestamp());
		} else {
			return wfMessage('cp-commentedited')->text() . ' ' . CP::timeTag($comment->getEditTimestamp());
		}
	}

	/**
	 * Returns a <time> tag with a comment's post date or last edited date for mobile.
	 *
	 * @param Comment $comment
	 *
	 * @return string	HTML fragment
	 */
	private static function mobileTimestamp(Comment $comment) {
		if ($comment->getEditTimestamp() === null) {
			return CP::timeTag($comment->getPostTimestamp(), true);
		} else {
			return CP::timeTag($comment->getEditTimestamp(), true);
		}
	}

	/**
	 * Unlike the previous comments function, this will create a new CommentBoard instance to fetch the data for you
	 *
	 * @param Comment $comment The comment for which replies need to be loaded
	 * @param User $actor The user the parent comment belongs to
	 * @return string HTML for display
	 */
	public static function repliesTo(Comment $comment, User $actor) {
		if ($actor->getId() < 1) {
			return 'Invalid user given';
		}
		$html = '';

		$comments = $comment->getReplies($actor, -1);

		if (empty($comments)) {
			$html = wfMessage('cp-nocommentreplies');
		} else {
			foreach ($comments as $comment) {
				$html .= self::singleComment($comment);
			}
		}

		return $html;
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
