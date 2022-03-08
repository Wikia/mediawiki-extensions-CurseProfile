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

use HydraApiBase;
use MediaWiki\MediaWikiServices;
use RequestContext;
use Wikimedia\ParamValidator\ParamValidator;

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

		$targetUser = MediaWikiServices::getInstance()->getUserFactory()->newFromName($this->getMain()->getVal('name'));
		if (!$targetUser) {
			$this->dieWithError(wfMessage('friendrequest-direct-notfound'), 'friendrequest-direct-notfound');
		}
		$targetUser->load();
		if ($targetUser->isAnon()) {
			$this->dieWithError(wfMessage('friendrequest-direct-notfound'), 'friendrequest-direct-notfound');
		}

		$result = $this->f->sendRequest($targetUser);
		if (is_array($result) && isset($result['error'])) {
			$this->dieWithError(wfMessage($result['error'])->params($targetUser->getName(), $wgUser->getName()), $result['error']);
		} elseif (!$result) {
			$this->dieWithError(wfMessage('friendrequestsend-error'), 'friendrequestsend-error');
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
		$userId = $this->getInt('user_id');
		$toUser = MediaWikiServices::getInstance()->getUserFactory()->newFromId($userId);
		$result = $this->f->sendRequest($toUser);
		$html = FriendDisplay::friendButtons($toUser, $this->getUser());
		$this->getResult()->addValue(null, 'result', $result);
		$this->getResult()->addValue(null, 'html', $html);
	}

	/**
	 * Do Confirm
	 *
	 * @return void
	 */
	protected function doConfirm() {
		$userId = $this->getInt('user_id');
		$toUser = MediaWikiServices::getInstance()->getUserFactory()->newFromId($userId);
		$result = $this->f->acceptRequest($toUser);
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
		$userId = $this->getInt('user_id');
		$toUser = MediaWikiServices::getInstance()->getUserFactory()->newFromId($userId);
		$rel = $this->f->getRelationship($toUser);
		$result = $this->f->ignoreRequest($toUser);
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
		$userId = $this->getInt('user_id');
		$toUser = MediaWikiServices::getInstance()->getUserFactory()->newFromId($userId);
		$result = $this->f->removeFriend($toUser);
		$html = FriendDisplay::friendButtons($toUser, $this->getUser());
		$this->getResult()->addValue(null, 'result', $result);
		$this->getResult()->addValue(null, 'html', $html);
	}
}
