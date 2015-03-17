<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @author		Noah Manneschmidt
 * @copyright	(c) 2015 Curse Inc.
 * @license		All Rights Reserved
 * @package		CurseProfile
 * @link		http://www.curse.com/
 *
**/
namespace CurseProfile;

/**
 * Class that allows manipulation of basic profile data
 */
class ProfileApi extends \CurseApiBase {
	public function getDescription() {
		return 'Allows basic profile data actions to be modified.';
	}

	public function getParamDescription() {
		return array_merge(parent::getParamDescription(), [
			'userId' => 'The local id for a user.',
		]);
	}

	public function getActions() {
		return [
			'purgeAboutMe' => [
				'tokenRequired' => true,
				'postRequired' => true,
				'params' => [
					'userId' => [
						\ApiBase::PARAM_TYPE => 'integer',
						\ApiBase::PARAM_REQUIRED => true,
					],
				],
			],
		];
	}

	public function doPurgeAboutMe() {
		global $wgUser;
		$userId = $this->getMain()->getVal('userId');
		if ($userId && $wgUser->isAllowed('profile-modcomments')) {
			$profileData = new ProfileData($userId);
			$profileData->purgeAboutText();
			$this->getResult()->addValue(null, 'result', 'success');
		} else {
			return $this->dieUsageMsg(['comment-invalidaction']);
		}
	}
}
