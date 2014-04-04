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
 * Class that allows commenting actions to be performed by AJAX calls.
 */
class CommentApi extends \ApiBase {
	public function getDescription() {
		return 'Allows commenting actions to be taken.';
	}

	public function getParamDescription() {
		return [
			'comment_action' => 'The commenting action to be taken (remove)',
			'comment_id' => 'The the ID of a comment which is being acted upon. Required for remove actions.',
			'token' => 'The edit token for the requesting user',
		];
	}

	public function getAllowedParams() {
		return [
			'comment_action' => [
				\ApiBase::PARAM_TYPE => 'string',
				\ApiBase::PARAM_REQUIRED => true,
			],
			'comment_id' => [
				\ApiBase::PARAM_TYPE => 'integer',
				\ApiBase::PARAM_REQUIRED => true, # will become optional when other actions are available
			],
			'token' => [
				\ApiBase::PARAM_TYPE => 'string',
				\ApiBase::PARAM_REQUIRED => true,
			],
		];
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

	public function execute() {
		$action = $this->getMain()->getVal('comment_action');
		$comment_id = $this->getMain()->getVal('comment_id');

		if (!in_array($action, ['remove'])) {
			return $this->dieUsageMsg(['comment-invalidaction', $action]);
		}

		switch ($action) {
			case 'remove':
			if ($comment_id && CommentBoard::canRemove($comment_id)) {
				CommentBoard::removeComment($comment_id);
				$this->getResult()->addValue(null, 'result', 'success');
				$this->getResult()->addValue(null, 'html', wfMessage('comment-adminremoved'));
			} else {
				return $this->dieUsageMsg(['comment-invalidaction', $action]);
			}
			break;
		}
	}
}
