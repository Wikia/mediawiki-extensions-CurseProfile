<?php

use CurseProfile\Classes\CommentBoard;
use CurseProfile\Classes\CommentReport;
use MediaWiki\MediaWikiServices;

/**
 * @group  CurseProfile
 * @group  Database
 *            ^--------- requests database access for these tests
 * @covers CommentReport
 */
class CommentReportTest extends MediaWikiTestCase {
	public function addDBData() {
		$userFactory = MediaWikiServices::getInstance()->getUserFactory();
		$testUsers = [
			'Commenter' => null,
			'Commentee' => null,
		];
		foreach ( $testUsers as $username => &$u ) {
			$u = $userFactory->newFromName( $username );
			if ( $u->getId() == 0 ) {
				$u->addToDatabase();
				$u->saveSettings();
			}
		}

		$this->setMwGlobals( 'wgUser', $testUsers['Commenter'] );

		$commentBoard = new CommentBoard( $testUsers['Commentee']->getId() );
	}

	protected $commenter, $commentee, $commentBoard;

	protected function setUp(): void {
		parent::setUp();
		$userFactory = MediaWikiServices::getInstance()->getUserFactory();
		$this->commenter = $userFactory->newFromName( 'Commenter' );
		$this->commentee = $userFactory->newFromName( 'Commentee' );
		$this->commentBoard = new CommentBoard( $this->commentee->getId() );
	}

	public function testGetReports() {
		$reports = CommentReport::getReports();
		$this->assertIsArray( $reports, 'CommentReport::getReports should return an array' );
		return $reports;
	}

	/**
	 * @depends testGetReports
	 */
	public function testReportComment( $reports ) {
		$this->commentBoard->addComment( 'Hello world!' );
		$comments = $this->commentBoard->getComments();

		$report = CommentReport::newUserReport( $comments[0]['ub_id'] );
		$this->assertInstanceOf( 'CommentReport', $report, "Comments should be reportable by logged-in users." );

		$newReports = CommentReport::getReports();
		$this->assertGreaterThan( count( $reports ), count( $newReports ), 'A new report should be returned after one has been submitted.' );
	}
}
