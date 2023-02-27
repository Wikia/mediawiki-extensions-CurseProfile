<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @package   CurseProfile
 * @author    Noah Manneschmidt
 * @copyright (c) 2015 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki
 */

namespace CurseProfile\Templates;

use CurseProfile\Classes\CommentReport;
use CurseProfile\Classes\CP;
use CurseProfile\Classes\ProfilePage;
use Html;
use MediaWiki\MediaWikiServices;
use SpecialPage;

class TemplateCommentModeration {
	// Max number of small reporter avatars to display above a comment
	private const MAX_REPORTER_AVATARS = 3;

	/**
	 * Renders the group and sort "tabs" at the top of the CommentModeration page
	 *
	 * @param string $currentStyle indicating the current sort style
	 * @return string HTML fragment
	 */
	public function sortStyleSelector( $currentStyle ) {
		$styles = [
			'byVolume' => [ 'commentmoderation-byvolume', 'default' ],
			// 'byWiki' => ['By Origin Wiki'],
			// 'byUser' => ['By Reported User'],
			// 'byDate' => ['Most Recent Reports First'],
			'byActionDate' => [ 'commentmoderation-byactiondate' ],
		];
		$html = '';

		foreach ( $styles as $key => $sort ) {
			if ( !empty( $html ) ) {
				$html .= ' | ';
			}
			$params = [];
			if ( isset( $sort[ 1 ] ) ) {
				$title = SpecialPage::getTitleFor( 'CommentModeration' );
			} else {
				$title = SpecialPage::getTitleFor( 'CommentModeration/' . $key );
			}
			$params[ 'href' ] = $title->getLocalUrl();
			if ( $currentStyle == $key ) {
				$params[ 'style' ] = 'font-weight: bold;';
			}
			$html .= Html::element( 'a', $params, wfMessage( $sort[ 0 ] )->text() );
		}

		return '<p>' . wfMessage( 'commentmoderation-view' )->text() . ': ' . $html . '</p>';
	}

	/**
	 * Renders the main body of the CommentModeration special page
	 *
	 * @param array $reports CommentReport instances.
	 * @return string HTML fragment
	 */
	public function renderComments( $reports ) {
		$userFactory = MediaWikiServices::getInstance()->getUserFactory();
		$html = '
				<div id="commentmoderation" class="comments">';

		foreach ( $reports as $report ) {
			$rep = $report->data;
			$author = $userFactory->newFromId( $rep[ 'comment' ][ 'author' ] );
			if ( $author ) { // handle failures where central ID doesn't exist on local wiki
				$html .= '
					<div class="report-item" data-key="' . $report->reportKey() . '">';

				$html .= Html::rawElement( 'p', [], $this->itemLine( $rep ) );
				$html .= '
						<div class="reported-comment">
							<div class="commentdisplay">
								<div class="avatar">' .
					ProfilePage::userAvatar( null, 48, $author->getEmail(), $author->getName() )[ 0 ] . '</div>
								<div><div class="right">' .
					$this->permalink( $rep ) . '</div>' . CP::userLink( $author ) . '</div>
								<div class="commentbody">' . htmlspecialchars( $rep[ 'comment' ][ 'text' ] ) . '</div>
							</div>';

				if ( $report->data[ 'action_taken' ] == CommentReport::ACTION_NONE ) {
					$html .= '
							<div class="moderation-actions">
								<div class="actions"><a class="del">' .
						wfMessage( 'commentmoderation-delete' )->text() .
						'</a> <a class="dis">' .
						wfMessage( 'commentmoderation-dismiss' )->text() .
						'</a></div>
								<div class="confirm"><a></a></div>
							</div>';
				} else {
					$html .= '
							<div class="moderation-actions">' . $this->actionTaken( $report ) . '</div>';
				}

				$html .= '
						</div>';

				$html .= '
					</div>';
			}
		}

		$html .= '
				</div>';

		return $html;
	}

	private function actionTaken( $rep ) {
		$user = MediaWikiServices::getInstance()->getUserFactory()
			->newFromId( (int)$rep->data[ 'action_taken_by' ] );
		switch ( $rep->data[ 'action_taken' ] ) {
			case CommentReport::ACTION_DISMISS:
				$action = 'dis';
				break;

			case CommentReport::ACTION_DELETE:
				$action = 'del';
				break;
		}
		return Html::rawElement(
			'span',
			[ 'class' => 'action-taken ' . $action ],
			wfMessage( 'report-actiontaken-' . $action, $user->getName() )->text() .
			' ' . CP::timeTag( $rep->data[ 'action_taken_at' ] )
		);
	}

	/**
	 * Produces the introduction line above a reported comment "First reporteded X time ago by [user]:"
	 *
	 * @param array $rep CommentReport data
	 * @return string HTML fragment
	 */
	private function itemLine( $rep ) {
		if ( count( $rep[ 'reports' ] ) <= self::MAX_REPORTER_AVATARS ) {
			return wfMessage(
				'commentmoderation-item',
				CP::timeTag( $rep[ 'first_reported' ] ),
				$this->reporterIcons( $rep[ 'reports' ] )
			)->text();
		} else {
			return wfMessage(
				'commentmoderation-item-andothers',
				CP::timeTag( $rep[ 'first_reported' ] ),
				$this->reporterIcons( $rep[ 'reports' ] ),
				count( $rep[ 'reports' ] ) - self::MAX_REPORTER_AVATARS
			)->text();
		}
	}

	/**
	 * Creates the small user icons indicating who has reported a comment
	 *
	 * @param array $reports Array of users reporting: {reporter: CURSE_ID, timestamp: UTC_TIME}
	 * @return string HTML fragment
	 */
	private function reporterIcons( $reports ) {
		$userFactory = MediaWikiServices::getInstance()->getUserFactory();
		$html = '';
		$iter = 0;
		foreach ( $reports as $rep ) {
			$reporter = $userFactory->newFromId( $rep[ 'reporter' ] );
			$title = htmlspecialchars( $reporter->getName(), ENT_QUOTES );
			$html .= Html::rawElement(
				'a',
				[ 'href' => $reporter->getUserPage()->getLinkURL() ],
				ProfilePage::userAvatar( null, 24, $reporter->getEmail(), $reporter->getName(), "title='$title'" )[ 0 ]
			);
			$iter += 1;
			if ( $iter >= self::MAX_REPORTER_AVATARS ) {
				break;
			}
		}
		return $html;
	}

	/**
	 * Returns a permalink to a comment on its origin wiki.
	 *
	 * @param mixed $rep CommentReport instance
	 * @return string HTML fragment
	 */
	private function permalink( $rep ) {
		$commentPermanentLink = SpecialPage::getTitleFor(
			'CommentPermalink',
			$rep[ 'comment' ][ 'cid' ],
			'comment' . $rep[ 'comment' ][ 'cid' ]
		)->getFullURL();
		return Html::rawElement(
			'a',
			[ 'href' => $commentPermanentLink ],
			'content as posted ' . CP::timeTag( $rep[ 'comment' ][ 'last_touched' ] )
		);
	}
}
