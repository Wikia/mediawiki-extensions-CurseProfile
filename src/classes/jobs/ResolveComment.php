<?php
/**
 * A job to asynchronously call the comment api on a remote wiki
 */

namespace CurseProfile\Classes\Jobs;

use CurseProfile\Classes\CommentReport;
use Job;
use JobSpecification;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserFactory;
use Title;

class ResolveComment extends Job {
	private const COMMAND = "CurseProfile\\ResolveComment";
	private const REPORT_KEY_PARAM = 'reportKey';
	private const ACTION_PARAM = 'action';
	private const BY_USER_PARAM = 'byUser';

	private string $reportKey;
	private string $action;
	private int $byUser;

	public function __construct( array $params, private UserFactory $userFactory ) {
		parent::__construct( self::COMMAND, $params );
		$this->reportKey = $params[self::REPORT_KEY_PARAM];
		$this->action = $params[self::ACTION_PARAM];
		$this->byUser = $params[self::BY_USER_PARAM];
	}

	public static function newInstance( ?Title $title, array $params ): self {
		return new self( $params, MediaWikiServices::getInstance()->getUserFactory() );
	}

	/**
	 * Queue a new job.
	 *
	 * @param array $parameters Named arguments passed by the command that queued this job.
	 *  - reportKey: unique key identifying the reported comment
	 *  - action: 'dismiss' or 'delete'
	 *  - byUser: User ID of admin acting
	 *
	 * @return void
	 */
	public static function queue( string $reportKey, string $action, int $byUser ): void {
		$job = new JobSpecification( self::COMMAND, [
			self::REPORT_KEY_PARAM => $reportKey,
			self::ACTION_PARAM => $action,
			self::BY_USER_PARAM => $byUser,
		] );
		MediaWikiServices::getInstance()->getJobQueueGroup()->push( $job );
	}

	/**
	 * Resolve a reported comment by deleting the comment or ignoring it by marking the report dismissed.
	 *
	 * @return bool Success
	 */
	public function run() {
		$report = CommentReport::newFromKey( $this->reportKey );
		if ( !$report ) {
			return true;
		}

		$user = $this->userFactory->newFromId( $this->byUser );
		$result = $report->resolve( $this->action, $user );

		if ( !$result ) {
			$this->setLastError( "Resolve action encountered an error." );
			return false;
		}
		return true;
	}
}
