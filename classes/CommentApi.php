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
class CommentApi extends \CurseApiBase {
	public function getDescription() {
		return 'Allows commenting actions to be taken.';
	}

	public function getParamDescription() {
		return [
			// universal
			'do' => 'The commenting action to be taken (remove or add)',
			'token' => 'The edit token for the requesting user',

			// remove
			'comment_id' => 'The the ID of a comment which is being acted upon. Required for remove actions.',

			// add
			'curse_id' => 'The id for a user on whose board a comment will be added',
			'user_id' => 'The optional id for a user on whose board a comment will be added. curse_id is ignored when this is present.',
			'text' => 'The content of the comment to be added',
			'inReplyTo' => 'An OPTIONAL id of a comment that the new comment will reply to',
		];
	}

	public function getActions() {
		return [
			'remove' => [
				'tokenRequired' => true,
				'postRequired' => true,
				'params' => [
					'comment_id' => [
						\ApiBase::PARAM_TYPE => 'integer',
						\ApiBase::PARAM_REQUIRED => true,
					],
				],
			],

			'add' => [
				'tokenRequired' => true,
				'postRequired' => true,
				'params' => [
					'curse_id' => [
						\ApiBase::PARAM_TYPE => 'integer',
						\ApiBase::PARAM_REQUIRED => true,
					],
					'user_id' => [
						\ApiBase::PARAM_TYPE => 'integer',
						\ApiBase::PARAM_DFLT => 0,
					],
					'text' => [
						\ApiBase::PARAM_TYPE => 'string',
						\ApiBase::PARAM_REQUIRED => true,
					],
					'inReplyTo' => [
						\ApiBase::PARAM_TYPE => 'integer',
						\ApiBase::PARAM_DFLT => 0,
					],
				]
			]
		];
	}

	public function doAdd() {
		// intentional use of value returned from assignment
		if (! ($toUser = $this->getMain()->getVal('user_id')) ) {
			$toUser = CP::userIDfromCurseID($this->getMain()->getVal('curse_id'));
		}
		$text = $this->getMain()->getVal('text');
		$inreply = $this->getMain()->getVal('inReplyTo');

		$board = new CommentBoard($toUser);
		$board->addComment($text, null, $inreply);

		// TODO: should probably change CommentBoard::addComment to indicate failure or succes
		// so that this api call isn't hard-coded to always return success assuming params validate
		$this->getResult()->addValue(null, 'result', 'success');
	}

	public function doRemove() {
		$comment_id = $this->getMain()->getVal('comment_id');
		if ($comment_id && CommentBoard::canRemove($comment_id)) {
			CommentBoard::removeComment($comment_id);
			$this->getResult()->addValue(null, 'result', 'success');
			$this->getResult()->addValue(null, 'html', wfMessage('comment-adminremoved'));
		} else {
			return $this->dieUsageMsg(['comment-invalidaction', $action]);
		}
	}
}
