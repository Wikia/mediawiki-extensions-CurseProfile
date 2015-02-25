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
			// getRaw, edit, and remove
			'comment_id' => 'The the ID of a comment which is being acted upon. Required for remove actions.',

			// resolveReport
			'reportKey' => 'The unique report key identifying an instance of a comment. "{sitemd5key}:{comment_id}:{edit_timestamp}"',
			'byUser' => 'The curse ID of the acting user. Defaults to the user currently logged in.',
			'withAction' => 'One of "delete" or "dismiss".',

			// add
			'curse_id' => 'The id for a user on whose board a comment will be added',
			'user_id' => 'The optional id for a user on whose board a comment will be added. curse_id is ignored when this is present.',
			'text' => 'The content of the comment to be added',
			'inReplyTo' => 'An OPTIONAL id of a comment that the new comment will reply to',

			// addToDefault
			// all the params for add plus:
			'title' => 'The headline to use when posting a new section to the user\'s talk page',
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

			'getRaw' => [
				'tokenRequired' => false,
				'postRequired' => false,
				'params' => [
					'comment_id' => [
						\ApiBase::PARAM_TYPE => 'integer',
						\ApiBase::PARAM_REQUIRED => true,
					],
				],
			],

			'edit' => [
				'tokenRequired' => true,
				'postRequired' => true,
				'params' => [
					'comment_id' => [
						\ApiBase::PARAM_TYPE => 'integer',
						\ApiBase::PARAM_REQUIRED => true,
					],
					'text' => [
						\ApiBase::PARAM_TYPE => 'string',
						\ApiBase::PARAM_REQUIRED => true,
					],
				],
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
			],

			'report' => [
				'tokenRequired' => true,
				'postRequired' => true,
				'params' => [
					'comment_id' => [
						\ApiBase::PARAM_TYPE => 'integer',
						\ApiBase::PARAM_REQUIRED => true,
					]
				]
			],

			'resolveReport' => [
				'tokenRequired' => true,
				'postRequired' => false,
				'permissionRequired' => 'profile-modcomments',
				'params' => [
					'reportKey' => [
						\ApiBase::PARAM_TYPE => 'string',
						\ApiBase::PARAM_REQUIRED => true,
					],
					'byUser' => [
						\ApiBase::PARAM_TYPE => 'integer',
					],
					'withAction' => [ // string param with two possible enumerated values:
						\ApiBase::PARAM_TYPE => ['delete', 'dismiss'],
						\ApiBase::PARAM_REQUIRED => true,
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
			$this->getResult()->addValue(null, 'result', ($commentSuccess ? 'success' : 'failure'));
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
			// TODO: check the result object from the internal API call to determine success/failure status
			$this->getResult()->addValue(null, 'result', 'success');
		}
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

		$this->getResult()->addValue(null, 'result', ($commentSuccess ? 'success' : 'failure'));
	}

	public function doGetRaw() {
		$comment = CommentBoard::getCommentById($this->getMain()->getVal('comment_id'), false);
		$this->getResult()->addValue(null, 'text', ( isset($comment[0]['ub_message']) ? $comment[0]['ub_message'] : ''));
	}

	public function doEdit() {
		$comment_id = $this->getMain()->getVal('comment_id');
		$text = $this->getMain()->getVal('text');
		if ($comment_id && CommentBoard::canEdit($comment_id)) {
			$res = CommentBoard::editComment($comment_id, $text);
			$this->getResult()->addValue(null, 'result', 'success');
			// add parsed text to result
			global $wgOut;
			$this->getResult()->addValue(null, 'parsedContent', $wgOut->parseInline($text));
		} else {
			$this->dieUsageMsg(['comment-invalidaction']);
		}
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

	public function doReport() {
		$comment_id = $this->getMain()->getVal('comment_id');
		if ($comment_id) {
			$res = CommentBoard::reportComment($comment_id);
			$this->getResult()->addValue(null, 'result', $res ? 'success' : 'error');
		} else {
			return $this->dieUsageMsg(['comment-invalidaction']);
		}
	}

	public function doResolveReport() {
		$reportKey = $this->getMain()->getVal('reportKey');
		$jobArgs = [
			'reportKey' => $reportKey,
			'action' => $this->getMain()->getVal('withAction'),
			'byUser' => $this->getMain()->getVal('byUser', $this->getUser()->curse_id),
		];

		// if not dealing with a comment originating here, dispatch it off to the origin wiki
		if (CommentReport::keyIsLocal($reportKey)) {
			$output = ResolveComment::run($jobArgs, true, $result);
			$this->getResult()->addValue(null, 'result', $result==0 ? 'success' : 'error');
			$this->getResult()->addValue(null, 'output', explode("\n",trim($output)));
		} else {
			ResolveComment::queue($jobArgs);
			$this->getResult()->addValue(null, 'result', 'queued');
		}
	}
}
