<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @package   CurseProfile
 * @author    Noah Manneschmidt
 * @copyright (c) 2014 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki
 **/

namespace CurseProfile\Templates;

use Html;
use SpecialPage;
use CurseProfile\Classes\CommentDisplay;
use CurseProfile\Classes\ProfileData;
use User;

class TemplateCommentBoard {
    /**
     * Output HTML
     *
     * @var string
     */
    private $HTML;

    /**
     * Header for comments archive board
     *
     * @param object $user user reference
     * @param string $title text title of the page
     * @return string    Built HTML
     */
    public function header( $user, $title ) {
        return '<p>' .
            Html::element( 'a', [ 'href' => ( new ProfileData( $user->getId() ) )->getProfilePageUrl() ], wfMessage( 'commentboard-link-backtoprofile' ) ) .
            '</p>';
    }

    /**
     * Header for single comment permalink page
     *
     * @param object $user
     * @param string $title
     * @return string
     */
    public function permalinkHeader( $user, $title ) {
        return '<p>' .
            Html::element( 'a', [ 'href' => ( new ProfileData( $user->getId() ) )->getProfilePageUrl() ], wfMessage( 'commentboard-link-backtoprofile' ) ) .
            ' | ' .
            Html::element( 'a', [ 'href' => SpecialPage::getTitleFor( 'CommentBoard', $user->getId() )->getFullURL() ], wfMessage( 'commentboard-link-backtoboard' ) ) .
            '</p>';
    }

    /**
     * Comments display.
     *
     * @param array $comments Array of comments
     * @param User $user User instance to who this comment list belongs to.
     * @param string $pagination [Optional] Built HTML fragment for pagination.
     *
     * @return string Built HTML
     */
    public function comments( $comments, User $user, $pagination = '' ) {
        $this->HTML = '';
        $this->HTML .= '<div>' . $pagination . '</div>';

        $this->HTML .= '<div class="comments curseprofile" data-user_id="' . $user->getId() . '">';

        // add hidden compose form, to support replies
        $this->HTML .= CommentDisplay::newCommentForm( $user, true );

        foreach ( $comments as $comment ) {
            $this->HTML .= CommentDisplay::singleComment( $comment, false );
        }

        $this->HTML .= '</div>';

        $this->HTML .= '<div>' . $pagination . '</div>';
        return $this->HTML;
    }
}
