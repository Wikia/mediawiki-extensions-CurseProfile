<?php
/**
 * A job to asynchronously call the comment api on a remote wiki
 */

namespace CurseProfile\Classes\Jobs;

use CurseProfile\Classes\CommentReport;
use Job;
use MediaWiki\MediaWikiServices;

class ResolveComment extends Job {
	private const COMMAND = "CurseProfile\\ResolveComment";

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
	public static function queue( array $parameters = [] ) {
		$job = new self( self::COMMAND, $parameters );
		MediaWikiServices::getInstance()->getJobQueueGroup()->push( $job );
	}

	/**
	 * Resolve a reported comment by deleting the comment or ignoring it by marking the report dismissed.
	 *
	 * @return bool Success
	 */
	public function run() {
		$args = $this->getParams();

		$report = CommentReport::newFromKey( $args['reportKey'], true );
		if ( !$report ) {
			return true;
		}

		$user = MediaWikiServices::getInstance()->getUserFactory()->newFromId( $args['byUser'] );
		$result = $report->resolve( $args['action'], $user );

		if ( !$result ) {
			$this->setLastError( "Resolve action encountered an error." );
			return false;
		}
		return true;
	}
}
