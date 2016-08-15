<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @author		Noah Manneschmidt
 * @copyright	(c) 2015 Curse Inc.
 * @license		All Rights Reserved
 * @package		CurseProfile
 * @link		http://www.curse.com/
 *
**/
use CurseProfile\CP, CurseProfile\ProfilePage, CurseProfile\CommentReport;

class TemplateCommentModeration {
	// Max number of small reporter avatars to display above a comment
	const MAX_REPORTER_AVATARS = 3;

	/**
	 * Renders the group and sort "tabs" at the top of the CommentModeration page
	 * @param currentStyle string indicating the current sort style
	 * @return string HTML fragment
	 */
	public function sortStyleSelector($currentStyle) {
		$styles = [
			'byVolume' => ['By Volume of Reports', 'default'],
			// 'byWiki' => ['By Origin Wiki'],
			// 'byUser' => ['By Reported User'],
			// 'byDate' => ['Most Recent Reports First'],
			'byActionDate' => ['Moderation Log'],
		];
		$HTML = '';

		foreach ($styles as $key => $sort) {
			if (!empty($HTML)) {
				$HTML .= ' | ';
			}
			$params = [];
			if (isset($sort[1])) {
				$params['href'] = '/Special:CommentModeration';
			} else {
				$params['href'] = '/Special:CommentModeration/'.$key;
			}
			if ($currentStyle == $key) {
				$params['style'] = 'font-weight:bold';
				unset($params['href']);
			}
			$HTML .= Html::element('a', $params, $sort[0]);
		}

		return '<p>View: '.$HTML.'</p>';
	}

	/**
	 * Renders the main body of the CommentModeration special page
	 * @param reports array of CommentReport instances
	 * @return string HTML fragment
	 */
	public function renderComments($reports) {
		$HTML = '<div id="commentmoderation" class="comments">';

		foreach ($reports as $report) {
			$rep = $report->data;
			$author = CurseAuthUser::newUserFromGlobalId($rep['comment']['author']);
			$HTML .= '<div class="report-item" data-key="'.$report->reportKey().'">';

			$HTML .= Html::rawElement('p', [], $this->itemLine($rep));
			$HTML .= '<div class="reported-comment">';

			$HTML .= '<div class="commentdisplay">'.
						'<div class="avatar">'.ProfilePage::userAvatar($nothing, 48, $author->getEmail(), $author->getName())[0].'</div>'.
						'<div><div class="right">'.$this->permalink($rep).'</div>'.CP::userLink($author).'</div>'.
						'<div class="commentbody">'.htmlspecialchars($rep['comment']['text']).'</div>';
			$HTML .= '</div>';

			if ($report->data['action_taken'] == CommentReport::ACTION_NONE) {
				$HTML .= '<div class="moderation-actions">';
					$HTML .= '<div class="actions"><a class="del">Delete</a> <a class="dis">Dismiss</a></div>';
					$HTML .= '<div class="confirm"><a></a></div>';
				$HTML .= '</div>';
			} else {
				$HTML .= '<div class="moderation-actions">'.$this->actionTaken($report).'</div>';
			}

			$HTML .= '</div>';

			$HTML .= '</div>';
		}

		$HTML .= '</div>';

		return $HTML;
	}

	private function actionTaken($rep) {
		$user = CurseAuthUser::newUserFromGlobalId($rep->data['action_taken_by']);
		switch ($rep->data['action_taken']) {
			case CommentReport::ACTION_DISMISS:
			$action = 'dis';
			break;

			case CommentReport::ACTION_DELETE:
			$action = 'del';
			break;
		}
		return Html::rawElement('span', ['class'=>'action-taken '.$action], wfMessage('report-actiontaken-'.$action, $user->getName())->text().' '.CP::timeTag($rep->data['action_taken_at']));
	}

	/**
	 * Produces the introduction line above a reported comment "First reporteded X time ago by [user]:"
	 * @param  rep    array CommentReport data
	 * @return string HTML fragment
	 */
	private function itemLine($rep) {
		if (count($rep['reports']) <= self::MAX_REPORTER_AVATARS) {
			return sprintf(wfMessage('commentmoderation-item')->text(), CP::timeTag($rep['first_reported']), $this->reporterIcons($rep['reports']));
		} else {
			return sprintf(wfMessage('commentmoderation-item-andothers')->text(), CP::timeTag($rep['first_reported']), $this->reporterIcons($rep['reports']), count($rep['reports'])-self::MAX_REPORTER_AVATARS);
		}
	}

	/**
	 * Creates the small user icons indicating who has reported a comment
	 * @param reports array of users reporting: {reporter: CURSE_ID, timestamp: UTC_TIME}
	 * @return string HTML fragment
	 */
	private function reporterIcons($reports) {
		$HTML = '';
		$iter = 0;
		foreach ($reports as $rep) {
			$reporter = CurseAuthUser::newUserFromGlobalId($rep['reporter']);
			$title = htmlspecialchars($reporter->getName(), ENT_QUOTES);
			$HTML .= \Html::rawElement(
				'a', [
					'href' => $reporter->getUserPage()->getLinkURL()
				],
				ProfilePage::userAvatar($nothing, 24, $reporter->getEmail(), $reporter->getName(), "title='$title'")[0]
			);
			$iter += 1;
			if ($iter >= self::MAX_REPORTER_AVATARS) {
				break;
			}
		}
		return $HTML;
	}

	/**
	 * Returns a permalink to a comment on its origin wiki
	 * @param rep CommentReport instance
	 * @return string HTML fragment
	 */
	private function permalink($rep) {
		if (defined('MASTER_WIKI') && MASTER_WIKI === true) {
			if ($rep['comment']['origin_wiki'] == 'master') {
				$domain = $GLOBALS['wgServer'];
				$wikiName = $GLOBALS['wgSitename'];
			} else {
				$wiki = \DynamicSettings\Wiki::loadFromHash($rep['comment']['origin_wiki']);
				$domain = $wiki->getDomains()->getDomain();
				$wikiName = $wiki->getNameForDisplay();
			}
			return 'content as posted '.Html::rawElement('a', ['href'=>'http://'.$domain.'/Special:CommentPermalink/'.$rep['comment']['cid']], CP::timeTag($rep['comment']['last_touched']).' on '.$wikiName);
		} else {
			return CP::timeTag($rep['comment']['last_touched']);
		}
	}
}
