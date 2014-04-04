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

// This file registers ajax functions that should be made available though action=ajax

// Comment boards
$wgAjaxExportList[] = 'CurseProfile\CommentDisplay::repliesTo';

$wgAPIModules['friend'] = 'CurseProfile\FriendApi';
$wgAPIModules['comment'] = 'CurseProfile\CommentApi';
