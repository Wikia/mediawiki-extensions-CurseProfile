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

$magicWords = [];
$magicWords['en'] = [
						'cpGravatar'						=> array(0, 'cpGravatar'),
						'placeholderImage'					=> array(0, 'img'),
						'recentactivity'					=> array(0, 'recentactivity'),
						'friendadd'							=> array(0, 'friendadd'),
						'friendcount'						=> array(0, 'friendcount'),
						'friendlist'						=> array(0, 'friendlist'),
						'comments'							=> array(0, 'comments'),
						'avatar'							=> array(0, 'avatar'),
						'groups'							=> array(0, 'groups'),
						'aboutme'							=> array(0, 'aboutme'),
						'location'							=> array(0, 'location'),
						'profilelinks'						=> array(0, 'profilelinks'),
];

$messages = array();
$messages['en'] = array(
						'curseprofile'						=> 'Curse Profile',
						'curseprofile_description'			=> 'A modular, multi-featured user profile system.',
						'addfriend'							=> 'Add Friend',
						'ignorefriend'						=> 'Ignore Friend',
						'confirmfriend'						=> 'Confirm Friend',
						'emptyactivity'						=> 'This user hasn\'t made any edits on this wiki yet',
						'commentplaceholder'				=> 'Leave a comment',
						'editprofile'						=> 'Edit Profile',
						'aboutme'							=> 'About Me',
						'aboutmeplaceholder'				=> 'Write something about yourself!',
						'citylabel'							=> 'City',
						'statelabel'						=> 'State/Province',
						'countrylabel'						=> 'Country',
						'steamlink'							=> 'Steam',
						'xbllink'							=> 'Xbox Live',
						'psnlink'							=> 'Playstation Network',
						'steamlinkplaceholder'				=> 'a URL to your Steam account',
						'xbllinkplaceholder'				=> 'What\'s your gamertag?',
						'psnlinkplaceholder'				=> 'Share your PSN ID',
						'userprofilelayout'					=> '
<div class="curseprofile">
	<div class="leftcolumn">
		<div class="borderless section">
			{{#avatar: 160 | $3 | you | class="mainavatar"}}
			<div class="headline">
				{{#groups: $2}}
				<h1>$1</h1>
			</div>
			<div>
				{{#profilelinks: $2}}
				{{#location: $2}}
			</div>
			<div class="aboutme">
				{{#aboutme: $2}}
			</div>
		</div>
		<div class="activity section">
			<h3>Recent Wiki Activity</h3>
			{{#recentactivity: $2}}
		</div>
		<div class="comments section">
			<h3>Comments</h3>
			{{#comments: $2}}
		</div>
	</div>
	<div class="rightcolumn">
		<div class="rightfloat">
			<div class="title">Gamepedian</div>
			<div class="score">{{#Points: User:$1 | all | raw}} GP</div>
			{{#friendadd: $2}}
		</div>
		<div>Favorite Wiki
		{{#img:150|80}}
		</div>
		<div class="section">
			<h3>Total Statistics</h3>
			<dl>
				<dt>Wikis Edited</dt><dd>243</dd>
				<dt>Total Contributions</dt><dd>16,954
				<dl>
					<dt>Other Stats</dt><dd>15,003
					<dl>
						<dt>Might</dt><dd>10</dd>
						<dt>Go</dt><dd>20</dd>
						<dt>Here</dt><dd>30</dd>
					</dl></dd>
				</dl></dd>
				<dt>Friends</dt><dd>{{#friendcount: $2}}</dd>
			</dl>
			{{#friendlist: $2}}
		</div>
	</div>
</div>
__NOTOC__
'
);
