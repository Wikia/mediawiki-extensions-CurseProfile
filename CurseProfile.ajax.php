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

// This file adds API classes to the registered API modules
$wgAPIModules['profile'] = 'CurseProfile\ProfileApi';
$wgAPIModules['friend'] = 'CurseProfile\FriendApi';
$wgAPIModules['comment'] = 'CurseProfile\CommentApi';
