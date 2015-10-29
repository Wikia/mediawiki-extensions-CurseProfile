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

// This file is included only by the master wiki and adds extra functionality

define('CURSEPROFILE_MASTER', true);
require_once('CurseProfile.php');

$extSyncServices[] = 'CurseProfile\FriendSync';
$extSyncServices[] = 'CurseProfile\StatsRecache';
$extSyncServices[] = 'CurseProfile\ResolveComment';

$wgAutoloadClasses['CurseProfile\StatsRecache'] = __DIR__.'/classes/jobs/StatsRecache.php';

$wgAutoloadClasses['CurseProfile\SpecialProfileStats']		= __DIR__."/specials/SpecialProfileStats.php";
$wgSpecialPages['ProfileStats']								= 'CurseProfile\SpecialProfileStats';

// Resource modules
$wgResourceModules['ext.curseprofile.profilestats'] = [
	'styles' => ['css/profilestats.css'],
	'scripts' => ['js/profilestats.js'],
	'localBasePath' => __DIR__.'/',
	'remoteExtPath' => 'CurseProfile',
	'dependencies' => ['jquery.timeago', 'highcharts'],
];
