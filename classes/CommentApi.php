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
use ApiMain;
use CentralIdLookup;
use DerivativeRequest;
use HydraApiBase;

/**
 * Class that allows commenting actions to be performed by AJAX calls.
 */
class CommentApi extends HydraApiBase {
	/**
	 * Get Description
	 *
	 * @return string
	 */
	public function getDescription() {
		return 'Allows commenting actions to be taken.';
	}

	/**
	 * Get Param Description
	 *
	 * @return array
	 */
	public function getParamDescription() {
		return array_merge(parent::getParamDescription(), [
			// getRaw, edit, remove, and restore
			'comment_id' => 'The the ID of a comment which is being acted upon. Required for remove actions.',

			// resolveReport
			'reportKey' => 'The unique report key identifying an instance of a comment. "{sitemd5key}:{comment_id}:{edit_timestamp}"',
			'byUser' => 'The curse ID of the acting user. Defaults to the user currently logged in.',
			'withAction' => 'One of "delete" or "dismiss".',

			// add
			'global_id' => 'The id for a user on whose board a comment will be added',
			'user_id' => 'The optional id for a user on whose board a comment will be added.  global_id is ignored when this is present.',
			'text' => 'The content of the comment to be added',
			'inReplyTo' => 'An OPTIONAL id of a comment that the new comment will reply to',

			// addToDefault
			// all the params for add plus:
			'title' => 'The headline to use when posting a new section to the user\'s talk page',
		]);
	}

	/**
	 * Get Actions
	 *
	 * @return array
	 */
	public function getActions() {
		return [
			'restore' => [
				'tokenRequired' => true,
				'postRequired' => true,
				'params' => [
					'comment_id' => [
						ApiBase::PARAM_TYPE => 'integer',
						ApiBase::PARAM_REQUIRED => true,
					],
				],
			],

			'remove' => [
				'tokenRequired' => true,
				'postRequired' => true,
				'params' => [
					'comment_id' => [
						ApiBase::PARAM_TYPE => 'integer',
						ApiBase::PARAM_REQUIRED => true,
					],
				],
			],

			'purge' => [
				'tokenRequired' => true,
				'postRequired' => true,
				'params' => [
					'comment_id' => [
						ApiBase::PARAM_TYPE => 'integer',
						ApiBase::PARAM_REQUIRED => true,
					],
				],
			],

			'add' => [
				'tokenRequired' => true,
				'postRequired' => true,
				'params' => [
					'global_id' => [
						ApiBase::PARAM_TYPE => 'integer',
						ApiBase::PARAM_REQUIRED => true,
					],
					'user_id' => [
						ApiBase::PARAM_TYPE => 'integer',
						ApiBase::PARAM_DFLT => 0,
					],
					'text' => [
						ApiBase::PARAM_TYPE => 'string',
						ApiBase::PARAM_REQUIRED => true,
					],
					'inReplyTo' => [
						ApiBase::PARAM_TYPE => 'integer',
						ApiBase::PARAM_DFLT => 0,
					],
				]
			],

			'getReplies' => [
				'tokenRequired' => false,
				'postRequired' => false,
				'params' => [
					'comment_id' => [
						ApiBase::PARAM_TYPE => 'integer',
						ApiBase::PARAM_REQUIRED => true,
					],
				],
			],

			'getRaw' => [
				'tokenRequired' => false,
				'postRequired' => false,
				'params' => [
					'comment_id' => [
						ApiBase::PARAM_TYPE => 'integer',
						ApiBase::PARAM_REQUIRED => true,
					],
				],
			],

			'edit' => [
				'tokenRequired' => true,
				'postRequired' => true,
				'params' => [
					'comment_id' => [
						ApiBase::PARAM_TYPE => 'integer',
						ApiBase::PARAM_REQUIRED => true,
					],
					'text' => [
						ApiBase::PARAM_TYPE => 'string',
						ApiBase::PARAM_REQUIRED => true,
					],
				],
			],

			'addToDefault' => [
				'tokenRequired' => true,
				'postRequired' => true,
				'params' => [
					'global_id' => [
						ApiBase::PARAM_TYPE => 'integer',
						ApiBase::PARAM_REQUIRED => true,
					],
					'user_id' => [
						ApiBase::PARAM_TYPE => 'integer',
						ApiBase::PARAM_DFLT => 0,
					],
					'title' => [
						ApiBase::PARAM_TYPE => 'string',
						ApiBase::PARAM_REQUIRED => true,
					],
					'text' => [
						ApiBase::PARAM_TYPE => 'string',
						ApiBase::PARAM_REQUIRED => true,
					],
					'inReplyTo' => [
						ApiBase::PARAM_TYPE => 'integer',
						ApiBase::PARAM_DFLT => 0,
					],
				]
			],

			'report' => [
				'tokenRequired' => true,
				'postRequired' => true,
				'params' => [
					'comment_id' => [
						ApiBase::PARAM_TYPE => 'integer',
						ApiBase::PARAM_REQUIRED => true,
					],
				]
			],

			'resolveReport' => [
				'tokenRequired' => true,
				'postRequired' => true,
				'permissionRequired' => 'profile-moderate',
				'params' => [
					'reportKey' => [
						ApiBase::PARAM_TYPE => 'string',
						ApiBase::PARAM_REQUIRED => true,
					],
					'byUser' => [
						ApiBase::PARAM_TYPE => 'integer',
					],
					'withAction' => [ // string param with two possible enumerated values:
						ApiBase::PARAM_TYPE => ['delete', 'dismiss'],
						ApiBase::PARAM_REQUIRED => true,
					],
				]
			],
		];
	}

	/**
	 * Adds a comment to a user's Curse Profile page or adds a new section on their talk page,
	 * depending on what the user has chosen as their default user page.
	 */
	public function doAddToDefault() {
		// intentional use of value returned from assignment
		if (!($user_id = $this->getMain()->getVal('user_id'))) {
			$lookup = CentralIdLookup::factory();
			$user = $lookup->localUserFromCentralId($this->getMain()->getVal('global_id'));
			if ($user->isAnon()) {
				return $this->dieUsageMsg(['comment-invaliduser']);
			}
		}
		$text = $this->getMain()->getVal('text');
		$inreply = $this->getMain()->getVal('inReplyTo');

		if ($user->getIntOption('comment-pref')) {
			$board = new CommentBoard($user_id);
			$commentSuccess = $board->addComment($text, null, $inreply);
			$this->getResult()->addValue(null, 'result', ($commentSuccess ? 'success' : 'failure'));
		} else {
			// the recommended way of editing a local article was with WikiPage::doEditContent
			// however there didn't seem to be an easy way to add a section rather than editing the entire content
			$params = new DerivativeRequest(
				$this->getRequest(),
				[
					'title' => 'User_talk:' . $user->getName(),
					'action' => 'edit',
					'section' => 'new',
					'summary' => $this->getMain()->getVal('title'),
					'text' => $text,
					'token' => $this->getMain()->getVal('token'),
				]
			);
			$api = new ApiMain($params, true);
			$api->execute();
			// TODO: check the result object from the internal API call to determine success/failure status
			$this->getResult()->addValue(null, 'result', 'success');
		}
	}

	/**
	 * Adds a new comment to a user's comment board on their Curse Profile page
	 */
	public function doAdd() {
		$toUser = $this->getMain()->getVal('user_id');
		if (!$toUser) {
			$lookup = CentralIdLookup::factory();
			$user = $lookup->localUserFromCentralId($this->getMain()->getVal('global_id'));
			if (!$user) {
				$this->getResult()->addValue(null, 'result', 'failure');
				return;
			}
			$toUser = $user->getId();
		}
		$text = $this->getMain()->getVal('text');
		$inreply = $this->getMain()->getVal('inReplyTo');

		$board = new CommentBoard($toUser);
		$commentSuccess = $board->addComment($text, null, $inreply);

		$this->getResult()->addValue(null, 'result', ($commentSuccess ? 'success' : 'failure'));
	}

	/**
	 * Returns all replies to a specific comment
	 */
	public function doGetReplies() {
		$replies = CommentDisplay::repliesTo($this->getMain()->getVal('user_id'), $this->getMain()->getVal('comment_id'));
		$this->getResult()->addValue(null, 'html', $replies);
	}

	public function doGetRaw() {
		$comment = CommentBoard::getCommentById($this->getMain()->getVal('comment_id'), false);
		$this->getResult()->addValue(null, 'text', (isset($comment[0]['ub_message']) ? $comment[0]['ub_message'] : ''));
	}

	public function doEdit() {
		$commentId = $this->getMain()->getVal('comment_id');
		$text = $this->getMain()->getVal('text');
		if ($commentId && CommentBoard::canEdit($commentId)) {
			$res = CommentBoard::editComment($commentId, $text);
			$this->getResult()->addValue(null, 'result', 'success');
			// add parsed text to result
			global $wgOut;
			$this->getResult()->addValue(null, 'parsedContent', $wgOut->parse($text));
		} else {
			$this->dieUsageMsg(['comment-invalidaction']);
		}
	}

	public function doRestore() {
		$commentId = $this->getMain()->getVal('comment_id');
		if ($commentId && CommentBoard::canRestore($commentId)) {
			CommentBoard::restoreComment($commentId);
			$this->getResult()->addValue(null, 'result', 'success');
			$this->getResult()->addValue(null, 'html', wfMessage('comment-adminremoved'));
		} else {
			return $this->dieUsageMsg(['comment-invalidaction']);
		}
	}

	public function doRemove() {
		$commentId = $this->getMain()->getVal('comment_id');
		if ($commentId && CommentBoard::canRemove($commentId)) {
			CommentBoard::removeComment($commentId);
			$this->getResult()->addValue(null, 'result', 'success');
			$this->getResult()->addValue(null, 'html', wfMessage('comment-adminremoved'));
		} else {
			return $this->dieUsageMsg(['comment-invalidaction']);
		}
	}

	public function doPurge() {
		$commentId = $this->getMain()->getVal('comment_id');
		if ($commentId && CommentBoard::canPurge()) {
			CommentBoard::purgeComment($commentId);
			$this->getResult()->addValue(null, 'result', 'success');
		} else {
			return $this->dieUsageMsg(['comment-invalidaction']);
		}
	}

	public function doReport() {
		$commentId = $this->getMain()->getVal('comment_id');
		if ($commentId) {
			$result = CommentBoard::reportComment($commentId);
			$this->getResult()->addValue(null, 'result', $result ? 'success' : 'error');
		} else {
			return $this->dieUsageMsg(['comment-invalidaction']);
		}
	}

	/**
	 * Resolve Report API End Point
	 *
	 * @access public
	 * @return boolean	Success
	 */
	public function doResolveReport() {
		$lookup = CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($this->getUser(), CentralIdLookup::AUDIENCE_RAW);
		if (!$globalId) {
			return false;
		}

		$reportKey = $this->getMain()->getVal('reportKey');
		$jobArgs = [
			'reportKey' => $reportKey,
			'action' => $this->getMain()->getVal('withAction'),
			'byUser' => $this->getMain()->getVal('byUser', $globalId),
		];

		// if not dealing with a comment originating here, dispatch it off to the origin wiki
		if (CommentReport::keyIsLocal($reportKey)) {
			$output = ResolveComment::run($jobArgs, true, $result);
			$this->getResult()->addValue(null, 'result', ($result == 0 ? 'success' : 'error'));
			$this->getResult()->addValue(null, 'output', explode("\n", trim($output)));
		} else {
			ResolveComment::queue($jobArgs);
			$this->getResult()->addValue(null, 'result', 'queued');
		}
		return true;
	}
}
