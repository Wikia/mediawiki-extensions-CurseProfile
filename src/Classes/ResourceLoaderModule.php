<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @package   CurseProfile
 * @author    Noah Manneschmidt
 * @copyright (c) 2013 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki
 */

namespace CurseProfile\Classes;

use ResourceLoaderContext;
use ResourceLoaderWikiModule;

/**
 * Module for site customization
 */
class ResourceLoaderModule extends ResourceLoaderWikiModule {

	/** @inheritDoc */
	protected function getPages( ResourceLoaderContext $context ): array {
		return [ 'MediaWiki:CurseProfile.css' => [ 'type' => 'style' ] ];
	}

	/** @inheritDoc */
	public function getGroup() {
		return 'site';
	}
}
