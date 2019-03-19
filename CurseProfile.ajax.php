<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @author    Noah Manneschmidt
 * @copyright (c) 2013 Curse Inc.
 * @license   GNU General Public License v2.0 or later
 * @package   CurseProfile
 * @link      https://gitlab.com/hydrawiki
**/

// This file adds API classes to the registered API modules
$wgAPIModules['profile'] = 'CurseProfile\ProfileApi';
$wgAPIModules['friend'] = 'CurseProfile\FriendApi';
$wgAPIModules['comment'] = 'CurseProfile\CommentApi';
