<?php
/**
 * A job to asynchronously call the comment api on a remote wiki
 */

namespace CurseProfile;

use DynamicSettings\Wiki;
use Job;
use JobQueueGroup;
use User;
use Wikimedia\Rdbms\DBConnectionError;

class ResolveComment extends Job {
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
	public static function queue(array $parameters = []) {
		$job = new self(__CLASS__, $parameters);
		JobQueueGroup::singleton()->push($job);
	}

	/**
	 * Resolve a reported comment by deleting the comment or ignoring it by marking the report dismissed.
	 *
	 * @return boolean Success
	 */
	public function run() {
		$args = $this->getParams();

		$report = CommentReport::newFromKey($args['reportKey'], true);
		if (!$report) {
			return true;
		}

		$user = User::newFromId($args['byUser']);
		$result = $report->resolve($args['action'], $user);

		if (!$result) {
			$this->setLastError("Resolve action encountered an error.");
			return false;
		}
		return true;
	}

	/**
	 * Look up a wiki by md5key and open a connection to its database
	 *
	 * @param  string $dbKey MD5 key for the wiki
	 * @return object	Active MW database connection
	 */
	public static function getWikiDB($dbKey) {
		try {
			$wiki = Wiki::loadFromHash($dbKey);
			if ($wiki !== false) {
				$db = $wiki->getDatabaseLB()->getConnection(DB_MASTER);
				return $db;
			}
		} catch (DBConnectionError $e) {
			// Doot doot, just fall down to false below.
		}
		return false;
	}
}
