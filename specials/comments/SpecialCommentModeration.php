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
		parent::__construct( 'CommentModeration', 'profile-moderate' );
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
	public function execute( $sortBy ) {
		$this->checkPermissions();
		$request = $this->getRequest();
		$output = $this->getOutput();

		$output->setPageTitle( wfMessage( 'commentmoderation-title' )->escaped() );

		$output->addModuleStyles(
			[
				'ext.curseprofile.commentmoderation.styles',
				'ext.hydraCore.pagination.styles',
				'ext.curseprofile.comments.styles',
			]
		);
		$output->addModules( [ 'ext.curseprofile.commentmoderation.scripts' ] );

		$templateCommentModeration = new TemplateCommentModeration();
		$this->setHeaders();

		$this->sortStyle = $sortBy;
		if ( !$this->sortStyle ) {
			$this->sortStyle = 'byVolume';
		}

		$start = $request->getInt( 'st' );
		$itemsPerPage = 25;

		$reports = CommentReport::getReports( $this->sortStyle, $itemsPerPage, $start );

		if ( !count( $reports ) ) {
			$output->addWikiMsg( 'commentmoderation-empty' );
			return;
		} else {
			$content = $templateCommentModeration->renderComments( $reports );
		}

		$pagination = HydraCore::generatePaginationHtml(
			$this->getFullTitle(),
			count( $reports ),
			$itemsPerPage,
			$start
		);

		$output->addHTML( $templateCommentModeration->sortStyleSelector( $this->sortStyle ) );
		$output->addHTML( $pagination );
		$output->addHTML( $content );
		$output->addHTML( $pagination );
	}

	private function countModQueue() {
		// TODO: pass extra param for byWiki or byUser
		return CommentReport::getCount( $this->sortStyle );
	}
}
