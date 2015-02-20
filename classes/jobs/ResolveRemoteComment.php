<?php
/**
 * A job to asyncronously call the comment api on a remote wiki
 */
namespace CurseProfile;
class ResolveRemoteComment extends \SyncService\Job {
	public function execute($params = []) {
		list($md5key, $comment_id, $timestamp) = explode(':', $params['report_id']);
		// look up domain for $md5key
		// make api.php request for the given $params['action'] and $params['by_user']
		// TODO figure out how to auth against the permissions the API enforces
		// potentially just connect to the other database?
	}
}
