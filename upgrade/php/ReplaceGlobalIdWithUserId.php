<?php
/**
 * Replace Global ID with User ID Maintenance Script
 *
 * @package   CurseProfile
 * @copyright (c) 2020 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki
**/

namespace CurseProfile\Maintenance;

use HydraAuthUser;
use LoggedUpdateMaintenance;
use Wikimedia\Rdbms\IDatabase;

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/maintenance/Maintenance.php';

/**
 * Maintenance script that cleans up tables that have orphaned users.
 */
class ReplaceGlobalIdWithUserId extends LoggedUpdateMaintenance {
	private $prefix;

	private $table;

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();
		$this->addDescription('Replaces global ID with user ID in CurseProfile tables.');
		$this->setBatchSize(100);
	}

	/**
	 * Return an unique name to logged this maintenance as being done.
	 *
	 * @return string
	 */
	protected function getUpdateKey() {
		return __CLASS__;
	}

	/**
	 * Do database updates for all tables.
	 *
	 * @return boolean True
	 */
	protected function doDBUpdates() {
		$this->cleanup(
			'user_board',
			'ub_id',
			['ub_admin_acted_global_id' => 'ub_admin_acted_user_id'],
			['ub_id']
		);
		$this->cleanup(
			'user_board_reports',
			'ubr_id',
			[
				'ubr_reporter_global_id' => 'ubr_reporter_user_id'
			],
			['ubr_id']
		);
		$this->cleanup(
			'user_board_report_archives',
			'ra_id',
			[
				'ra_global_id_from' => 'ra_user_id_from',
				'ra_action_taken_by_global_id' => 'ra_action_taken_by_user_id'
			],
			['ra_id']
		);

		return true;
	}

	/**
	 * Cleanup a table.
	 *
	 * @param string $table          Table to migrate
	 * @param string $primaryKey     Primary key of the table.
	 * @param array  $globalIdFields Global ID field to User ID field as $key => $value relation.
	 * @param array  $orderby        Fields to order by
	 *
	 * @return void
	 */
	protected function cleanup(
		string $table, string $primaryKey, array $globalIdFields, array $orderby
	) {
		$this->output(
			"Beginning cleanup of $table\n"
		);

		$dbw = $this->getDB(DB_MASTER);
		$next = '1=1';
		$count = 0;
		while (true) {
			foreach ($globalIdFields as $key => $value) {
				if (!$dbw->fieldExists($table, $key)) {
					unset($globalIdFields[$key]);
				}
			}
			if (empty($globalIdFields)) {
				$this->output("Skipping due to global ID fields not being present.\n");
				break;
			}
			// Fetch the rows needing update
			$res = $dbw->select(
				$table,
				array_merge([$primaryKey], array_keys($globalIdFields)),
				[$next],
				__METHOD__,
				[
					'ORDER BY' => $orderby,
					'LIMIT' => $this->mBatchSize,
				]
			);
			if (!$res->numRows()) {
				break;
			}

			// Update the existing rows
			foreach ($res as $row) {
				$update = [];
				foreach ($globalIdFields as $globalIdField => $userIdField) {
					if ($row->$globalIdField) {
						$userId = HydraAuthUser::userIdFromGlobalId($row->$globalIdField);
						if ($userId > 0) {
							$update[$userIdField] = $userId;
						}
					}
				}

				if (!empty($update)) {
					$dbw->update(
						$table,
						$update,
						[$primaryKey => $row->$primaryKey],
						__METHOD__
					);
					$count += $dbw->affectedRows();
				}
			}

			list($next, $display) = $this->makeNextCond($dbw, $orderby, $row);
			$this->output("... $display\n");
			wfWaitForSlaves();
		}

		$this->output(
			"Cleanup complete: Replaced {$count} global IDs with user ID\n"
		);
	}

	/**
	 * Calculate a "next" condition and progress display string
	 *
	 * @param IDatabase $dbw
	 * @param string[]  $indexFields Fields in the index being ordered by
	 * @param object    $row         Database row
	 *
	 * @return string[] [ string $next, string $display ]
	 */
	private function makeNextCond($dbw, array $indexFields, $row) {
		$next = '';
		$display = [];
		for ($i = count($indexFields) - 1; $i >= 0; $i--) {
			$field = $indexFields[$i];
			$display[] = $field . '=' . $row->$field;
			$value = $dbw->addQuotes($row->$field);
			if ($next === '') {
				$next = "$field > $value";
			} else {
				$next = "$field > $value OR $field = $value AND ($next)";
			}
		}
		$display = implode(' ', array_reverse($display));
		return [$next, $display];
	}
}

$maintClass = \CurseProfile\Maintenance\ReplaceGlobalIdWithUserId::class;
require_once RUN_MAINTENANCE_IF_MAIN;
