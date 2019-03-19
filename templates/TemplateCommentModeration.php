<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @package   CurseProfile
 * @author    Noah Manneschmidt
 * @copyright (c) 2015 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki
**/

use CurseProfile\CommentReport;
use CurseProfile\CP;
use CurseProfile\ProfilePage;
use DynamicSettings\Environment;
use DynamicSettings\Wiki;

class TemplateCommentModeration {
	// Max number of small reporter avatars to display above a comment
	const MAX_REPORTER_AVATARS = 3;

	/**
	 * Renders the group and sort "tabs" at the top of the CommentModeration page
	 *
	 * @param  currentStyle $currentStyle string indicating the current sort style
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
		$html = '';

		foreach ($styles as $key => $sort) {
			if (!empty($html)) {
				$html .= ' | ';
			}
			$params = [];
			if (isset($sort[1])) {
				$title = SpecialPage::getTitleFor('CommentModeration');
			} else {
				$title = SpecialPage::getTitleFor('CommentModeration/' . $key);
			}
			$params['href'] = $title->getLocalUrl();
			if ($currentStyle == $key) {
				$params['style'] = 'font-weight: bold;';
			}
			$html .= Html::element('a', $params, $sort[0]);
		}

		return '<p>' . wfMessage('commentmoderation-view')->text() . ': ' . $html . '</p>';
	}

	/**
	 * Renders the main body of the CommentModeration special page
	 *
	 * @access public
	 * @param  array $reports CommentReport instances.
	 * @return string	HTML fragment
	 */
	public function renderComments($reports) {
		$html = '
				<div id="commentmoderation" class="comments">';

		$lookup = CentralIdLookup::factory();

		foreach ($reports as $report) {
			$rep = $report->data;
			$author = $lookup->localUserFromCentralId($rep['comment']['author']);
			if ($author) { // handle failures where central ID doesn't exist on local wiki
				$html .= '
					<div class="report-item" data-key="' . $report->reportKey() . '">';

				$html .= Html::rawElement('p', [], $this->itemLine($rep));
				$html .= '
						<div class="reported-comment">
							<div class="commentdisplay">
								<div class="avatar">' . ProfilePage::userAvatar(null, 48, $author->getEmail(), $author->getName())[0] . '</div>
								<div><div class="right">' . $this->permalink($rep) . '</div>' . CP::userLink($author) . '</div>
								<div class="commentbody">' . htmlspecialchars($rep['comment']['text']) . '</div>
							</div>';

				if ($report->data['action_taken'] == CommentReport::ACTION_NONE) {
					$html .= '
							<div class="moderation-actions">
								<div class="actions"><a class="del">' . wfMessage('commentmoderation-delete')->text() . '</a> <a class="dis">' . wfMessage('commentmoderation-dismiss')->text() . '</a></div>
								<div class="confirm"><a></a></div>
							</div>';
				} else {
					$html .= '
							<div class="moderation-actions">' . $this->actionTaken($report) . '</div>';
				}

				$html .= '
						</div>';

				$html .= '
					</div>';
			}
		}

		$html .= '
				</div>';

		return $html;
	}

	private function actionTaken($rep) {
		$lookup = CentralIdLookup::factory();
		$user = $lookup->localUserFromCentralId($rep->data['action_taken_by']);
		switch ($rep->data['action_taken']) {
			case CommentReport::ACTION_DISMISS:
				$action = 'dis';
			break;

			case CommentReport::ACTION_DELETE:
				$action = 'del';
			break;
		}
		return Html::rawElement('span', ['class' => 'action-taken ' . $action], wfMessage('report-actiontaken-' . $action, $user->getName())->text() . ' ' . CP::timeTag($rep->data['action_taken_at']));
	}

	/**
	 * Produces the introduction line above a reported comment "First reporteded X time ago by [user]:"
	 *
	 * @access private
	 * @param  rep    array CommentReport data
	 * @return string HTML fragment
	 */
	private function itemLine($rep) {
		if (count($rep['reports']) <= self::MAX_REPORTER_AVATARS) {
			return wfMessage('commentmoderation-item', CP::timeTag($rep['first_reported']), $this->reporterIcons($rep['reports']))->text();
		} else {
			return wfMessage('commentmoderation-item-andothers', CP::timeTag($rep['first_reported']), $this->reporterIcons($rep['reports']), count($rep['reports']) - self::MAX_REPORTER_AVATARS)->text();
		}
	}

	/**
	 * Creates the small user icons indicating who has reported a comment
	 *
	 * @access private
	 * @param  array	Array of users reporting: {reporter: CURSE_ID, timestamp: UTC_TIME}
	 * @return string	HTML fragment
	 */
	private function reporterIcons($reports) {
		$html = '';
		$iter = 0;
		$lookup = CentralIdLookup::factory();
		foreach ($reports as $rep) {
			$reporter = $lookup->localUserFromCentralId($rep['reporter']);
			$title = htmlspecialchars($reporter->getName(), ENT_QUOTES);
			$html .= Html::rawElement(
				'a',
				['href' => $reporter->getUserPage()->getLinkURL()],
				ProfilePage::userAvatar(null, 24, $reporter->getEmail(), $reporter->getName(), "title='$title'")[0]
			);
			$iter += 1;
			if ($iter >= self::MAX_REPORTER_AVATARS) {
				break;
			}
		}
		return $html;
	}

	/**
	 * Returns a permalink to a comment on its origin wiki.
	 *
	 * @access private
	 * @param  object	CommentReport instance
	 * @return string	HTML fragment
	 */
	private function permalink($rep) {
		if (Environment::isMasterWiki()) {
			$wiki = Wiki::loadFromHash($rep['comment']['origin_wiki']);
			if ($rep['comment']['origin_wiki'] == 'master') {
				global $wgSitename;
				$wikiName = $wgSitename;
				$url = SpecialPage::getTitleFor('CommentModeration/' . $rep['comment']['cid'])->getFullUrl();
			} elseif ($wiki !== false) {
				$domain = $wiki->getDomains()->getDomain();
				$wikiName = $wiki->getNameForDisplay();
				if (!isset($domain) || !isset($wikiName)) {
					return '';
				}
				$url = wfExpandUrl('https://' . $domain . '/Special:CommentPermalink/' . $rep['comment']['cid']);
			}
			return 'content as posted ' . Html::rawElement('a', ['href' => $url], CP::timeTag($rep['comment']['last_touched']) . ' on ' . $wikiName);
		} else {
			return CP::timeTag($rep['comment']['last_touched']);
		}
	}
}
