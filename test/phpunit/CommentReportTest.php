<?php
/**
 * @group CurseProfile
 * @covers CommentReport
 */
class CommentReportTest extends MediaWikiTestCase {
	protected function setUp() {
		parent::setUp();
	}

	protected function tearDown() {
		parent::tearDown();
	}

	public function testReportComment() {
		$this->assertTrue(false, "Comments should be reportable by logged-in users.");
	}
}
