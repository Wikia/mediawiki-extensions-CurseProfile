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
class FriendApi extends \ApiBase {
	public function execute() {
		$action = $this->getMain()->getVal('friendship_action');
		$curse_id = $this->getMain()->getVal('curse_id');

		global $wgUser;
		if (!in_array($action, ['send','confirm','ignore','remove'])) {
			return $this->dieUsageMsg(['friendship-invalidaction', $action]);
		}

		$wgUser->load();
		$f = new Friendship($wgUser->curse_id);
		switch ($action) {
			case 'send':
			$result = $f->sendRequest($curse_id);
			$html = FriendDisplay::friendButtons($curse_id, true);
			break;

			case 'confirm':
			$result = $f->acceptRequest($curse_id);
			$html = wfMessage($result ? 'alreadyfriends' : 'friendrequestconfirm-error')->plain();
			break;

			case 'ignore':
			$rel = $f->getRelationship($curse_id);
			$result = $f->ignoreRequest($curse_id);
			if ($rel == Friendship::REQUEST_RECEIVED) {
				$this->getResult()->addValue(null, 'remove', true);
			}
			$html = '';
			break;

			case 'remove':
			$result = $f->removeFriend($curse_id);
			$html = FriendDisplay::friendButtons($curse_id, true);
			break;
		}

		$this->getResult()->addValue(null, 'result', $result);
		$this->getResult()->addValue(null, 'html', $html);
	}

	public function getAllowedParams() {
		return [
			'friendship_action' => [
				\ApiBase::PARAM_TYPE => 'string',
				\ApiBase::PARAM_REQUIRED => true,
			],
			'curse_id' => [
				\ApiBase::PARAM_TYPE => 'string',
				\ApiBase::PARAM_REQUIRED => true,
			],
			'token' => [
				\ApiBase::PARAM_TYPE => 'string',
				\ApiBase::PARAM_REQUIRED => true,
			],
		];
	}

	public function getParamDescription() {
		return [
			'friendship_action' => 'The friending action to be taken (send, confirm, ignore, remove)',
			'curse_id' => 'The user upon which the action should be taken',
			'token' => 'The edit token for the requesting user',
		];
	}

	public function getDescription() {
		return 'Allows friending actions to be taken.';
	}

	public function needsToken() {
		return true;
	}

	public function getTokenSalt() {
		return '';
	}

	public function mustBePosted() {
		return true;
	}
}
