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

class SpecialEditProfile extends \UnlistedSpecialPage {
	public function __construct() {
		parent::__construct( 'EditProfile' );
	}

	/**
	 * Show the special page
	 */
	public function execute($subPage) {
		global $wgUser;
		$wgRequest = $this->getRequest();
		$wgOut = $this->getOutput();
		$wgOut->addModules('ext.curseprofile.forms');

		$profile = new ProfileData($wgUser->getId());

		$this->setHeaders();

		if ($wgRequest->wasPosted()) {
			//confirm form, do redirect
			$data = [
				'aboutme' => $wgRequest->getVal('aboutme'),
				'city' => $wgRequest->getVal('city'),
				'state' => $wgRequest->getVal('state'),
				'country' => $wgRequest->getVal('country'),
				'fav_wiki' => $wgRequest->getVal('fav_wiki'),
				'steam_link' => $wgRequest->getVal('steam_link'),
				'xbl_link' => $wgRequest->getVal('xbl_link'),
				'psn_link' => $wgRequest->getVal('psn_link'),
			];
			$profile->save($data);
			$wgOut->redirect($wgUser->getUserPage()->getFullURL());
			return;
		}

		// Get list of options for favorite wiki
		global $wgServer;
		$mouse = CP::loadMouse(['curl'=>'mouseTransferCurl']);
		$rawSites = $mouse->curl->fetch($wgServer.'/extensions/AllSites/api.php?action=siteInformation&task=getSiteStats');
		$sites = json_decode($rawSites, true);
		$wikiOptions = ['<option value="">---</option>'];
		foreach ($sites['data']['wikis'] as $wiki) {
			if ($wiki['md5_key'] == $profile->getFavoriteWikiHash()) {
				$sel = 'selected';
			} else {
				$sel = '';
			}
			$wikiOptions[] = "<option $sel value='{$wiki['md5_key']}'>{$wiki['wiki_name']}</option>";
		}
		$wikiOptions = implode("\n", $wikiOptions);

		$profilePrefOptions = [
			'<option value="1">Use an enhanced profile page</option>',
			'<option value="0">Use a simple wiki page</option>',
		];
		$profilePrefOptions = implode("\n", $profilePrefOptions);

		$wgOut->addHTML('<form id="aboutme" method="post">
				<label for="aboutme">'.wfMessage('aboutme')->escaped().'</label>
				<textarea id="aboutme" name="aboutme" placeholder="'.wfMessage('aboutmeplaceholder')->escaped().'">'.htmlspecialchars($profile->getAboutText()).'</textarea>
				<label for="favwiki">'.wfMessage('favoritewiki')->escaped().'</label> <select name="fav_wiki" id="favwiki">'.$wikiOptions.'</select><br>
				<label for="profilepref">'.wfMessage('profileprefselect')->escaped().'</label> <select name="profile_type" id="profilepref">'.$profilePrefOptions.'</select>
				<fieldset><legend>Location</legend>
					<label for="city">'.wfMessage('citylabel')->escaped().'</label> <input type="text" name="city" id="city" value="'.$this->escquo($profile->getLocations()['city']).'"><br>
					<label for="state">'.wfMessage('statelabel')->escaped().'</label> <input type="text" name="state" id="state" value="'.$this->escquo($profile->getLocations()['state']).'"><br>
					<label for="country">'.wfMessage('countrylabel')->escaped().'</label> <input type="text" name="country" id="country" value="'.$this->escquo($profile->getLocations()['country']).'"><br>
				</fieldset>
				<fieldset><legend>Other Profiles</legend>
					<label for="steamlink">Steam</label> <input size="50" type="text" name="steam_link" id="steamlink" placeholder="'.wfMessage('steamlinkplaceholder')->escaped().'" value="'.$this->escquo($profile->getProfileLinks()['Steam']).'"><br>
					<label for="xbllink">XBL</label> <input type="text" name="xbl_link" id="xbllink" placeholder="'.wfMessage('xbllinkplaceholder')->escaped().'" value="'.$this->escquo($profile->getProfileLinks()['XBL']).'"><br>
					<label for="psnlink">PSN</label> <input type="text" name="psn_link" id="psnlink" placeholder="'.wfMessage('psnlinkplaceholder')->escaped().'" value="'.$this->escquo($profile->getProfileLinks()['PSN']).'"><br>
				</fieldset>
			<input type="submit" value="Save">
			</form>');
	}

	private function escquo($str) {
		return htmlspecialchars($str, ENT_QUOTES);
	}
}
