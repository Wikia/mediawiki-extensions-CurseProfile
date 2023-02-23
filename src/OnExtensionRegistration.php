<?php

namespace CurseProfile;

class OnExtensionRegistration {
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
}
