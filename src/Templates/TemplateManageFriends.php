<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @package   CurseProfile
 * @author    Noah Manneschmidt
 * @copyright (c) 2014 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki
 */

namespace CurseProfile\Templates;

use CurseProfile\Classes\FriendDisplay;
use Html;
use HydraCore;
use SpecialPage;
use User;

class TemplateManageFriends {
	/**
	 * Displays a list of friends.
	 *
	 * @param array $friends Raw friend IDs.
	 * @param string $pagination Pagination HTML
	 * @param int $itemsPerPage Items Per Page
	 * @param int $start Start Offset
	 * @return string
	 */
	public function display( $friends, $pagination, $itemsPerPage, $start ) {
		$html = '<h2>' . wfMessage( 'friends' ) . '</h2>';
		if ( count( $friends ) ) {
			$html .= $pagination;
			$html .= FriendDisplay::listFromArray( $friends, false, null, $itemsPerPage, $start );
			$html .= $pagination;
		} else {
			$html .= wfMessage( 'nofriends' )->plain();
		}
		return $html;
	}

	/**
	 * Displays a management page for friends
	 *
	 * @param User $actor The User performing friend management actions.
	 * @param array $friendTypes
	 * Association of friend types(accepted friends, sent requests, received requests) to User objects.
	 * @param int $itemsPerPage Items Per Page
	 * @param int $start Start Offset
	 *
	 * @return string Built HTML
	 */
	public function manage( User $actor, array $friendTypes, $itemsPerPage, $start ) {
		$friends = $friendTypes[ 'friends' ];
		$received = $friendTypes[ 'incoming_requests' ];
		$sent = $friendTypes[ 'outgoing_requests' ];

		$html = '';
		$pagination = count( $friends ) ? HydraCore::generatePaginationHtml(
			SpecialPage::getTitleFor( 'ManageFriends' ),
			count( $friends ), $itemsPerPage, $start
		) : '';

		if ( count( $received ) ) {
			$html .= '<h2>' . wfMessage( 'pendingrequests' ) . '</h2>';
			$html .= FriendDisplay::listFromArray( $received, true, $actor );
		}

		$html .= '<h2>' . wfMessage( 'friends' ) . '</h2>';
		if ( count( $friends ) ) {
			$html .= $pagination;
			$html .= FriendDisplay::listFromArray( $friends, true, $actor, $itemsPerPage, $start );
			$html .= $pagination;
		} else {
			$html .= wfMessage( 'soronery' )->plain();
		}

		if ( count( $sent ) ) {
			$html .= '<h2>' . wfMessage( 'sentrequests' ) . '</h2>';
			$html .= FriendDisplay::listFromArray( $sent, true, $actor );
		}

		$html .= '<h3>' . wfMessage( 'senddirectrequest' ) . '</h3>';
		$html .= Html::element(
			'input',
			[
				'type' => 'text',
				'id' => 'directfriendreq',
				'placeholder' => wfMessage( 'directfriendreqplaceholder' )->text()
			]
		);
		$html .= Html::element( 'button', [ 'id' => 'senddirectreq' ], wfMessage( 'sendrequest' )->text() );

		return '<div id="managefriends">' . $html . '</div>';
	}
}
