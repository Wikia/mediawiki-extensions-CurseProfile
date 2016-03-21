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
	/**
	 * Return description of this API module.
	 *
	 * @access	public
	 * @return	string	Description
	 */
	public function getDescription() {
		return 'Allows basic profile data actions to be modified.';
	}

	/**
	 * Return description of valid parameters for API actions.
	 *
	 * @access	public
	 * @return	array	API Actions
	 */
	public function getParamDescription() {
		return array_merge(
			parent::getParamDescription(),
			[
				'userId' => 'The local id for a user.',
			]
		);
	}

	/**
	 * Allowed API actions.
	 *
	 * @access	public
	 * @return	array	API Actions
	 */
	public function getActions() {
		return [
			'getRawAboutMe' => [
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
				'params' => [
					'userId' => [
						\ApiBase::PARAM_TYPE		=> 'integer',
						\ApiBase::PARAM_MIN			=> 1,
						\ApiBase::PARAM_REQUIRED	=> true,
					],
					'text' => [
						\ApiBase::PARAM_TYPE		=> 'string',
						\ApiBase::PARAM_REQUIRED	=> false,
					],
				],
			],
		];
	}

	/**
	 * Add the raw about me text into the API response.
	 *
	 * @access	public
	 * @return	void
	 */
	public function doGetRawAboutMe() {
		if ($this->getUser()->getId() === $this->getRequest()->getInt('userId') || $this->getUser()->isAllowed('profile-modcomments')) {
			$profileData = new ProfileData($this->getRequest()->getInt('userId'));
			$this->getResult()->addValue(null, 'text', $profileData->getAboutText());
		}
	}

	/**
	 * Perform an edit on the about me section.
	 *
	 * @access	public
	 * @return	void
	 */
	public function doEditAboutMe() {
		global $wgOut, $wgEmailAuthentication;

		if ($wgEmailAuthentication && (!boolval($this->getUser()->getEmailAuthenticationTimestamp()) || !Sanitizer::validateEmail($this->getUser()->getEmail()))) {
			$this->getResult()->addValue(null, 'result', 'failure');
			$this->getResult()->addValue(null, 'errormsg', 'email-auth-required');
		}

		if ($this->getUser()->getId() === $this->getRequest()->getInt('userId') || $this->getUser()->isAllowed('profile-modcomments')) {
			$profileData = new ProfileData($this->getRequest()->getInt('userId'));
			$text = $this->getMain()->getVal('text');
			$profileData->setAboutText($text);
			$this->getResult()->addValue(null, 'result', 'success');
			// add parsed text to result
			$this->getResult()->addValue(null, 'parsedContent', $wgOut->parse($text));
		}
	}
}
