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

		$wgOut->addHTML('<form id="aboutme" method="post">
				<label for="aboutme">'.wfMessage('aboutme')->plain().'</label>
				<textarea id="aboutme" name="aboutme" placeholder="'.wfMessage('aboutmeplaceholder')->plain().'">'.htmlspecialchars($profile->getAboutText()).'</textarea>
				<label for="favwiki">'.wfMessage('favoritewiki')->plain().'</label> <select name="fav_wiki" id="favwiki">'.$wikiOptions.'</select>
				<fieldset><legend>Location</legend>
					<label for="city">'.wfMessage('citylabel')->plain().'</label> <input type="text" name="city" id="city" value="'.$this->escquo($profile->getLocations()['city']).'"><br>
					<label for="state">'.wfMessage('statelabel')->plain().'</label> <input type="text" name="state" id="state" value="'.$this->escquo($profile->getLocations()['state']).'"><br>
					<label for="country">'.wfMessage('countrylabel')->plain().'</label> <input type="text" name="country" id="country" value="'.$this->escquo($profile->getLocations()['country']).'"><br>
				</fieldset>
				<fieldset><legend>Other Profiles</legend>
					<label for="steamlink">Steam</label> <input size="50" type="text" name="steam_link" id="steamlink" placeholder="'.wfMessage('steamlinkplaceholder')->plain().'" value="'.$this->escquo($profile->getProfileLinks()['Steam']).'"><br>
					<label for="xbllink">XBL</label> <input type="text" name="xbl_link" id="xbllink" placeholder="'.wfMessage('xbllinkplaceholder')->plain().'" value="'.$this->escquo($profile->getProfileLinks()['XBL']).'"><br>
					<label for="psnlink">PSN</label> <input type="text" name="psn_link" id="psnlink" placeholder="'.wfMessage('psnlinkplaceholder')->plain().'" value="'.$this->escquo($profile->getProfileLinks()['PSN']).'"><br>
				</fieldset>
			<input type="submit" value="Save">
			</form>');
	}

	private function escquo($str) {
		return htmlspecialchars($str, ENT_QUOTES);
	}
}
