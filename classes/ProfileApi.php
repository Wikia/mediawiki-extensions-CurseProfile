<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @package   CurseProfile
 * @author    Noah Manneschmidt
 * @copyright (c) 2015 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki
**/

namespace CurseProfile;

use ApiBase;
use HydraApiBase;
use MWException;

/**
 * Class that allows manipulation of basic profile data
 */
class ProfileApi extends HydraApiBase {
	/**
	 * Allowed API actions.
	 *
	 * @return array	API Actions
	 */
	public function getActions() {
		return [
			'getPublicProfile' => [
				'tokenRequired' => false,
				'postRequired' => false,
				'params' => [
					'user_name' => [
						ApiBase::PARAM_TYPE => 'string',
						ApiBase::PARAM_MIN => 1,
						ApiBase::PARAM_REQUIRED => true,
					]
				]
			],
			'getRawField' => [
				'tokenRequired' => true,
				'postRequired' => true,
				'params' => [
					'field' => [
						ApiBase::PARAM_TYPE => 'string',
						ApiBase::PARAM_REQUIRED => true,
					],
					'user_id' => [
						ApiBase::PARAM_TYPE => 'integer',
						ApiBase::PARAM_MIN => 1,
						ApiBase::PARAM_REQUIRED => true,
					],
				],
			],
			'getWikisByString' => [
				'params' => [
					'search' => [
						ApiBase::PARAM_TYPE => 'string',
						ApiBase::PARAM_MIN => 1,
						ApiBase::PARAM_REQUIRED => true,
					],
				],
			],
			'getWiki' => [
				'params' => [
					'hash' => [
						ApiBase::PARAM_TYPE => 'string',
						ApiBase::PARAM_MIN => 1,
						ApiBase::PARAM_REQUIRED => true,
					],
				],
			],
			'editField' => [
				'tokenRequired' => true,
				'postRequired' => true,
				'params' => [
					'field' => [
						ApiBase::PARAM_TYPE => 'string',
						ApiBase::PARAM_REQUIRED => true,
					],
					'user_id' => [
						ApiBase::PARAM_TYPE		=> 'integer',
						ApiBase::PARAM_MIN			=> 1,
						ApiBase::PARAM_REQUIRED	=> true,
					],
					'text' => [
						ApiBase::PARAM_TYPE		=> 'string',
						ApiBase::PARAM_REQUIRED	=> false,
					],
				],
			],
			'editSocialFields' => [
				'tokenRequired' => true,
				'postRequired' => true,
				'params' => [
					'data' => [
						ApiBase::PARAM_TYPE => 'string',
						ApiBase::PARAM_REQUIRED => true,
					],
					'user_id' => [
						ApiBase::PARAM_TYPE		=> 'integer',
						ApiBase::PARAM_MIN			=> 1,
						ApiBase::PARAM_REQUIRED	=> true,
					],

				],
			],
		];
	}

	/**
	 * Return a list of wikis (and data about them) from a search string.
	 *
	 * @return void
	 */
	public function doGetWikisByString() {
		$search = $this->getMain()->getVal('search');
		$returnGet = ProfileData::getWikiSitesSearch($search);
		$return = [];
		foreach ($returnGet as $hash => $r) {
			// curated data return.
			$return[$hash] = [
				'wiki_name' => $r['wiki_name'],
				'wiki_name_display' => $r['wiki_name_display'],
				'md5_key' => $r['md5_key']
			];
		}
		$this->getResult()->addValue(null, 'result', 'success');
		$this->getResult()->addValue(null, 'data', $return);
	}

	/**
	 * Return wiki data
	 *
	 * @return void
	 */
	public function doGetWiki() {
		$hash = $this->getMain()->getVal('hash');
		$return = ProfileData::getWikiSites($hash);

		if (isset($return[$hash])) {
			$r = $return[$hash];
			$return = [
				'wiki_name' => $r['wiki_name'],
				'wiki_name_display' => $r['wiki_name_display'],
				'md5_key' => $r['md5_key']
			]; // curated data return.
			$this->getResult()->addValue(null, 'result', 'success');
			$this->getResult()->addValue(null, 'data', $return);
		} else {
			$this->getResult()->addValue(null, 'result', 'error');
			$this->getResult()->addValue(null, 'message', 'no result found for hash ' . $hash);
			$this->getResult()->addValue(null, 'data', []);
		}
	}

	/**
	 * Add the public info from a user profile by username
	 *
	 * @return void
	 */
	public function doGetPublicProfile() {
		$userName = $this->getRequest()->getText('user_name');
		$user = \User::newFromName($userName);
		if (!$user || !$user->getId()) {
			$this->getResult()->addValue(null, 'result', 'failure');
			$this->getResult()->addValue(null, 'errormsg', 'Invalid user.');
			return;
		}
		$profileData = new ProfileData($user->getId());
		$validFields = $profileData::getValidEditFields();
		$userFields = ['username' => $userName];
		foreach ($validFields as $field) {
			$field = str_replace('profile-', '', $field);
			$userFields[$field] = $profileData->getField($field);
		}
		$this->getResult()->addValue(null, 'profile', $userFields);
	}

	/**
	 * Add the raw about me text into the API response.
	 *
	 * @return void
	 */
	public function doGetRawField() {
		if ($this->getUser()->getId() === $this->getRequest()->getInt('user_id') || $this->getUser()->isAllowed('profile-moderate')) {
			$field = strtolower($this->getRequest()->getText('field'));
			$profileData = new ProfileData($this->getRequest()->getInt('user_id'));
			try {
				$fieldText = $profileData->getField($field);
			} catch (MWException $e) {
				$this->getResult()->addValue(null, 'result', 'failure');
				$this->getResult()->addValue(null, 'errormsg', 'Invalid profile field.');
				return;
			}

			$this->getResult()->addValue(null, $field, $profileData->getField($field));
		}
	}

	/**
	 * Perform an edit on general profile fields.
	 *
	 * @return void
	 */
	public function doEditField() {
		$field = strtolower($this->getRequest()->getText('field'));
		$text = $this->getMain()->getVal('text');
		$profileData = new ProfileData($this->getRequest()->getInt('user_id'));

		$canEdit = $profileData->canEdit($this->getUser());
		if ($canEdit !== true) {
			$this->getResult()->addValue(null, 'result', 'failure');
			$this->getResult()->addValue(null, 'errormsg', $canEdit);
			return;
		}

		try {
			$profileData->setField($field, $text, $this->getUser());
			$fieldText = $profileData->getFieldHtml($field);
			$this->getResult()->addValue(null, 'result', 'success');
			// Add parsed text to result.
			$this->getResult()->addValue(null, 'parsedContent', $fieldText);
			return;
		} catch (MWException $e) {
			$this->getResult()->addValue(null, 'result', 'failure');
			$this->getResult()->addValue(null, 'errormsg', $e->getMessage());
			return;
		}
	}

	/**
	 * Perform an edit on the about me section with multiple fields.
	 *
	 * @return void
	 */
	public function doEditSocialFields() {
		$odata = $this->getRequest()->getText('data');
		$data = json_decode($odata, 1);
		if (!$data) {
			$this->getResult()->addValue(null, 'result', 'failure');
			$this->getResult()->addValue(null, 'errormsg', 'Failed to decode data sent. (' . $odata . ')');
			return;
		}

		$profileData = new ProfileData($this->getRequest()->getInt('user_id'));
		$canEdit = $profileData->canEdit($this->getUser());
		if ($canEdit !== true) {
			$this->getResult()->addValue(null, 'result', 'failure');
			$this->getResult()->addValue(null, 'errormsg', $canEdit);
			return;
		}

		try {
			foreach ($data as $field => $text) {
				$text = ProfileData::validateExternalProfile(str_replace('link-', '', $field), preg_replace('/\s+\#/', '#', trim($text)));
				if ($text === false) {
					$text = '';
				}
				if ($profileData->getField($field) != $text) {
					$profileData->setField($field, $text, $this->getUser());
				}
			}
			$this->getResult()->addValue(null, 'result', 'success');
			$this->getResult()->addValue(null, 'parsedContent', $profileData->getProfileLinksHtml());
			return;
		} catch (MWException $e) {
			$this->getResult()->addValue(null, 'result', 'failure');
			$this->getResult()->addValue(null, 'errormsg', $e->getMessage());
			return;
		}
	}
}
