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
 */

namespace CurseProfile\Api;

use ApiMain;
use CurseProfile\Classes\ProfileData;
use HydraApiBase;
use MediaWiki\User\UserFactory;
use MWException;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\NumericDef;

/**
 * Class that allows manipulation of basic profile data
 */
class ProfileApi extends HydraApiBase {
	public function __construct( ApiMain $main, $action, private UserFactory $userFactory ) {
		parent::__construct( $main, $action );
	}

	/** @inheritDoc */
	public function getActions(): array {
		return [
			'getPublicProfile' => [
				'tokenRequired' => false,
				'postRequired' => false,
				'params' => [
					'user_name' => [
						ParamValidator::PARAM_TYPE => 'string',
						NumericDef::PARAM_MIN => 1,
						ParamValidator::PARAM_REQUIRED => true,
					]
				]
			],
			'getRawField' => [
				'tokenRequired' => true,
				'postRequired' => true,
				'params' => [
					'field' => [
						ParamValidator::PARAM_TYPE => 'string',
						ParamValidator::PARAM_REQUIRED => true,
					],
					'user_id' => [
						ParamValidator::PARAM_TYPE => 'integer',
						NumericDef::PARAM_MIN => 1,
						ParamValidator::PARAM_REQUIRED => true,
					],
				],
			],
			'getWikisByString' => [
				'params' => [
					'search' => [
						ParamValidator::PARAM_TYPE => 'string',
						NumericDef::PARAM_MIN => 1,
						ParamValidator::PARAM_REQUIRED => true,
					],
				],
			],
			'getWiki' => [
				'params' => [
					'hash' => [
						ParamValidator::PARAM_TYPE => 'string',
						NumericDef::PARAM_MIN => 1,
						ParamValidator::PARAM_REQUIRED => true,
					],
				],
			],
			'editField' => [
				'tokenRequired' => true,
				'postRequired' => true,
				'params' => [
					'field' => [
						ParamValidator::PARAM_TYPE => 'string',
						ParamValidator::PARAM_REQUIRED => true,
					],
					'user_id' => [
						ParamValidator::PARAM_TYPE => 'integer',
						NumericDef::PARAM_MIN => 1,
						ParamValidator::PARAM_REQUIRED => true,
					],
					'text' => [
						ParamValidator::PARAM_TYPE => 'string',
						ParamValidator::PARAM_REQUIRED => false,
					],
				],
			],
			'editSocialFields' => [
				'tokenRequired' => true,
				'postRequired' => true,
				'params' => [
					'data' => [
						ParamValidator::PARAM_TYPE => 'string',
						ParamValidator::PARAM_REQUIRED => true,
					],
					'user_id' => [
						ParamValidator::PARAM_TYPE => 'integer',
						NumericDef::PARAM_MIN => 1,
						ParamValidator::PARAM_REQUIRED => true,
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
	public function doGetWikisByString(): void {
		$search = $this->getMain()->getVal( 'search' );
		$returnGet = ProfileData::getWikiSitesSearch( $search );
		$return = [];
		foreach ( $returnGet as $hash => $r ) {
			// curated data return.
			$return[$hash] = [
				'wiki_name' => $r['wiki_name'],
				'wiki_name_display' => $r['wiki_name_display'],
				'wiki_url' => $r['wiki_url'],
				'md5_key' => $r['md5_key']
			];
		}
		$this->getResult()->addValue( null, 'result', 'success' );
		$this->getResult()->addValue( null, 'data', $return );
	}

	/**
	 * Return wiki data
	 *
	 * @return void
	 */
	public function doGetWiki(): void {
		$hash = $this->getMain()->getVal( 'hash' );
		$wiki = ProfileData::getWikiSite( $hash );

		if ( !empty( $wiki ) ) {
			$this->getResult()->addValue( null, 'result', 'success' );
			$this->getResult()->addValue( null, 'data', $wiki );
		} else {
			$this->getResult()->addValue( null, 'result', 'error' );
			$this->getResult()->addValue( null, 'message', 'no result found for hash ' . $hash );
			$this->getResult()->addValue( null, 'data', [] );
		}
	}

	/**
	 * Add the public info from a user profile by username
	 *
	 * @return void
	 */
	public function doGetPublicProfile(): void {
		$userName = $this->getRequest()->getText( 'user_name' );
		$user = $this->userFactory->newFromName( $userName );
		if ( !$user || $user->isAnon() ) {
			$this->getResult()->addValue( null, 'result', 'failure' );
			$this->getResult()->addValue( null, 'errormsg', 'Invalid user.' );
			return;
		}
		$profileData = new ProfileData( $user );
		$validFields = ProfileData::getValidEditFields();
		$userFields = [ 'username' => $userName ];
		foreach ( $validFields as $field ) {
			$field = str_replace( 'profile-', '', $field );
			$userFields[$field] = $profileData->getField( $field );
		}
		$this->getResult()->addValue( null, 'profile', $userFields );
	}

	/**
	 * Add the raw about me text into the API response.
	 *
	 * @return void
	 */
	public function doGetRawField(): void {
		if ( $this->getUser()->getId() !== $this->getRequest()->getInt( 'user_id' ) &&
			!$this->getUser()->isAllowed( 'profile-moderate' ) ) {
			return;
		}

		$field = strtolower( $this->getRequest()->getText( 'field' ) );
		$profileData = new ProfileData( $this->getRequest()->getInt( 'user_id' ) );
		try {
			$fieldText = $profileData->getField( $field );
			$this->getResult()->addValue( null, $field, $fieldText );
		} catch ( MWException $e ) {
			$this->getResult()->addValue( null, 'result', 'failure' );
			$this->getResult()->addValue( null, 'errormsg', 'Invalid profile field.' );
		}
	}

	/**
	 * Perform an edit on general profile fields.
	 *
	 * @return void
	 */
	public function doEditField(): void {
		$field = strtolower( $this->getRequest()->getText( 'field' ) );
		$text = $this->getMain()->getVal( 'text' );
		$profileData = new ProfileData( $this->getRequest()->getInt( 'user_id' ) );

		$canEdit = $profileData->canEdit( $this->getUser() );
		if ( $canEdit !== true ) {
			$this->getResult()->addValue( null, 'result', 'failure' );
			$this->getResult()->addValue( null, 'errormsg', $canEdit );
			return;
		}

		try {
			$profileData->setField( $field, $text, $this->getUser() );
			$fieldText = $profileData->getFieldHtml( $field );
			$this->getResult()->addValue( null, 'result', 'success' );
			// Add parsed text to result.
			$this->getResult()->addValue( null, 'parsedContent', $fieldText );
			return;
		} catch ( MWException $e ) {
			$this->getResult()->addValue( null, 'result', 'failure' );
			$this->getResult()->addValue( null, 'errormsg', $e->getMessage() );
			return;
		}
	}

	/**
	 * Perform an edit on the about me section with multiple fields.
	 *
	 * @return void
	 */
	public function doEditSocialFields(): void {
		$odata = $this->getRequest()->getText( 'data' );
		$data = json_decode( $odata, true );
		if ( !$data ) {
			$this->getResult()->addValue( null, 'result', 'failure' );
			$this->getResult()->addValue( null, 'errormsg', 'Failed to decode data sent. (' . $odata . ')' );
			return;
		}

		$profileData = new ProfileData( $this->getRequest()->getInt( 'user_id' ) );
		$canEdit = $profileData->canEdit( $this->getUser() );
		if ( $canEdit !== true ) {
			$this->getResult()->addValue( null, 'result', 'failure' );
			$this->getResult()->addValue( null, 'errormsg', $canEdit );
			return;
		}

		try {
			foreach ( $data as $field => $text ) {
				$text = ProfileData::validateExternalProfile(
					str_replace( 'link-', '', $field ),
					preg_replace( '/\s+\#/', '#', trim( $text ) )
				);
				if ( $text === false ) {
					$text = '';
				}
				if ( $profileData->getField( $field ) != $text ) {
					$profileData->setField( $field, $text, $this->getUser() );
				}
			}
			$this->getResult()->addValue( null, 'result', 'success' );
			$this->getResult()->addValue( null, 'parsedContent', $profileData->getProfileLinksHtml() );
			return;
		} catch ( MWException $e ) {
			$this->getResult()->addValue( null, 'result', 'failure' );
			$this->getResult()->addValue( null, 'errormsg', $e->getMessage() );
			return;
		}
	}
}
