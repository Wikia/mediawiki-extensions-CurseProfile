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

namespace CurseProfile;

use HydraCore;
use HydraCore\SpecialPage;
use TemplateCommentModeration;

class SpecialCommentModeration extends SpecialPage {
	private $sortStyle;

	public function __construct() {
		parent::__construct('CommentModeration', 'profile-moderate');
	}

	/**
	 * Return the group name for this special page.
	 *
	 * @return string
	 */
	protected function getGroupName() {
		return 'users';
	}

	/**
	 * Show the special page
	 *
	 * @param string $sortBy Mixed: parameter(s) passed to the page or null
	 */
	public function execute($sortBy) {
		$this->checkPermissions();
		$wgRequest = $this->getRequest();

		$this->output->setPageTitle(wfMessage('commentmoderation-title')->escaped());

		$this->output->addModuleStyles(['ext.curseprofile.commentmoderation.styles', 'ext.hydraCore.pagination.styles']);
		$this->output->addModules(['ext.curseprofile.commentmoderation.scripts']);

		$templateCommentModeration = new TemplateCommentModeration;
		$this->setHeaders();

		$this->sortStyle = $sortBy;
		if (!$this->sortStyle) {
			$this->sortStyle = 'byVolume';
		}

		$start = $wgRequest->getInt('st');
		$itemsPerPage = 25;

		$total = $this->countModQueue();

		if (!$total) {
			$this->output->addWikiMsg('commentmoderation-empty');
			return;
		} else {
			$content = $templateCommentModeration->renderComments(CommentReport::getReports($this->sortStyle, $itemsPerPage, $start));
		}

		$pagination = HydraCore::generatePaginationHtml($this->getFullTitle(), $total, $itemsPerPage, $start);

		$this->output->addHTML($templateCommentModeration->sortStyleSelector($this->sortStyle));
		$this->output->addHTML($pagination);
		$this->output->addHTML($content);
		$this->output->addHTML($pagination);
	}

	private function countModQueue() {
		// TODO: pass extra param for byWiki or byUser
		return CommentReport::getCount($this->sortStyle);
	}
}
