<?php
/**
 * A job to asyncronously call the comment api on a remote wiki
 */
namespace CurseProfile;

use DynamicSettings\Wiki;

class ResolveComment extends \SyncService\Job {
	/**
	 * Look up a wiki by md5key and open a connection to its database
	 *
	 * @access	public
	 * @param	string	MD5 key for the wiki
	 * @return	object	Active MW database connection
	 */
	public static function getWikiDB($dbKey) {
		try {
			$wiki = Wiki::loadFromHash($dbKey);
			if ($wiki !== false) {
				$db = $wiki->getDatabaseLB()->getConnection(DB_MASTER);
				return $db;
			}
		} catch (DBConnectionError $e) {
			//Doot doot, just fall down to false below.
		}
		return false;
	}

	/**
	 * Resolve a reported comment by deleting the comment or ignoring it by marking the report dismissed
	 *
	 * @param	array	Params for this job with string keys:
	 *   reportKey: unique key identifying the reported comment
	 *   action: 'dismiss' or 'delete'
	 *   byUser: curse ID of admin acting
	 * @return	integer	return code
	 */
	public function execute($args = []) {
		if (!CommentReport::keyIsLocal($args['reportKey'])) {
			list($md5key, $comment_id, $timestamp) = explode(':', $args['reportKey']);
			//Get direct DB connection to the origin wiki.
			$db = self::getWikiDb($md5key);
		} else {
			$db = null;
		}

		//Have all curse profile use this db connection for now.
		CP::setDb($db);

		$this->outputLine("Resolving reported comment {$args['reportKey']} with action {$args['action']} for admin {$args['byUser']}", time());
		$report = CommentReport::newFromKey($args['reportKey'], true);
		if (!$report) {
			return 0;
		}
		$lookup = \CentralIdLookup::factory();
		$user = $lookup->localUserFromCentralId($args['byUser']);
		$result = $report->resolve($args['action'], $user);

		//Revert back to standard db connections.
		CP::setDb(null);
		if ($db) {
			$db->close();
		}

		if ($result) {
			return 0;
		} else {
			$this->outputLine("Resolve action encountered an error", time());
			return 1;
		}
	}
}
