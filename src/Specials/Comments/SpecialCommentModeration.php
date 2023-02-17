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
 */

namespace CurseProfile\Specials\Comments;

use CurseProfile\Classes\CommentReport;
use CurseProfile\Templates\TemplateCommentModeration;
use HydraCore;
use SpecialPage;

class SpecialCommentModeration extends SpecialPage {
	public function __construct() {
		parent::__construct( 'CommentModeration', 'profile-comments-moderate' );
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'users';
	}

	/**
	 * @inheritDoc
	 *
	 * @param ?string $subPage sortStyle
	 */
	public function execute( $subPage ) {
		$this->checkPermissions();
		$output = $this->getOutput();

		$output->setPageTitle( $this->msg( 'commentmoderation-title' )->escaped() );

		$output->addModuleStyles( [
			'ext.curseprofile.commentmoderation.styles',
			'ext.hydraCore.pagination.styles',
			'ext.curseprofile.comments.styles'
		] );
		$output->addModules( [ 'ext.curseprofile.commentmoderation.scripts' ] );

		$templateCommentModeration = new TemplateCommentModeration();
		$this->setHeaders();

		$sortStyle = $subPage ?: 'byVolume';

		$start = $this->getRequest()->getInt( 'st' );
		$itemsPerPage = 25;

		$reports = CommentReport::getReports( $sortStyle, $itemsPerPage, $start );

		if ( !count( $reports ) ) {
			$output->addWikiMsg( 'commentmoderation-empty' );
			return;
		}

		$pagination = HydraCore::generatePaginationHtml(
			$this->getFullTitle(),
			count( $reports ),
			$itemsPerPage,
			$start
		);

		$output->addHTML( $templateCommentModeration->sortStyleSelector( $sortStyle ) );
		$output->addHTML( $pagination );
		$output->addHTML( $templateCommentModeration->renderComments( $reports ) );
		$output->addHTML( $pagination );
	}

	/**
	 * @inheritDoc
	 * only list when we want it listed, and when user is allowed to use
	 */
	public function isListed() {
		return parent::isListed() && $this->userCanExecute( $this->getUser() );
	}
}
