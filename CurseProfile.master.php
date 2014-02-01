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

// This file is included only by the master wiki and adds extra functionality,
// and can add extra functionality as necessary.

define('CURSEPROFILE_MASTER', true);
require_once 'CurseProfile.php';

$extSyncServices[] = 'CurseProfile\FriendSync';
