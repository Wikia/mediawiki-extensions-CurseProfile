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
**/

// This file adds API classes to the registered API modules
$wgAPIModules['profile'] = 'CurseProfile\ProfileApi';
$wgAPIModules['friend'] = 'CurseProfile\FriendApi';
$wgAPIModules['comment'] = 'CurseProfile\CommentApi';
