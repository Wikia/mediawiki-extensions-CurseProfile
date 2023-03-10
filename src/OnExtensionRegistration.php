<?php

namespace CurseProfile;

use CurseProfile\Maintenance\ReplaceGlobalIdWithUserId;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class OnExtensionRegistration implements LoadExtensionSchemaUpdatesHook {
	public static function onRegistration() {
		global $wgReverbNotifications;

		$reverbNotifications = [
			"user-interest-profile-comment" => [
				"importance" => 8
			],
			"user-interest-profile-comment-reply-self-self" => [
				"importance" => 8,
				"use-preference" => "user-interest-profile-comment"
			],
			"user-interest-profile-comment-reply-self-other" => [
				"importance" => 8,
				"use-preference" => "user-interest-profile-comment"
			],
			"user-interest-profile-comment-reply-other-self" => [
				"importance" => 8,
				"use-preference" => "user-interest-profile-comment"
			],
			"user-moderation-profile-comment-report" => [
				"importance" => 1,
				"requires" => [ "hydra_admin", "sysop" ]
			],
			"user-interest-profile-friendship" => [
				"importance" => 5
			]
		];
		$wgReverbNotifications = array_merge( $wgReverbNotifications, $reverbNotifications );

		return true;
	}

	/** @inheritDoc */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$extDir = dirname( __DIR__ ) . '/..';

		// Add tables that may exist for previous users of SocialProfile.
		$updater->addExtensionTable( 'user_board', "$extDir/install/sql/table_user_board.sql" );
		$updater->addExtensionTable(
			'user_board_report_archives',
			"$extDir/install/sql/table_user_board_report_archives.sql"
		);
		$updater->addExtensionTable(
			'user_board_reports',
			"$extDir/install/sql/table_user_board_reports.sql"
		);

		// global_id migration.
		$updater->addExtensionField(
			'user_board_reports',
			'ubr_id',
			"$extDir/upgrade/sql/user_board_reports/add_ubr_id.sql"
		);
		$updater->modifyExtensionField(
			'user_board_reports',
			'ubr_reporter_global_id',
			"$extDir/upgrade/sql/user_board_reports/change_ubr_reporter_global_id.sql"
		);
		$updater->addExtensionField(
			'user_board_reports',
			'ubr_reporter_user_id',
			"$extDir/upgrade/sql/user_board_reports/add_ubr_reporter_user_id.sql"
		);
		$updater->addExtensionIndex(
			'user_board_reports',
			'ubr_report_archive_id_ubr_reporter_user_id',
			"$extDir/upgrade/sql/user_board_reports/add_index_ubr_report_archive_id_ubr_reporter_user_id.sql"
		);
		$updater->dropExtensionIndex(
			'user_board_reports',
			'ubr_report_archive_id_ubr_reporter_global_id',
			"$extDir/upgrade/sql/user_board_reports/drop_index_ubr_report_archive_id_ubr_reporter_global_id.sql"
		);
		$updater->addExtensionField(
			'user_board_report_archives',
			'ra_user_id_from',
			"$extDir/upgrade/sql/user_board_report_archives/add_ra_user_id_from.sql"
		);
		$updater->modifyExtensionField(
			'user_board_report_archives',
			'ra_action_taken_by',
			"$extDir/upgrade/sql/user_board_report_archives/rename_ra_action_taken_by.sql"
		);
		$updater->addExtensionField(
			'user_board_report_archives',
			'ra_action_taken_by_user_id',
			"$extDir/upgrade/sql/user_board_report_archives/add_ra_action_taken_by_user_id.sql"
		);
		$updater->addExtensionField(
			'user_board',
			'ub_admin_acted_user_id',
			"$extDir/upgrade/sql/user_board/add_ub_admin_acted_user_id.sql"
		);
		$updater->modifyExtensionField(
			'user_board',
			'ub_admin_acted',
			"$extDir/upgrade/sql/user_board/rename_ub_admin_acted.sql"
		);
		$updater->addPostDatabaseUpdateMaintenance( ReplaceGlobalIdWithUserId::class );

		// global_id migration - Second part
		$updater->dropExtensionField(
			'user_board_reports',
			'ubr_reporter_global_id',
			"$extDir/upgrade/sql/user_board_reports/drop_ubr_reporter_global_id.sql"
		);
		$updater->dropExtensionField(
			'user_board_report_archives',
			'ra_global_id_from',
			"$extDir/upgrade/sql/user_board_report_archives/drop_ra_global_id_from.sql"
		);
		$updater->dropExtensionField(
			'user_board_report_archives',
			'ra_action_taken_by_global_id',
			"$extDir/upgrade/sql/user_board_report_archives/drop_ra_action_taken_by_global_id.sql"
		);
		$updater->dropExtensionField(
			'user_board',
			'ub_admin_acted_global_id',
			"$extDir/upgrade/sql/user_board/drop_ub_admin_acted_global_id.sql"
		);
		$updater->addExtensionTable(
			'user_board_purge_archive',
			"$extDir/install/sql/table_user_board_purge_archive.sql"
		);
	}
}
