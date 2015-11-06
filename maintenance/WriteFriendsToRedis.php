<?php
/**
 * Curse Inc.
 * Curse Profile
 * Copies the current friend relationships out of the master DB into redis
 *
 * @author		Noah Manneschmidt
 * @copyright	(c) 2015 Curse Inc.
 * @license		All Rights Reserved
 * @package		CurseProfile
 * @link		http://www.curse.com/
 *
**/
namespace CurseProfile;
require_once(dirname(dirname(__DIR__))."/SyncService/ILogger.php");
require_once(dirname(dirname(dirname(__DIR__)))."/maintenance/Maintenance.php");

class WriteFriendsToRedis extends \Maintenance implements \SyncService\ILogger {
	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		parent::__construct();
		$this->addDescription('Copies the current friend relationships out of the master DB into redis in case of a desync or loss of data in redis.');
		$this->addOption('curse-id', 'Only operate on the friends and requests of a single user. (cannot be used with --all)', false, true);
		$this->addOption('all', 'Instead of syncing only a single user\'s friendships, iterate across the entire DB. (cannot use be used with --curse-id)', false, false);
	}

	/**
	 * Main Executor
	 *
	 * @access	public
	 * @return	void
	 */
	public function execute() {
		if (!defined('CURSEPROFILE_MASTER')) {
			$this->error('Must only be run in the context of the master wiki');
		}

		if ($this->hasOption('all') && $this->hasOption('curse-id')) {
			$this->error('Must only use only ONE of --all or --curse-id');
			$this->maybeHelp(true);

		} elseif ($this->hasOption('all')) {
			$f = new Friendship(null);

		} elseif ($this->hasOption('curse-id')) {
			$f = new Friendship(intval($this->getOption('curse-id', 0)));

		} else {
			$this->error('Must use one of --all or --curse-id');
			$this->maybeHelp(true);
		}

		if ($errCode = $f->syncToRedis($this)) {
			$this->error("The sync function returned an error state", $errCode);
		}
	}

	/**
	 * copied from /SyncService/Worker
	 *
	 * @param	string	the message to output
	 * @param	integer	[optional] timestamp with which to prefix the message
	 * @return	void
	 */
	public function outputLine($message, $time = null) {
		if ($time) {
			$message = '['.date('c', $time).'] '.$message;
		}
		parent::output($message."\n");
	}
}

$maintClass = 'CurseProfile\WriteFriendsToRedis';
require_once(RUN_MAINTENANCE_IF_MAIN);
