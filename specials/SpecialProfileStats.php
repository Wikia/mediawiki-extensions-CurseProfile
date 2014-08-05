<?php
/**
 * Curse Inc.
 * Curse Profile
 * Display stats on 
 *
 * @author		Noah Manneschmidt
 * @copyright	(c) 2014 Curse Inc.
 * @license		All Rights Reserved
 * @package		CurseProfile
 * @link		http://www.curse.com/
 *
**/
namespace CurseProfile;

class SpecialProfileStats extends \SpecialPage {
	public function __construct() {
		parent::__construct( 'ProfileStats' );

		global $wgRequest, $wgUser, $wgOut;
		$this->wgRequest	= $wgRequest;
		$this->wgUser		= $wgUser;
		$this->output		= $this->getOutput();
	}

	public function execute( $path ) {
		$this->setHeaders();
		if (!$this->wgUser->isAllowed('profilestats')) {
			throw new \PermissionsError('profilestats');
		}

		$this->mouse = \mouseNest::getMouse();
		$this->mouse->output->addTemplateFolder(HA_EXT_DIR.'/templates');
		$this->mouse->output->loadTemplate('hydralytics');

		// get primary adoption stats
		$this->users['profile'] = 5000;
		$this->users['wiki'] = 1000;

		// get friending stats
		$this->friends['none'] = 3500;
		$this->friends['more'] = 1500;
		$this->avgFriends = 2;

		// get customization stats
		$this->profileContent['empty'] = 3000;
		$this->profileContent['filled'] = 2000;

		// get favorite wiki stats
		$this->favoriteWikis['help.gamepedia.com'] = 1500;
		$this->favoriteWikis['minecraft.gamepedia.com'] = 2500;
		$this->favoriteWikis['lol.gamepedia.com'] = 500;
		$this->favoriteWikis['wowpedia.org'] = 300;
		$this->favoriteWikis['dota2.gamepedia.com'] = 200;

		$this->output->addModules('ext.curseprofile.profilestats');
		$this->output->addHTML($this->buildOutput());
	}

	public function isListed() {
		return $this->wgUser->isAllowed('profilestats');
	}

	public function isRestricted() {
		return true;
	}

	private function buildOutput() {
		$HTML = '';

		$HTML .= 'Output a chart and tables!';

		return $HTML;
	}
}
