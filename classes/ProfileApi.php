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
			'getRawAboutMe' => [
				'permissionRequired' => 'profile-modcomments',
				'params' => [
					'userId' => [
						\ApiBase::PARAM_TYPE => 'integer',
						\ApiBase::PARAM_MIN => 1,
						\ApiBase::PARAM_REQUIRED => true,
					],
				],
			],
			'editAboutMe' => [
				'tokenRequired' => true,
				'postRequired' => true,
				'permissionRequired' => 'profile-modcomments',
				'params' => [
					'userId' => [
						\ApiBase::PARAM_TYPE => 'integer',
						\ApiBase::PARAM_MIN => 1,
						\ApiBase::PARAM_REQUIRED => true,
					],
					'text' => [
						\ApiBase::PARAM_TYPE => 'string',
						\ApiBase::PARAM_REQUIRED => true,
					],
				],
			],
		];
	}

	public function doGetRawAboutMe() {
		$profileData = new ProfileData($this->getMain()->getVal('userId'));
		$this->getResult()->addValue(null, 'text', $profileData->getAboutText());
	}

	public function doEditAboutMe() {
		$profileData = new ProfileData($this->getMain()->getVal('userId'));
		$text = $this->getMain()->getVal('text');
		$profileData->setAboutText($text);
		$this->getResult()->addValue(null, 'result', 'success');
		// add parsed text to result
		global $wgOut;
		$this->getResult()->addValue(null, 'parsedContent', $wgOut->parse($text));
	}
}
