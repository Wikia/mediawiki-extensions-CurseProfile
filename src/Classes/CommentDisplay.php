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
 */

namespace CurseProfile\Classes;

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
	 * @param mixed &$parser parser instance
	 * @param mixed $userId id of the user whose recent comments should be displayed
	 * @return array|string
	 */
	public static function comments( &$parser, $userId = 0 ) {
		$userIdentity = MediaWikiServices::getInstance()->getUserIdentityLookup()
			->getUserIdentityByUserId( (int)$userId );
		if ( !$userIdentity || !$userIdentity->isRegistered() ) {
			return 'Invalid user ID given';
		}

		$selectedUser = MediaWikiServices::getInstance()->getUserFactory()->newFromUserIdentity( $userIdentity );

		$html = self::newCommentForm( $selectedUser, false );

		$board = new CommentBoard( $selectedUser );
		$comments = $board->getComments( RequestContext::getMain()->getUser() );

		foreach ( $comments as $comment ) {
			$html .= self::singleComment( $comment, false );
		}

		return [ $html, 'isHTML' => true ];
	}

	/**
	 * Returns the HTML text for a comment entry form if the current user is logged in and not blocked
	 *
	 * @param User $owner User whose comment board will receive a new comment via this form
	 * @param bool $hidden If true, the form will have an added class to be hidden by css/
	 * @return string html fragment or empty string
	 */
	public static function newCommentForm( User $owner, bool $hidden = false ) {
		$requestUser = RequestContext::getMain()->getUser();

		$comment = Comment::newWithOwner( $owner );
		if ( $comment->canComment( $requestUser ) ) {
			$tokenSet = new CsrfTokenSet( RequestContext::getMain()->getRequest() );
			$commentPlaceholder = wfMessage( 'commentplaceholder' )->escaped();
			$replyPlaceholder = wfMessage( 'commentreplyplaceholder' )->escaped();
			$page = Title::newFromText( "Special:AddComment/" . $owner->getId() );
			return '
			<div class="commentdisplay add-comment' . ( $hidden ? ' hidden' : '' ) . '">
				<div class="avatar">' .
				ProfilePage::userAvatar( null, 48, $requestUser->getEmail(), $requestUser->getName() )[0] .
				'</div>
				<div class="entryform">
					<form action="' . $page->getFullUrl() . '" method="post">
						<textarea name="message" maxlength="' . Comment::MAX_LENGTH .
				'" data-replyplaceholder="' . $replyPlaceholder .
				'" placeholder="' . $commentPlaceholder . '"></textarea>
						<button name="inreplyto" class="submit wds-button" value="0">' .
				wfMessage( 'commentaction' )->escaped() . '</button>
						' . Html::hidden( 'token', $tokenSet->getToken() ) . '
					</form>
				</div>
			</div>';
		}

		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$link = $linkRenderer->makeKnownLink(
			Title::newFromText( 'Special:ConfirmEmail' ),
			wfMessage( 'no-perm-validate-email' )->text()
		);
		return "<div class='errorbox'>" . wfMessage( 'no-perm-profile-addcomment', $link )->text() . "</div>";
	}

	/**
	 * Returns html display for a single profile comment
	 *
	 * @param Comment $comment Comment instance to pull data from.
	 * @param int $highlight [optional] ID of a comment to highlight from among those displayed.
	 * @return string html for display
	 */
	public static function singleComment( Comment $comment, $highlight = false ) {
		$requestUser = RequestContext::getMain()->getUser();
		$output = RequestContext::getMain()->getOutput();

		$html = '';
		$cUser = $comment->getActorUser();

		$type = '';
		switch ( $comment->getType() ) {
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

		if ( $highlight === $comment->getId() ) {
			$type .= ' highlighted';
		}

		if ( HydraCore::isMobileSkin( RequestContext::getMain()->getSkin() ) ) {
			$avatarSize = 36;
		} else {
			$avatarSize = 48;
		}

		$html .= '
		<div class="commentdisplay ' . $type . '" data-id="' . $comment->getId() . '">
			<a name="comment' . $comment->getId() . '"></a>
			<div class="commentblock">
				<div class="avatar">' . ProfilePage::userAvatar(
					null,
					$avatarSize,
					$cUser->getEmail(),
					$cUser->getName()
			)[0] . '</div>
				<div class="commentheader">';
		$html .= '
					<div class="right">'
				. ( $comment->getAdminActedUserId() ? self::adminAction( $comment ) . ', ' : '' )
				. Html::rawElement(
					'a',
					[
						'href' => SpecialPage::getTitleFor(
							'CommentPermalink',
							$comment->getId(),
							'comment' . $comment->getId()
						)->getLinkURL()
					],
					self::timestamp( $comment )
			) . ' '
				. ( $comment->canReply( $requestUser ) ?
				Html::rawElement(
					'a',
					[ 'href' => '#', 'class' => 'icon newreply', 'title' => wfMessage( 'replylink-tooltip' ) ],
					HydraCore::awesomeIcon( 'reply' ) ) . ' ' : '' )
				. ( $comment->canEdit( $requestUser ) ?
				Html::rawElement(
					'a',
					[ 'href' => '#', 'class' => 'icon edit', 'title' => wfMessage( 'commenteditlink-tooltip' ) ],
					HydraCore::awesomeIcon( 'pencil-alt' ) ) . ' ' : '' )
				. ( $comment->canRemove( $requestUser ) ?
				Html::rawElement(
					'a',
					[ 'href' => '#', 'class' => 'icon remove', 'title' => wfMessage( 'removelink-tooltip' ) ],
					HydraCore::awesomeIcon( 'trash' ) ) : '' )
				. ( $comment->canRestore( $requestUser ) ?
				Html::rawElement(
					'a',
					[ 'href' => '#', 'class' => 'icon restore', 'title' => wfMessage( 'restorelink-tooltip' ) ],
					HydraCore::awesomeIcon( 'undo' ) ) : '' )
				. ( $comment->canPurge( $requestUser ) ?
				Html::rawElement(
					'a',
					[ 'href' => '#', 'class' => 'icon purge', 'title' => wfMessage( 'purgelink-tooltip' ) ],
					HydraCore::awesomeIcon( 'eraser' ) ) : '' )
				. ( $comment->canReport( $requestUser ) ?
				Html::rawElement(
					'a',
					[ 'href' => '#', 'class' => 'icon report', 'title' => wfMessage( 'reportlink-tooltip' ) ],
					HydraCore::awesomeIcon( 'flag' ) ) : '' )
				. '
					</div>'
				. CP::userLink( $comment->getActorUserId(), "commentUser" );
		$html .= '
				</div>
				<div class="commentbody">
					' . self::sanitizeComment( $comment->getMessage() ) . '
				</div>
			</div>';
		$replies = $comment->getReplies( $requestUser );
		if ( !empty( $replies ) ) {
			$html .= '<div class="replyset">';

			// perhaps there are more replies not yet loaded
			if ( $comment->getTotalReplies( $requestUser ) > count( $replies ) ) {
				$repliesTooltip = htmlspecialchars( wfMessage( 'repliestooltip' )->plain(), ENT_QUOTES );
				// force parsing this message because MW won't replace plurals as expected
				// due to this all happening inside the wfMessage()->parse() call that
				// generates the entire profile
				$viewReplies = Parser::stripOuterParagraph(
					$output->parseAsContent(
						wfMessage(
							'viewearlierreplies',
							$comment->getTotalReplies( $requestUser ) - count( $replies )
						)->escaped()
					)
				);
				$html .= "<button type='button' class='reply-count' data-id='{$comment->getId()}' " .
					" title='{$repliesTooltip}'>{$viewReplies}</button>";
			}

			foreach ( $replies as $reply ) {
				$html .= self::singleComment( $reply, $highlight );
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
	 * @return string HTML fragment
	 */
	private static function adminAction( Comment $comment ) {
		$admin = $comment->getAdminActedUser();
		if ( !$admin->getName() ) {
			return '';
		}

		return wfMessage( 'cp-commentmoderated', $admin->getName() )->text() .
			' ' . CP::timeTag( $comment->getAdminActionTimestamp() );
	}

	/**
	 * Returns a <time> tag with a comment's post date or last edited date
	 *
	 * @param Comment $comment
	 *
	 * @return string HTML fragment
	 */
	private static function timestamp( Comment $comment ): string {
		if ( $comment->getEditTimestamp() === null ) {
			return wfMessage( 'cp-commentposted' )->text() . ' ' . CP::timeTag( $comment->getPostTimestamp() );
		}

		return wfMessage( 'cp-commentedited' )->text() . ' ' . CP::timeTag( $comment->getEditTimestamp() );
	}

	/**
	 * Returns a <time> tag with a comment's post date or last edited date for mobile.
	 *
	 * @param Comment $comment
	 *
	 * @return string HTML fragment
	 */
	private static function mobileTimestamp( Comment $comment ): string {
		if ( $comment->getEditTimestamp() === null ) {
			return CP::timeTag( $comment->getPostTimestamp(), true );
		}

		return CP::timeTag( $comment->getEditTimestamp(), true );
	}

	/**
	 * Unlike the previous comments function, this will create a new CommentBoard instance to fetch the data for you
	 *
	 * @param Comment $comment The comment for which replies need to be loaded
	 * @param User $actor The user the parent comment belongs to
	 * @return string HTML for display
	 */
	public static function repliesTo( Comment $comment, User $actor ) {
		if ( $actor->getId() < 1 ) {
			return 'Invalid user given';
		}

		$replies = $comment->getReplies( $actor, -1 );

		if ( empty( $replies ) ) {
			return wfMessage( 'cp-nocommentreplies' );
		}

		$html = '';
		foreach ( $replies as $reply ) {
			$html .= self::singleComment( $reply );
		}

		return $html;
	}

	/**
	 * Sanitizes a comment for display in HTML.
	 *
	 * @param string $comment Comment as typed by user.
	 * @return string Comment sanitized for usage in HTML.
	 */
	public static function sanitizeComment( $comment ) {
		$output = RequestContext::getMain()->getOutput();

		$popts = $output->parserOptions();
		$oldIncludeSize = $popts->setMaxIncludeSize( 0 );

		$parserOutput = MediaWikiServices::getInstance()->getParserFactory()->getInstance()->parse(
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
				htmlentities( $comment, ENT_QUOTES )
			),
			$output->getTitle(),
			$popts
		);

		$popts->setMaxIncludeSize( $oldIncludeSize );
		return $parserOutput->getText();
	}
}
