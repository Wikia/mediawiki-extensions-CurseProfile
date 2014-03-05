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

/**
 * An abstract class that is not a special page in its own right.
 * This holds some simple logic for a page that simply asks the
 * user to confirm taking an action before redirecting them elsewhere.
 */
abstract class SpecialConfirmAction extends \UnlistedSpecialPage {
	/**
	 * Returns a string which will be shown to the user when they are asked to confirm
	 * wfMessage('friendrequestsend-prompt', $user->getName())->plain()
	 */
	abstract protected function getConfirmMessage();

	/**
	 * Returns a string which will be used as the text of the confirm button
	 * wfMessage('friendrequestsend')->plain()
	 */
	abstract protected function getButtonMessage();

	/**
	 * Returns a string path to which the user will be directed
	 * '/User:'.urlencode($user->getName())
	 */
	protected function getRedirect() {
		return '/Special:ManageFriends';
	}

	/**
	 * Called when a user has submitted confirmation
	 */
	abstract public function confirm($formData);

	/**
	 * Show the special page
	 *
	 * @param $params Mixed: parameter(s) passed to the page or null
	 */
	public function execute( $param ) {
		$this->setHeaders();
		$wgRequest = $this->getRequest();
		$wgOut = $this->getOutput();
		$wgOut->setArticleRelated( false );
		$wgOut->setRobotPolicy( 'noindex,nofollow' );

		$htmlForm = new \HTMLForm([], $this->getContext());
		$htmlForm->setIntro($this->getConfirmMessage());
		$htmlForm->addHiddenField('param', $param);
		$htmlForm->setSubmitText($this->getButtonMessage());
		$htmlForm->setSubmitCallback([$this, 'confirm']);
		if ($htmlForm->show()) {
			$wgOut->redirect($this->getRedirect());
		}
	}
}
