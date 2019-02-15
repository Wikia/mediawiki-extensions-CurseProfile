<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @author		Noah Manneschmidt
 * @copyright	(c) 2013 Curse Inc.
 * @license		GNU General Public License v2.0 or later
 * @package		CurseProfile
 * @link		https://gitlab.com/hydrawiki
 *
**/
namespace CurseProfile;

use ResourceLoaderContext;
use ResourceLoaderWikiModule;

/**
 * Module for site customization
 */
class ResourceLoaderModule extends ResourceLoaderWikiModule {

	/* Protected Methods */

	/**
	 * Gets list of pages used by this module
	 *
	 * @param ResourceLoaderContext $context
	 *
	 * @return Array List of pages
	 */
	protected function getPages(ResourceLoaderContext $context) {
		$pages = [
			'MediaWiki:CurseProfile.css' => [ 'type' => 'style' ]
		];
		return $pages;
	}

	/* Methods */

	/**
	 * Gets group name
	 *
	 * @return String Name of group
	 */
	public function getGroup() {
		return 'site';
	}
}
