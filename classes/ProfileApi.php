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
class ProfileApi extends \HydraApiBase {
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
			'getWikisByString' => [
				'params' => [
					'search' => [
						\ApiBase::PARAM_TYPE => 'string',
						\ApiBase::PARAM_MIN => 1,
						\ApiBase::PARAM_REQUIRED => true,
					],
				],
			],
			'getWiki' => [
				'params' => [
					'hash' => [
						\ApiBase::PARAM_TYPE => 'string',
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
		if ($this->getUser()->getId() === $this->getRequest()->getInt('userId') || $this->getUser()->isAllowed('profile-moderate')) {
			$profileData = new ProfileData($this->getRequest()->getInt('userId'));
			$this->getResult()->addValue(null, 'text', $profileData->getAboutText());
		}
	}

	/**
	 * Return a list of wikis (and data about them) from a search string.
	 * @return void
	 */
	public function doGetWikisByString() {
		$search = $this->getMain()->getVal('search');
		$returnGet = ProfileData::getWikiSitesSearch($search);
		$return = [];
		foreach ($returnGet as $hash => $r) {
			$return[$hash] = [
				'wiki_name' => $r['wiki_name'],
				'wiki_name_display' => $r['wiki_name_display'],
				'md5_key' => $r['md5_key']
			]; // curated data return.
		}
		$this->getResult()->addValue(null, 'result', 'success');
		$this->getResult()->addValue(null, 'data', $return);
	}

	/**
	 * Return wiki data
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
			$this->getResult()->addValue(null, 'message', 'no result found for hash '.$hash);
			$this->getResult()->addValue(null, 'data', []);
		}
	}

	/**
	 * Perform an edit on the about me section.
	 *
	 * @access	public
	 * @return	void
	 */
	public function doEditAboutMe() {
		global $wgOut;

		$profileData = new ProfileData($this->getRequest()->getInt('userId'));

		$canEdit = $profileData->canEdit($this->getUser());
		if ($canEdit !== true) {
			$this->getResult()->addValue(null, 'result', 'failure');
			$this->getResult()->addValue(null, 'errormsg', $canEdit);
			return;
		}

		$text = $this->getMain()->getVal('text');
		$profileData->setAboutText($text, $this->getUser());
		$this->getResult()->addValue(null, 'result', 'success');
		//Add parsed text to result.
		$this->getResult()->addValue(null, 'parsedContent', $wgOut->parse($text));

		return;
	}
}