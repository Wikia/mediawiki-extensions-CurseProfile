<?php

namespace CurseProfile\Classes\Jobs;

use CurseProfile\Classes\Comment;
use CurseProfile\Classes\CommentBoard;
use Fandom\Includes\Logging\Loggable;
use Fandom\Includes\Rabbit\JobConfigurator;
use Fandom\Includes\Rabbit\JobParams;
use IDatabase;
use Job;
use JobSpecification;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\User\UserFactory;
use Wikimedia\Rdbms\ILBFactory;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * Job that mass purges comments made by a user
 */
class PurgeCommentsJob extends Job {
	use Loggable;

	private const CONSTRUCTOR_OPTIONS = [ 'FandomBotUserName' ];
	private const COMMAND = "CurseProfile\\PurgeComments";
	private const JOB_OWNER = JobConfigurator::UGC_TEAM_LABEL;
	private const LIMIT = 100;
	private int $targetUserId;
	private string $summary;
	private string $time;

	public function __construct(
		array $params,
		private ILBFactory $lbFactory,
		private UserFactory $userFactory,
		private ServiceOptions $serviceOptions
	) {
		$this->serviceOptions->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->targetUserId = $params['targetUserId'];
		$this->summary = $params['summary'];
		$this->time = $params['time'];

		parent::__construct( self::COMMAND, $params );
	}

	public static function newSpecification(
		int $targetUserId,
		string $summary,
		string $time,
	): JobSpecification {
		return new JobSpecification(
			self::COMMAND,
			[
				JobParams::JOB_OWNER => self::JOB_OWNER,
				'targetUserId' => $targetUserId,
				'summary' => $summary,
				'time' => $time,
			]
		);
	}

	public static function newInstance( ?Title $title, $params ): Job {
		$services = MediaWikiServices::getInstance();

		return new PurgeCommentsJob(
			$params,
			$services->getDBLoadBalancerFactory(),
			$services->getUserFactory(),
			new ServiceOptions(
				self::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
		);
	}

	public function run(): void {
		$botUser = $this->userFactory->newFromName( $this->serviceOptions->get( 'FandomBotUserName' ) );
		$lastOffset = 0;
		$ticket = $this->lbFactory->getEmptyTransactionTicket( __METHOD__ );
		$lb = $this->lbFactory->getMainLB();
		$connection = $lb->getConnection( DB_PRIMARY );
		$commentsFromDB = $this->getCommentsFromTimestamp( $connection, $this->targetUserId, $this->time, $lastOffset );
		while ( $commentsFromDB->numRows() ) {
			foreach ( $commentsFromDB as $commentRow ) {
				try {
					$lastOffset = $commentRow->ub_id;
					$comment = new Comment( (array)$commentRow );
					CommentBoard::purgeComment( $comment, $botUser, $this->summary );
				} catch ( \MWException $e ) {
					$this->error( "Failed to purge comment", [ 'exception' => $e ] );
				}
			}
			$this->lbFactory->commitAndWaitForReplication( __METHOD__, $ticket );
			$commentsFromDB =
				$this->getCommentsFromTimestamp( $connection, $this->targetUserId, $this->time, $lastOffset );
		}
	}

	private function getCommentsFromTimestamp(
		IDatabase $connection,
		int $targetUserId,
		string $time,
		int $offsetId
	): IResultWrapper {
		return $connection
			->newSelectQueryBuilder()
			->select( '*' )
			->from( 'user_board' )
			->where( [
				"ub_id > $offsetId",
				'ub_user_id_from' => $targetUserId,
				"ub_date > $time",
			] )
			->limit( self::LIMIT )
			->caller( __METHOD__ )
			->fetchResultSet();
	}
}
