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
use CurseProfile\CP, CurseProfile\ProfilePage;

class skin_commentmoderation {
	const MAX_REPORTER_AVATARS = 3;

	public function sortStyleSelector($currentStyle) {
		$styles = [
			'byVolume' => ['By Volume of Reports', 'default'],
			'byWiki' => ['By Origin Wiki'],
			'byUser' => ['By Reported User'],
			'byDate' => ['Most Recent Reports First'],
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
				$params['href'] = '/Special:CommendModeration/'.$key;
			}
			if ($currentStyle == $key) {
				$params['style'] = 'font-weight:bold';
				unset($params['href']);
			}
			$HTML .= Html::element('a', $params, $sort[0]);
		}

		return '<p>Group and sort: '.$HTML.'</p>';
	}

	public function renderComments($reports) {
		$HTML = '<div id="commentmoderation" class="comments">';

		foreach ($reports as $rep) {
			$rep = $rep->data;
			$author = CurseUser::newFromCurseId($rep['comment']['author']);
			$HTML .= '<div class="report-item">';

			$HTML .= Html::rawElement('p', [], $this->itemLine($rep));
			$HTML .= '<div class="reported-comment">';

			$HTML .= '<div class="commentdisplay">'.
						'<div class="avatar">'.ProfilePage::userAvatar($nothing, 48, $author->getEmail(), $author->getName())[0].'</div>'.
						'<div><div class="right">'.$this->permalink($rep).'</div>'.CP::userLink($author).'</div>'.
						'<div class="commentbody">'.htmlspecialchars($rep['comment']['text']).'</div>';
			$HTML .= '</div>';
			$HTML .= '<div class="moderation-actions"><a class="del">Delete</a> <a class="dis">Dismiss</a></div>';

			$HTML .= '</div>';

			$HTML .= '</div>';
		}

		$HTML .= '</div>';

		return $HTML;
	}

	private function itemLine($rep) {
		if (count($rep['reports']) <= self::MAX_REPORTER_AVATARS) {
			return sprintf(wfMessage('commentmoderation-item')->text(), CP::timeTag($rep['reports'][0]['timestamp']), $this->reporterIcons($rep['reports']));
		} else {
			return sprintf(wfMessage('commentmoderation-item-andothers')->text(), CP::timeTag($rep['reports'][0]['timestamp']), $this->reporterIcons($rep['reports']), count($rep['reports'])-self::MAX_REPORTER_AVATARS);
		}
	}

	private function reporterIcons($reports) {
		$HTML = '';
		$iter = 0;
		foreach ($reports as $rep) {
			$reporter = CurseUser::newFromCurseId($rep['reporter']);
			$HTML .= ProfilePage::userAvatar($nothing, 24, $reporter->getEmail(), $reporter->getName())[0];
			$iter += 1;
			if ($iter >= self::MAX_REPORTER_AVATARS) {
				break;
			}
		}
		return $HTML;
	}

	private function permalink($rep) {
		if (defined('CURSEPROFILE_MASTER')) {
			$wiki = \DynamicSettings\Wiki::loadFromHash($rep['comment']['origin_wiki']);
			$domain = $wiki->getDomains()->getDomain();
			return 'content as posted '.Html::rawElement('a', ['href'=>'http://'.$domain.'/Special:CommentPermalink/'.$rep['comment']['cid']], CP::timeTag($rep['comment']['last_touched']).' on '.$wiki->getNameForDisplay());
		} else {
			return CP::timeTag($rep['comment']['last_touched']);
		}
	}
}
