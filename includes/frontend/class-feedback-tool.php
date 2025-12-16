<?php
/**
 * Frontend Feedback Tool
 *
 * @package RAS_Website_Feedback
 */

defined( 'ABSPATH' ) || exit;

/**
 * Frontend feedback tool loader
 */
class RAS_WF_Feedback_Tool {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ), 999 );
        add_action( 'wp_footer', array( $this, 'maybe_render_tool' ), 999 );
    }

    /**
     * Check if tool should load and enqueue assets
     */
    public function maybe_enqueue_assets() {
        if ( ! $this->should_load_tool() ) {
            return;
        }

        // Load styles
        wp_enqueue_style(
            'ras-wf-frontend',
            RAS_WF_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            RAS_WF_VERSION
        );

        // Load script in footer with defer for performance
        wp_enqueue_script(
            'ras-wf-frontend',
            RAS_WF_PLUGIN_URL . 'assets/js/frontend.js',
            array(), // No jQuery dependency for performance
            RAS_WF_VERSION,
            array(
                'strategy' => 'defer',
                'in_footer' => true,
            )
        );

        // Localize script data
        $page_path = wp_parse_url( esc_url_raw( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH ) ?: '/';
        // Strip query string
        $page_path = strtok( $page_path, '?' );

        $feedback_count = RAS_WF_CPT_Feedback::get_page_count( $page_path, true );

        wp_localize_script( 'ras-wf-frontend', 'rasWfFrontend', array(
            'ajaxurl'       => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'ras_wf_frontend_nonce' ),
            'pageUrl'       => esc_url_raw( home_url( $_SERVER['REQUEST_URI'] ?? '/' ) ),
            'pagePath'      => $page_path,
            'feedbackCount' => $feedback_count,
            'isGuest'       => RAS_WF_User_Access::is_guest(),
            'userId'        => get_current_user_id(),
            'userName'      => is_user_logged_in() ? wp_get_current_user()->display_name : '',
            'i18n'          => array(
                'feedbackButton'    => __( 'Feedback', 'ras-website-feedback' ),
                'selectElement'     => __( 'Click on an element to add feedback', 'ras-website-feedback' ),
                'cancel'            => __( 'Cancel', 'ras-website-feedback' ),
                'submit'            => __( 'Submit', 'ras-website-feedback' ),
                'submitting'        => __( 'Submitting...', 'ras-website-feedback' ),
                'reply'             => __( 'Reply', 'ras-website-feedback' ),
                'addReply'          => __( 'Add Reply', 'ras-website-feedback' ),
                'yourName'          => __( 'Your Name', 'ras-website-feedback' ),
                'yourFeedback'      => __( 'Your feedback...', 'ras-website-feedback' ),
                'noFeedback'        => __( 'No feedback for this page yet.', 'ras-website-feedback' ),
                'feedbackFor'       => __( 'Feedback for this page', 'ras-website-feedback' ),
                'unresolved'        => __( 'Unresolved', 'ras-website-feedback' ),
                'pending'           => __( 'Pending', 'ras-website-feedback' ),
                'resolved'          => __( 'Resolved', 'ras-website-feedback' ),
                'close'             => __( 'Close', 'ras-website-feedback' ),
                'errorSubmit'       => __( 'Failed to submit feedback. Please try again.', 'ras-website-feedback' ),
                'successSubmit'     => __( 'Feedback submitted successfully!', 'ras-website-feedback' ),
                'pressEscToCancel'  => __( 'Press ESC to cancel', 'ras-website-feedback' ),
                'replies'           => __( 'Replies', 'ras-website-feedback' ),
                'viewOnPage'        => __( 'View on page', 'ras-website-feedback' ),
            ),
        ) );
    }

    /**
     * Check if tool should load for current user
     *
     * @return bool
     */
    private function should_load_tool() {
        // Don't load in admin
        if ( is_admin() ) {
            return false;
        }

        // Don't load on login/register pages
        if ( in_array( $GLOBALS['pagenow'] ?? '', array( 'wp-login.php', 'wp-register.php' ), true ) ) {
            return false;
        }

        return RAS_WF_User_Access::can_current_user_access();
    }

    /**
     * Render feedback tool container in footer
     */
    public function maybe_render_tool() {
        if ( ! $this->should_load_tool() ) {
            return;
        }

        // Output minimal HTML container - JS will build the UI
        ?>
        <div id="ras-wf-tool" class="ras-wf-tool" aria-hidden="true"></div>
        <?php
    }
}
