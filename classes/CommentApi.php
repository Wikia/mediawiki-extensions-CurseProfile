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
		return array_merge(parent::getParamDescription(), [
			// remove
			'comment_id' => 'The the ID of a comment which is being acted upon. Required for remove actions.',

			// add
			'curse_id' => 'The id for a user on whose board a comment will be added',
			'user_id' => 'The optional id for a user on whose board a comment will be added. curse_id is ignored when this is present.',
			'text' => 'The content of the comment to be added',
			'inReplyTo' => 'An OPTIONAL id of a comment that the new comment will reply to',

			// addToDefault
			// all the params for add plus:
			'title' => 'The headline to use when posting a new section to the user\' talk page',
		]);
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
			],

			'addToDefault' => [
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
					'title' => [
						\ApiBase::PARAM_TYPE => 'string',
						\ApiBase::PARAM_REQUIRED => true,
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

	/**
	 * Adds a comment to a user's Curse Profile page or adds a new section on their talk page,
	 * depending on what the user has chosen as their default user page.
	 */
	public function doAddToDefault() {
		// intentional use of value returned from assignment
		if (! ($user_id = $this->getMain()->getVal('user_id')) ) {
			$user_id = CP::userIDfromCurseID($this->getMain()->getVal('curse_id'));
			$user = \User::newFromId($user_id);
			$user->load();
			if ($user->isAnon()) {
				return $this->dieUsageMsg(['comment-invaliduser']);
			}
		}
		$text = $this->getMain()->getVal('text');
		$inreply = $this->getMain()->getVal('inReplyTo');

		if ($user->getIntOption('profile-pref')) {
			$board = new CommentBoard($user_id);
			$commentSuccess = $board->addComment($text, null, $inreply);
		} else {
			// the recommended way of editing a local article was with WikiPage::doEditContent
			// however there didn't seem to be an easy way to add a section rather than editing the entire content
			$params = new \DerivativeRequest($this->getRequest(),
				[
					'title' => 'User_talk:'.$user->getName(),
					'action' => 'edit',
					'section' => 'new',
					'summary' => $this->getMain()->getVal('title'),
					'text' => $text,
					'token' => $this->getMain()->getVal('token'),
				]
			);
			$api = new \ApiMain($params, true);
			$api->execute();
		}

		// TODO: should probably change CommentBoard::addComment to indicate failure or succes
		// so that this api call isn't hard-coded to always return success assuming params validate
		$this->getResult()->addValue(null, 'result', 'success');
	}

	/**
	 * Adds a new comment to a user's comment board on their Curse Profile page
	 */
	public function doAdd() {
		// intentional use of value returned from assignment
		if (! ($toUser = $this->getMain()->getVal('user_id')) ) {
			$toUser = CP::userIDfromCurseID($this->getMain()->getVal('curse_id'));
		}
		$text = $this->getMain()->getVal('text');
		$inreply = $this->getMain()->getVal('inReplyTo');

		$board = new CommentBoard($toUser);
		$commentSuccess = $board->addComment($text, null, $inreply);

		// TODO: should probably change CommentBoard::addComment to indicate failure or succes
		// so that this api call isn't hard-coded to always return success assuming params validate
		$this->getResult()->addValue(null, 'result', ($commentSuccess ? 'success' : 'failure'));
	}

	public function doRemove() {
		$comment_id = $this->getMain()->getVal('comment_id');
		if ($comment_id && CommentBoard::canRemove($comment_id)) {
			CommentBoard::removeComment($comment_id);
			$this->getResult()->addValue(null, 'result', 'success');
			$this->getResult()->addValue(null, 'html', wfMessage('comment-adminremoved'));
		} else {
			return $this->dieUsageMsg(['comment-invalidaction']);
		}
	}
}
