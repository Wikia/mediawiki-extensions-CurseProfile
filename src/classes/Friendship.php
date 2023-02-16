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

use Cheevos\Cheevos;
use Cheevos\CheevosException;
use MediaWiki\MediaWikiServices;
use MWException;
use Reverb\Notification\NotificationBroadcast;
use SpecialPage;
use Title;
use User;

/**
 * Class that manages friendship relations between users. Create an instance with a User object.
 * All relationships statuses are then described from the perspective of that user.
 */
class Friendship {
	private $user;

	/**
	 * Relationship status constants
	 */
	const STRANGERS        = 1;
	const FRIENDS          = 2;
	const REQUEST_SENT     = 3;
	const REQUEST_RECEIVED = 4;

	/**
	 * The user passed to the constructor is used as the main user from which the
	 * perspective of the SENT/RECEIVED status are determined.
	 *
	 * @param User $user
	 */
	public function __construct( User $user ) {
		if ( !$user->getId() ) {
			throw new MWException( 'Anonymous user object passed.' );
		}
		$this->user = $user;
	}

	/**
	 * Check the relationship status between two users.
	 *
	 * @param User $toUser
	 *
	 * @return int -1 on failure or one of the class constants STRANGERS, FRIENDS, REQUEST_SENT, REQUEST_RECEIVED
	 */
	public function getRelationship( User $toUser ) {
		if ( !$this->checkIfValidUserRelation( $toUser ) ) {
			return -1;
		}

		try {
			$status = Cheevos::getFriendStatus( $this->user, $toUser );
			if ( $status['status'] ) {
				return $status['status'];
			}
		} catch ( CheevosException $e ) {
			wfDebug( __METHOD__ . ": Caught CheevosException - " . $e->getMessage() );
		}
		return -1;
	}

	/**
	 * Returns the array of user IDs for this or another user's friends
	 *
	 * @return array User IDs of friends
	 */
	public function getFriends() {
		try {
			$friendTypes = [
				'friends' => [],
				'incoming_requests' => [],
				'outgoing_requests' => []
			];
			$friendTypes = array_merge( $friendTypes, array_intersect_key( Cheevos::getFriends( $this->user ), $friendTypes ) );
			foreach ( $friendTypes as $type => $data ) {
				$friendTypes[$type] = (array)$data;
			}
			return $friendTypes;
		} catch ( CheevosException $e ) {
			wfDebug( __METHOD__ . ": Caught CheevosException - " . $e->getMessage() );
			// Return default array after Cheevos Exception.
			return $friendTypes;
		}
	}

	/**
	 * Returns the number of friends a user has
	 *
	 * @return int Number of friends
	 */
	public function getFriendCount() {
		// my god look how efficient this is
		$friendTypes = $this->getFriends();
		return count( $friendTypes['friends'] );
	}

	/**
	 * Sends a friend request to a given user.
	 *
	 * @param User $toUser
	 *
	 * @return bool True on success, False on failure.
	 */
	public function sendRequest( User $toUser ) {
		if ( !$this->checkIfValidUserRelation( $toUser ) ) {
			return false;
		}

		if ( $this->user->getBlock() !== null ) {
			return [ 'error' => 'friendrequest-blocked' ];
		}

		if ( $toUser->getBlock() !== null ) {
			return [ 'error' => 'friendrequest-blocked-other' ];
		}

		$relationShip = $this->getRelationship( $toUser );

		if ( $relationShip == -1 ) {
			return [ 'error' => 'friendrequest-status-unavailable' ];
		}

		if ( $relationShip !== self::STRANGERS ) {
			return [ 'error' => 'friendrequest-already-friends' ];
		}

		try {
			$makeFriend = Cheevos::createFriendRequest( $this->user, $toUser );
		} catch ( CheevosException $e ) {
			wfDebug( __METHOD__ . ": Caught CheevosException - " . $e->getMessage() );
			return false;
		}

		$fromUserTitle = Title::makeTitle( NS_USER_PROFILE, $this->user->getName() );
		$canonicalUrl = SpecialPage::getTitleFor( 'ManageFriends' )->getFullURL();
		$broadcast = NotificationBroadcast::newSingle(
			'user-interest-profile-friendship',
			$this->user,
			$toUser,
			[
				'url' => $canonicalUrl,
				'message' => [
					[
						'user_note',
						''
					],
					[
						1,
						$this->user->getName()
					],
					[
						2,
						$fromUserTitle->getFullURL()
					],
					[
						3,
						$canonicalUrl
					]
				]
			]
		);
		if ( $broadcast ) {
			$broadcast->transmit();
		}

		MediaWikiServices::getInstance()->getHookContainer()
			->run( 'CurseProfileAddFriend', [ $this->user, $toUser ] );
		return true;
	}

	/**
	 * Accepts a pending request.
	 *
	 * @param User $toUser
	 *
	 * @return bool True on success, False on failure.
	 */
	public function acceptRequest( User $toUser ) {
		if ( !$this->checkIfValidUserRelation( $toUser ) ) {
			return false;
		}

		try {
			$res = Cheevos::acceptFriendRequest( $this->user, $toUser );
			if ( $res['message'] == "success" ) {
				return true;
			}
		} catch ( CheevosException $e ) {
			wfDebug( __METHOD__ . ": Caught CheevosException - " . $e->getMessage() );
		}
		return false;
	}

	/**
	 * Ignores and dismisses a pending request.
	 *
	 * @param User $toUser
	 *
	 * @return bool True on success, False on failure.
	 */
	public function ignoreRequest( User $toUser ) {
		if ( !$this->checkIfValidUserRelation( $toUser ) ) {
			return false;
		}

		try {
			$res = Cheevos::cancelFriendRequest( $this->user, $toUser );
			if ( $res['message'] == "success" ) {
				return true;
			}
		} catch ( CheevosException $e ) {
			wfDebug( __METHOD__ . ": Caught CheevosException - " . $e->getMessage() );
		}
		return false;
	}

	/**
	 * Removes a friend relationship or cancels a pending request.
	 *
	 * @param User $toUser
	 *
	 * @return bool True on success, False on failure.
	 */
	public function removeFriend( User $toUser ) {
		if ( !$this->checkIfValidUserRelation( $toUser ) ) {
			return false;
		}

		try {
			Cheevos::cancelFriendRequest( $this->user, $toUser );
		} catch ( CheevosException $e ) {
			wfDebug( __METHOD__ . ": Caught CheevosException - " . $e->getMessage() );
			return false;
		}

		MediaWikiServices::getInstance()->getHookContainer()
			->run( 'CurseProfileRemoveFriend', [ $this->user, $toUser ] );

		return true;
	}

	/**
	 * Make sure that the User objects are valid and that they are not the same user.
	 *
	 * @param User|null $to
	 *
	 * @return bool All checks passed.
	 */
	private function checkIfValidUserRelation( ?User $to ): bool {
		$from = $this->user;
		// No null users.
		if ( !$from || !$to ) {
			return false;
		}

		// Not the same user.
		if ( $from->getId() === $to->getId() ) {
			return false;
		}

		// No anonymous users.
		if ( $from->isAnon() || $to->isAnon() ) {
			return false;
		}
		return true;
	}
}
