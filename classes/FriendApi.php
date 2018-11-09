<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @author		Noah Manneschmidt
 * @copyright	(c) 2013 Curse Inc.
 * @license		Proprietary
 * @package		CurseProfile
 * @link		http://www.curse.com/
 *
**/
namespace CurseProfile;

use ApiBase;
use CentralIdLookup;
use HydraApiBase;
use User;

/**
 * Class that allows friendship actions to be performed by AJAX calls.
 */
class FriendApi extends HydraApiBase {

	/**
	 * Get Description
	 *
	 * @return string
	 */
	public function getDescription() {
		return 'Allows friending actions to be taken.';
	}

	/**
	 * Get param Description
	 *
	 * @return array
	 */
	public function getParamDescription() {
		return [
			'do' => 'The friending action to be taken (send, confirm, ignore, remove)',
			'global_id' => 'The user upon which the action should be taken',
			'name' => 'The username to be added as a friend',
			'token' => 'The edit token for the requesting user',
		];
	}

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
				'global_id' => [
					ApiBase::PARAM_TYPE => 'string',
					ApiBase::PARAM_REQUIRED => true,
				],
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
						ApiBase::PARAM_REQUIRED => true,
					]
				]
			]
		];
	}

	/**
	 * execute
	 *
	 * @return void
	 */
	public function execute() {
		global $wgUser;

		$lookup = CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($wgUser, CentralIdLookup::AUDIENCE_RAW);

		$this->f = new Friendship($globalId);
		parent::execute();
	}

	/**
	 * Do Direct Req
	 *
	 * @return void
	 */
	protected function doDirectreq() {
		$targetUser = User::newFromName($this->getMain()->getVal('name'));
		if (!$targetUser) {
			$this->dieUsage(wfMessage('friendrequest-direct-notfound')->text(), 'friendrequest-direct-notfound');
		}
		$targetUser->load();
		if ($targetUser->isAnon()) {
			$this->dieUsage(wfMessage('friendrequest-direct-notfound')->text(), 'friendrequest-direct-notfound');
		}

		$lookup = CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($targetUser);
		if (!$globalId) {
			$this->dieUsage(wfMessage('friendrequest-direct-unmerged')->text(), 'friendrequest-direct-unmerged');
		}

		$result = $this->f->sendRequest($globalId);
		if (is_array($result) && isset($result['error'])) {
			$this->dieUsage(wfMessage($result['error'])->text(), $result['error']);
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
		$this->globalId = $this->getMain()->getVal('global_id');
		$result = $this->f->sendRequest($this->globalId);
		$html = FriendDisplay::friendButtons($this->globalId, true);
		$this->getResult()->addValue(null, 'result', $result);
		$this->getResult()->addValue(null, 'html', $html);
	}

	/**
	 * Do Confirm
	 *
	 * @return void
	 */
	protected function doConfirm() {
		$this->globalId = $this->getMain()->getVal('global_id');
		$result = $this->f->acceptRequest($this->globalId);
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
		$this->globalId = $this->getMain()->getVal('global_id');
		$rel = $this->f->getRelationship($this->globalId);
		$result = $this->f->ignoreRequest($this->globalId);
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
		$this->globalId = $this->getMain()->getVal('global_id');
		$result = $this->f->removeFriend($this->globalId);
		$html = FriendDisplay::friendButtons($this->globalId, true);
		$this->getResult()->addValue(null, 'result', $result);
		$this->getResult()->addValue(null, 'html', $html);
	}
}
