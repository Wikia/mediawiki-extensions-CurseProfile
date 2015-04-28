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
namespace CurseProfile;

class SpecialCommentModeration extends \SpecialPage {
	public function __construct() {
		parent::__construct( 'CommentModeration', 'profile-modcomments' );
	}

	private $sortStyle;

	/**
	 * Show the special page
	 *
	 * @param $params Mixed: parameter(s) passed to the page or null
	 */
	public function execute( $sortBy ) {
		$this->checkPermissions();
		$wgRequest = $this->getRequest();
		$wgOut = $this->getOutput();
		$wgOut->setPageTitle(wfMessage('commentmoderation-title')->plain());
		$wgOut->addModules('ext.curseprofile.commentmoderation');
		$wgOut->addModules('ext.curse.pagination');
		$mouse = CP::loadMouse(['output' => 'mouseOutputOutput']);
		$mouse->output->addTemplateFolder(dirname(dirname(__DIR__)).'/templates');
		$mouse->output->loadTemplate('commentmoderation');
		$this->setHeaders();

		$this->sortStyle = $sortBy;
		if (!$this->sortStyle) {
			$this->sortStyle = 'byVolume';
		}

		$start = $wgRequest->getInt('st');
		$itemsPerPage = 25;

		$total = $this->countModQueue();

		if (!$total) {
			$wgOut->addWikiMsg('commentmoderation-empty');
			return;
		} else {
			$content = $mouse->output->commentmoderation->renderComments(CommentReport::getReports($this->sortStyle, $itemsPerPage, $start));
		}

		$pagination = $mouse->output->generatePagination($total, $itemsPerPage, $start);
		$pagination = $mouse->output->paginationTemplate($pagination);

		$wgOut->addHTML($mouse->output->commentmoderation->sortStyleSelector($this->sortStyle));
		$wgOut->addHTML($pagination);
		$wgOut->addHTML($content);
		$wgOut->addHTML($pagination);
	}

	private function countModQueue() {
		// TODO: pass extra param for byWiki or byUser
		return CommentReport::getCount($this->sortStyle);
	}
}
