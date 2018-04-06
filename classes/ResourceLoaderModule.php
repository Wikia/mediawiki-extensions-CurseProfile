<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @author		Noah Manneschmidt
 * @copyright	(c) 2013 Curse Inc.
 * @license		All Rights Reserved
 * @package		CurseProfile
 * @link		http://www.curse.com/
 *
**/
namespace CurseProfile;

/**
 * Module for site customizations
 */
class ResourceLoaderModule extends \ResourceLoaderWikiModule {

	/* Protected Methods */

	/**
	 * Gets list of pages used by this module
	 *
	 * @param $context ResourceLoaderContext
	 *
	 * @return Array: List of pages
	 */
	protected function getPages( \ResourceLoaderContext $context ) {
		$pages = array(
			'MediaWiki:CurseProfile.css' => array( 'type' => 'style' )
		);
		return $pages;
	}

	/* Methods */

	/**
	 * Gets group name
	 *
	 * @return String: Name of group
	 */
	public function getGroup() {
		return 'site';
	}
}
