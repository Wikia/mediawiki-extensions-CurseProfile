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

use ApiBase;
use HydraApiBase;
use RequestContext;
use User;

/**
 * Class that allows friendship actions to be performed by AJAX calls.
 */
class FriendApi extends HydraApiBase {
	/**
	 * Get Actions
	 *
	 * @return array
	 */
	public function getActions() {
		$basicAction = [
			'tokenRequired' => true,
			'postRequired' => true,
			'params' => [
				'user_id' => [
					ApiBase::PARAM_TYPE => 'string',
					ApiBase::PARAM_REQUIRED => true
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
						ApiBase::PARAM_TYPE => 'string',
						ApiBase::PARAM_REQUIRED => true
					]
				]
			]
		];
	}

	/**
	 * Execute
	 *
	 * @return void
	 */
	public function execute() {
		$this->f = new Friendship($this->getUser());
		parent::execute();
	}

	/**
	 * Do Direct Req
	 *
	 * @return void
	 */
	protected function doDirectreq() {
		$wgUser = RequestContext::getMain()->getUser();

		$targetUser = User::newFromName($this->getMain()->getVal('name'));
		if (!$targetUser) {
			$this->dieUsage(wfMessage('friendrequest-direct-notfound')->text(), 'friendrequest-direct-notfound');
		}
		$targetUser->load();
		if ($targetUser->isAnon()) {
			$this->dieUsage(wfMessage('friendrequest-direct-notfound')->text(), 'friendrequest-direct-notfound');
		}

		$result = $this->f->sendRequest($targetUser->getId());
		if (is_array($result) && isset($result['error'])) {
			$this->dieUsage(wfMessage($result['error'])->params($targetUser->getName(), $wgUser->getName())->text(), $result['error']);
		} elseif (!$result) {
			$this->dieUsage(wfMessage('friendrequestsend-error')->text(), 'friendrequestsend-error');
		}
		$html = wfMessage('friendrequest-direct-success')->text();
		$this->getResult()->addValue(null, 'result', $result);
		$this->getResult()->addValue(null, 'html', $html);
	}

	/**
	 * Do Send
	 *
	 * @return void
	 */
	protected function doSend() {
		$userId = $this->getMain()->getInt('user_id');
		$result = $this->f->sendRequest($userId);
		$html = FriendDisplay::friendButtons($userId, true);
		$this->getResult()->addValue(null, 'result', $result);
		$this->getResult()->addValue(null, 'html', $html);
	}

	/**
	 * Do Confirm
	 *
	 * @return void
	 */
	protected function doConfirm() {
		$userId = $this->getMain()->getInt('user_id');
		$result = $this->f->acceptRequest($userId);
		$html = wfMessage($result ? 'alreadyfriends' : 'friendrequestconfirm-error')->plain();
		$this->getResult()->addValue(null, 'result', $result);
		$this->getResult()->addValue(null, 'html', $html);
	}

	/**
	 * Do Ignore
	 *
	 * @return void
	 */
	protected function doIgnore() {
		$userId = $this->getMain()->getInt('user_id');
		$rel = $this->f->getRelationship($userId);
		$result = $this->f->ignoreRequest($userId);
		if ($rel == Friendship::REQUEST_RECEIVED) {
			$this->getResult()->addValue(null, 'remove', true);
		}
		$this->getResult()->addValue(null, 'result', $result);
	}

	/**
	 * Do Remove
	 *
	 * @return void
	 */
	protected function doRemove() {
		$userId = $this->getMain()->getInt('user_id');
		$result = $this->f->removeFriend($userId);
		$html = FriendDisplay::friendButtons($userId, true);
		$this->getResult()->addValue(null, 'result', $result);
		$this->getResult()->addValue(null, 'html', $html);
	}
}
