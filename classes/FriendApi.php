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
 * Class that allows friendship actions to be performed by AJAX calls.
 */
class FriendApi extends \CurseApiBase {

	public function getDescription() {
		return 'Allows friending actions to be taken.';
	}

	public function getParamDescription() {
		return [
			'do' => 'The friending action to be taken (send, confirm, ignore, remove)',
			'curse_id' => 'The user upon which the action should be taken',
			'name' => 'The username to be added as a friend',
			'token' => 'The edit token for the requesting user',
		];
	}

	public function getActions() {
		$basicAction = [
			'tokenRequired' => true,
			'postRequired' => true,
			'params' => [
				'curse_id' => [
					\ApiBase::PARAM_TYPE => 'string',
					\ApiBase::PARAM_REQUIRED => true,
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
						\ApiBase::PARAM_TYPE => 'string',
						\ApiBase::PARAM_REQUIRED => true,
					]
				]
			]
		];
	}

	public function execute() {
		global $wgUser;

		$wgUser->load();
		$curseUser = \CurseAuthUser::getInstance($wgUser);
		$this->f = new Friendship($curseUser->getId());
		parent::execute();
	}

	protected function doDirectreq() {
		$targetUser = \User::newFromName($this->getMain()->getVal('name'));
		if (!$targetUser) {
			$this->dieUsage(wfMessage('friendrequest-direct-notfound')->text(), 'friendrequest-direct-notfound');
		}
		$targetUser->load();
		if ($targetUser->isAnon()) {
			$this->dieUsage(wfMessage('friendrequest-direct-notfound')->text(), 'friendrequest-direct-notfound');
		}

		$curseTargetUser = \CurseAuthUser::getInstance($targetUser);
		if (!$curseTargetUser->getId()) {
			$this->dieUsage(wfMessage('friendrequest-direct-unmerged')->text(), 'friendrequest-direct-unmerged');
		}

		$result = $this->f->sendRequest($curseTargetUser->getId());
		if (!$result) {
			$this->dieUsage(wfMessage('friendrequestsend-error')->text(), 'friendrequestsend-error');
		}
		$html = wfMessage('friendrequest-direct-success')->text();
		$this->getResult()->addValue(null, 'result', $result);
		$this->getResult()->addValue(null, 'html', $html);
	}

	protected function doSend() {
		$this->curse_id = $this->getMain()->getVal('curse_id');
		$result = $this->f->sendRequest($this->curse_id);
		$html = FriendDisplay::friendButtons($this->curse_id, true);
		$this->getResult()->addValue(null, 'result', $result);
		$this->getResult()->addValue(null, 'html', $html);
	}

	protected function doConfirm() {
		$this->curse_id = $this->getMain()->getVal('curse_id');
		$result = $this->f->acceptRequest($this->curse_id);
		$html = wfMessage($result ? 'alreadyfriends' : 'friendrequestconfirm-error')->plain();
		$this->getResult()->addValue(null, 'result', $result);
		$this->getResult()->addValue(null, 'html', $html);
	}

	protected function doIgnore() {
		$this->curse_id = $this->getMain()->getVal('curse_id');
		$rel = $this->f->getRelationship($this->curse_id);
		$result = $this->f->ignoreRequest($this->curse_id);
		if ($rel == Friendship::REQUEST_RECEIVED) {
			$this->getResult()->addValue(null, 'remove', true);
		}
		$this->getResult()->addValue(null, 'result', $result);
	}

	protected function doRemove() {
		$this->curse_id = $this->getMain()->getVal('curse_id');
		$result = $this->f->removeFriend($this->curse_id);
		$html = FriendDisplay::friendButtons($this->curse_id, true);
		$this->getResult()->addValue(null, 'result', $result);
		$this->getResult()->addValue(null, 'html', $html);
	}
}
