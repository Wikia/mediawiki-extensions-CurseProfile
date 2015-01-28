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
			$content = $mouse->output->commentmoderation->renderComments($this->getModQueue());
		}

		$pagination = $mouse->output->generatePagination($total, $itemsPerPage, $start);
		$pagination = $mouse->output->paginationTemplate($pagination);

		$wgOut->addHTML($mouse->output->commentmoderation->sortStyleSelector($this->sortStyle));
		$wgOut->addHTML($pagination);
		$wgOut->addHTML($content);
		$wgOut->addHTML($pagination);
	}

	private function countModQueue() {
		return 42;
	}

	private function getModQueue() {
		switch($this->sortStyle) {
			case 'byDate':
			// retrieve report data with most recent sorted first
			return;

			case 'byUser':
			// retrieve report data grouped by comment author
			// look for user_id param to potentially only display a specific user's reports
			return;

			case 'byWiki':
			// retrieve report data grouped by child wiki
			return;

			case 'byVolume':
			default:
			// retrieve report data ordered with highest volume sorted first
			return [
				[
					'comment' => [
						'text' => 'Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Aenean commodo ligula eget dolor. Aenean massa. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Donec quam felis, ultricies nec, pellentesque eu, pretium quis, sem. Nulla consequat massa quis enim. Donec pede justo, fringilla vel, aliquet nec, vulputate eget, arcu. In enim justo, rhoncus ut, imperdiet a, venenatis vitae, justo. Nullam dictum felis eu pede mollis pretium. Integer tincidunt. Cras dapibus. Vivamus elementum semper nisi. Aenean vulputate eleifend tellus. Aenean leo ligula, porttitor eu, consequat vitae, eleifend ac, enim. Aliquam lorem ante, dapibus in, viverra quis, feugiat a,',
						'cid' => '12345',
						'origin_wiki' => '31d0850297d41fb6d48cf793c2664abf',
						'last_touched' => '1421617242',
						'author' => '12092614',
					],
					'reports' => [
						[
							'reporter' => '12092614',
							'timestamp' => '1422403930',
						],
						[
							'reporter' => '197108',
							'timestamp' => '1422394792',
						],
						[
							'reporter' => '87049',
							'timestamp' => '1422394792',
						],
						[
							'reporter' => '6514759',
							'timestamp' => '1422394792',
						],
					],
					'action_taken' => 'none'
				],[
					'comment' => [
						'text' => 'Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Aenean commodo ligula eget dolor. Aenean massa. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus.',
						'id' => '12345',
						'origin_wiki' => 'c4d8475e02c1901fcfd9627473a674a4',
						'last_touched' => '1421195517',
						'author' => '12092614',
					],
					'reports' => [
						[
							'reporter' => '12092614',
							'timestamp' => '1422145943',
						],
						[
							'reporter' => '12304932',
							'timestamp' => '1422394792',
						]
					],
					'action_taken' => 'none',
				]
			];
		}
	}
}
