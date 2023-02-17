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

namespace CurseProfile\Api;

use ApiMain;
use CurseProfile\Classes\FriendDisplay;
use CurseProfile\Classes\Friendship;
use HydraApiBase;
use MediaWiki\User\UserFactory;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Class that allows friendship actions to be performed by AJAX calls.
 */
class FriendApi extends HydraApiBase {
	public function __construct( ApiMain $main, $action, private UserFactory $userFactory ) {
		parent::__construct( $main, $action );
	}

	/** @inheritDoc */
	public function getActions(): array {
		$basicAction = [
			'tokenRequired' => true,
			'postRequired' => true,
			'params' => [
				'user_id' => [
					ParamValidator::PARAM_TYPE => 'string',
					ParamValidator::PARAM_REQUIRED => true
				]
			]
		];

		return [
			'send' => $basicAction,
			'confirm' => $basicAction,
			'ignore' => $basicAction,
			'remove' => $basicAction,

			'directreq' => [
				'tokenRequired' => true,
				'postRequired' => true,
				'params' => [
					'name' => [
						ParamValidator::PARAM_TYPE => 'string',
						ParamValidator::PARAM_REQUIRED => true
					]
				]
			]
		];
	}

	protected function doDirectreq(): void {
		$user = $this->getUser();

		$targetUser = $this->userFactory->newFromName( $this->getMain()->getVal( 'name' ) );
		if ( !$targetUser ) {
			$this->dieWithError( $this->msg( 'friendrequest-direct-notfound' ), 'friendrequest-direct-notfound' );
		}

		if ( $targetUser->isAnon() ) {
			$this->dieWithError( $this->msg( 'friendrequest-direct-notfound' ), 'friendrequest-direct-notfound' );
		}

		$friendship = new Friendship( $user );
		$result = $friendship->sendRequest( $targetUser );
		if ( is_array( $result ) && isset( $result['error'] ) ) {
			$this->dieWithError(
				$this->msg( $result['error'] )->params( $targetUser->getName(), $user->getName() ),
				$result['error']
			);
		}

		if ( !$result ) {
			$this->dieWithError( $this->msg( 'friendrequestsend-error' ), 'friendrequestsend-error' );
		}

		$html = $this->msg( 'friendrequest-direct-success' )->text();
		$this->getResult()->addValue( null, 'result', $result );
		$this->getResult()->addValue( null, 'html', $html );
	}

	protected function doSend(): void {
		$toUser = $this->userFactory->newFromId( $this->getInt( 'user_id' ) );
		$friendship = new Friendship( $this->getUser() );
		$result = $friendship->sendRequest( $toUser );
		$html = FriendDisplay::friendButtons( $toUser, $this->getUser() );
		$this->getResult()->addValue( null, 'result', $result );
		$this->getResult()->addValue( null, 'html', $html );
	}

	protected function doConfirm(): void {
		$toUser = $this->userFactory->newFromId( $this->getInt( 'user_id' ) );
		$friendship = new Friendship( $this->getUser() );
		$result = $friendship->acceptRequest( $toUser );
		$html = $this->msg( $result ? 'alreadyfriends' : 'friendrequestconfirm-error' )->plain();
		$this->getResult()->addValue( null, 'result', $result );
		$this->getResult()->addValue( null, 'html', $html );
	}

	protected function doIgnore(): void {
		$friendship = new Friendship( $this->getUser() );
		$toUser = $this->userFactory->newFromId( $this->getInt( 'user_id' ) );
		$rel = $friendship->getRelationship( $toUser );
		$result = $friendship->ignoreRequest( $toUser );
		if ( $rel == Friendship::REQUEST_RECEIVED ) {
			$this->getResult()->addValue( null, 'remove', true );
		}
		$this->getResult()->addValue( null, 'result', $result );
	}

	protected function doRemove(): void {
		$friendship = new Friendship( $this->getUser() );
		$toUser = $this->userFactory->newFromId( $this->getInt( 'user_id' ) );
		$result = $friendship->removeFriend( $toUser );
		$html = FriendDisplay::friendButtons( $toUser, $this->getUser() );
		$this->getResult()->addValue( null, 'result', $result );
		$this->getResult()->addValue( null, 'html', $html );
	}
}
