<?php

namespace CurseProfile\Classes;

use CurseProfile\Classes\Jobs\PurgeCommentsJob;
use Fandom\QuickTools\Hook\QuickToolsRevertOrDelete;
use JobQueueGroup;
use MediaWiki\User\UserFactory;
use Wikimedia\Rdbms\ILoadBalancer;

class QuickToolsHookHandler implements QuickToolsRevertOrDelete {

	public function __construct(
		private JobQueueGroup $jobQueueGroup,
		private ILoadBalancer $lb,
		private UserFactory $userFactory,
	) {
	}

	public function onQuickToolsRevertOrDelete(
		string $targetUsername,
		string $time,
		string $summary,
		bool $doRollback,
		bool $doDelete,
		bool &$alteredByHook,
	): void {
		if ( !$doDelete ) {
			return;
		}

		$targetUser = $this->userFactory->newFromName( $targetUsername );
		if ( !$targetUser && !$targetUser->isRegistered() ) {
			return;
		}

		if ( !$this->willRemove( $targetUser->getId(), $time ) ) {
			return;
		}

		$this->jobQueueGroup->lazyPush(
			PurgeCommentsJob::newSpecification( $targetUser->getId(), $summary, $time )
		);
		$alteredByHook = true;
	}

	private function willRemove( int $targetUserId, string $time ): bool {
		$db = $this->lb->getConnection( DB_REPLICA );
		$timestamp = $db->timestamp( $time );

		return boolval(
			$this->lb->getConnection( DB_REPLICA )
				->newSelectQueryBuilder()
				->select( '*' )
				->from( 'user_board' )
				->where( [
					'ub_user_id_from' => $targetUserId,
					"ub_date > $timestamp",
				] )
				->limit( 1 )
				->caller( __METHOD__ )
				->fetchRowCount()
		);
	}

}
