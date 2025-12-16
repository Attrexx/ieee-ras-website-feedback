<?php
/**
 * AJAX Handler
 *
 * @package RAS_Website_Feedback
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handle all AJAX requests
 */
class RAS_WF_Ajax_Handler {

    /**
     * Constructor - register AJAX actions
     */
    public function __construct() {
        // Frontend AJAX (logged in and not logged in)
        add_action( 'wp_ajax_ras_wf_submit_feedback', array( $this, 'submit_feedback' ) );
        add_action( 'wp_ajax_nopriv_ras_wf_submit_feedback', array( $this, 'submit_feedback' ) );

        add_action( 'wp_ajax_ras_wf_get_page_feedbacks', array( $this, 'get_page_feedbacks' ) );
        add_action( 'wp_ajax_nopriv_ras_wf_get_page_feedbacks', array( $this, 'get_page_feedbacks' ) );

        add_action( 'wp_ajax_ras_wf_add_reply', array( $this, 'add_reply' ) );
        add_action( 'wp_ajax_nopriv_ras_wf_add_reply', array( $this, 'add_reply' ) );

        add_action( 'wp_ajax_ras_wf_get_replies', array( $this, 'get_replies' ) );
        add_action( 'wp_ajax_nopriv_ras_wf_get_replies', array( $this, 'get_replies' ) );

        // Admin AJAX
        add_action( 'wp_ajax_ras_wf_update_status', array( $this, 'update_status' ) );
        add_action( 'wp_ajax_ras_wf_delete_feedback', array( $this, 'delete_feedback' ) );
        add_action( 'wp_ajax_ras_wf_search_users', array( $this, 'search_users' ) );
        add_action( 'wp_ajax_ras_wf_toggle_user', array( $this, 'toggle_user' ) );
        add_action( 'wp_ajax_ras_wf_save_settings', array( $this, 'save_settings' ) );
        add_action( 'wp_ajax_ras_wf_regenerate_token', array( $this, 'regenerate_token' ) );
        add_action( 'wp_ajax_ras_wf_update_notification_pref', array( $this, 'update_notification_preference' ) );
    }

    /**
     * Verify frontend nonce and access
     *
     * @return bool
     */
    private function verify_frontend_access() {
        if ( ! check_ajax_referer( 'ras_wf_frontend_nonce', 'nonce', false ) ) {
            return false;
        }

        return RAS_WF_User_Access::can_current_user_access();
    }

    /**
     * Verify admin nonce and capability
     *
     * @return bool
     */
    private function verify_admin_access() {
        if ( ! check_ajax_referer( 'ras_wf_admin_nonce', 'nonce', false ) ) {
            return false;
        }

        return current_user_can( 'manage_options' );
    }

    /**
     * Submit new feedback
     */
    public function submit_feedback() {
        if ( ! $this->verify_frontend_access() ) {
            wp_send_json_error( array( 'message' => __( 'Access denied.', 'ras-website-feedback' ) ) );
        }

        // Validate required fields
        $required = array( 'page_url', 'click_x', 'click_y', 'window_width', 'window_height', 'element_selector', 'comment' );

        foreach ( $required as $field ) {
            if ( ! isset( $_POST[ $field ] ) || ( '' === $_POST[ $field ] && 'element_selector' !== $field ) ) {
                wp_send_json_error( array(
                    'message' => sprintf(
                        /* translators: %s: field name */
                        __( 'Missing required field: %s', 'ras-website-feedback' ),
                        $field
                    ),
                ) );
            }
        }

        $data = array(
            'page_url'         => esc_url_raw( wp_unslash( $_POST['page_url'] ) ),
            'click_x'          => absint( $_POST['click_x'] ),
            'click_y'          => absint( $_POST['click_y'] ),
            'window_width'     => absint( $_POST['window_width'] ),
            'window_height'    => absint( $_POST['window_height'] ),
            'element_selector' => sanitize_text_field( wp_unslash( $_POST['element_selector'] ) ),
            'element_html'     => isset( $_POST['element_html'] ) ? wp_kses_post( wp_unslash( $_POST['element_html'] ) ) : '',
            'comment'          => sanitize_textarea_field( wp_unslash( $_POST['comment'] ) ),
            'user_id'          => get_current_user_id(),
            'guest_name'       => isset( $_POST['guest_name'] ) ? sanitize_text_field( wp_unslash( $_POST['guest_name'] ) ) : '',
        );

        $feedback_id = RAS_WF_CPT_Feedback::create_feedback( $data );

        if ( is_wp_error( $feedback_id ) ) {
            wp_send_json_error( array( 'message' => $feedback_id->get_error_message() ) );
        }

        // Send notifications
        $actor_name = get_current_user_id()
            ? wp_get_current_user()->display_name
            : ( ! empty( $data['guest_name'] ) ? $data['guest_name'] : __( 'Guest', 'ras-website-feedback' ) );

        $notifications = ras_website_feedback()->get_module( 'notifications' );
        if ( $notifications ) {
            $notifications->notify_new_feedback( $feedback_id, $actor_name );
        }

        // Get the created feedback for response
        $post     = get_post( $feedback_id );
        $feedback = RAS_WF_CPT_Feedback::format_feedback( $post );

        wp_send_json_success( array(
            'message'  => __( 'Feedback submitted successfully!', 'ras-website-feedback' ),
            'feedback' => $feedback,
        ) );
    }

    /**
     * Get feedbacks for a specific page
     */
    public function get_page_feedbacks() {
        if ( ! $this->verify_frontend_access() ) {
            wp_send_json_error( array( 'message' => __( 'Access denied.', 'ras-website-feedback' ) ) );
        }

        if ( empty( $_POST['page_path'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Page path required.', 'ras-website-feedback' ) ) );
        }

        $page_path = sanitize_text_field( wp_unslash( $_POST['page_path'] ) );
        $feedbacks = RAS_WF_CPT_Feedback::get_by_page( $page_path );

        // Group by status
        $grouped = array(
            'unresolved' => array(),
            'pending'    => array(),
            'resolved'   => array(),
        );

        foreach ( $feedbacks as $feedback ) {
            $status = $feedback['status'] ?: 'unresolved';
            if ( isset( $grouped[ $status ] ) ) {
                $grouped[ $status ][] = $feedback;
            }
        }

        wp_send_json_success( array(
            'feedbacks' => $feedbacks,
            'grouped'   => $grouped,
            'counts'    => array(
                'unresolved' => count( $grouped['unresolved'] ),
                'pending'    => count( $grouped['pending'] ),
                'resolved'   => count( $grouped['resolved'] ),
                'total'      => count( $feedbacks ),
            ),
        ) );
    }

    /**
     * Add reply to feedback
     */
    public function add_reply() {
        if ( ! $this->verify_frontend_access() ) {
            wp_send_json_error( array( 'message' => __( 'Access denied.', 'ras-website-feedback' ) ) );
        }

        if ( empty( $_POST['feedback_id'] ) || empty( $_POST['content'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Feedback ID and content required.', 'ras-website-feedback' ) ) );
        }

        $feedback_id = absint( $_POST['feedback_id'] );
        $content     = sanitize_textarea_field( wp_unslash( $_POST['content'] ) );
        $user_id     = get_current_user_id();
        $guest_name  = isset( $_POST['guest_name'] ) ? sanitize_text_field( wp_unslash( $_POST['guest_name'] ) ) : '';

        // Verify feedback exists
        $feedback = get_post( $feedback_id );
        if ( ! $feedback || RAS_WF_CPT_Feedback::POST_TYPE !== $feedback->post_type ) {
            wp_send_json_error( array( 'message' => __( 'Feedback not found.', 'ras-website-feedback' ) ) );
        }

        $reply_id = RAS_WF_Database::add_reply( $feedback_id, $content, $user_id, $guest_name );

        if ( ! $reply_id ) {
            wp_send_json_error( array( 'message' => __( 'Failed to add reply.', 'ras-website-feedback' ) ) );
        }

        // Send notifications
        $actor_name = $user_id
            ? get_user_by( 'ID', $user_id )->display_name
            : ( $guest_name ?: __( 'Guest', 'ras-website-feedback' ) );

        $notifications = ras_website_feedback()->get_module( 'notifications' );
        if ( $notifications ) {
            $notifications->notify_new_reply( $feedback_id, $reply_id, $actor_name );
        }

        // Get all replies
        $replies = RAS_WF_Database::get_replies( $feedback_id );

        wp_send_json_success( array(
            'message' => __( 'Reply added successfully!', 'ras-website-feedback' ),
            'replies' => $replies,
        ) );
    }

    /**
     * Get replies for a feedback
     */
    public function get_replies() {
        // Allow both frontend and admin access
        $is_frontend = check_ajax_referer( 'ras_wf_frontend_nonce', 'nonce', false );
        $is_admin    = check_ajax_referer( 'ras_wf_admin_nonce', 'nonce', false );

        if ( ! $is_frontend && ! $is_admin ) {
            wp_send_json_error( array( 'message' => __( 'Access denied.', 'ras-website-feedback' ) ) );
        }

        // For frontend, verify access
        if ( $is_frontend && ! RAS_WF_User_Access::can_current_user_access() ) {
            wp_send_json_error( array( 'message' => __( 'Access denied.', 'ras-website-feedback' ) ) );
        }

        if ( empty( $_POST['feedback_id'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Feedback ID required.', 'ras-website-feedback' ) ) );
        }

        $feedback_id = absint( $_POST['feedback_id'] );
        $replies     = RAS_WF_Database::get_replies( $feedback_id );

        wp_send_json_success( array( 'replies' => $replies ) );
    }

    /**
     * Update feedback status (admin only)
     */
    public function update_status() {
        if ( ! $this->verify_admin_access() ) {
            wp_send_json_error( array( 'message' => __( 'Access denied.', 'ras-website-feedback' ) ) );
        }

        if ( empty( $_POST['feedback_id'] ) || empty( $_POST['status'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Feedback ID and status required.', 'ras-website-feedback' ) ) );
        }

        $feedback_id = absint( $_POST['feedback_id'] );
        $status      = sanitize_key( $_POST['status'] );

        // Get old status for notification
        $old_status = get_post_meta( $feedback_id, '_ras_wf_status', true ) ?: 'unresolved';

        $result = RAS_WF_CPT_Feedback::update_status( $feedback_id, $status );

        if ( ! $result ) {
            wp_send_json_error( array( 'message' => __( 'Failed to update status.', 'ras-website-feedback' ) ) );
        }

        // Send notifications for status change
        if ( $old_status !== $status ) {
            $actor_name    = wp_get_current_user()->display_name;
            $notifications = ras_website_feedback()->get_module( 'notifications' );
            if ( $notifications ) {
                $notifications->notify_status_change( $feedback_id, $old_status, $status, $actor_name );
            }
        }

        // Get updated counts
        $counts = RAS_WF_CPT_Feedback::get_status_counts();

        wp_send_json_success( array(
            'message' => __( 'Status updated.', 'ras-website-feedback' ),
            'counts'  => $counts,
        ) );
    }

    /**
     * Delete feedback (admin only)
     */
    public function delete_feedback() {
        if ( ! $this->verify_admin_access() ) {
            wp_send_json_error( array( 'message' => __( 'Access denied.', 'ras-website-feedback' ) ) );
        }

        if ( empty( $_POST['feedback_id'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Feedback ID required.', 'ras-website-feedback' ) ) );
        }

        $feedback_id = absint( $_POST['feedback_id'] );

        // Verify it's a feedback post
        $feedback = get_post( $feedback_id );
        if ( ! $feedback || RAS_WF_CPT_Feedback::POST_TYPE !== $feedback->post_type ) {
            wp_send_json_error( array( 'message' => __( 'Feedback not found.', 'ras-website-feedback' ) ) );
        }

        // Delete (replies are cleaned up via hook)
        $result = wp_delete_post( $feedback_id, true );

        if ( ! $result ) {
            wp_send_json_error( array( 'message' => __( 'Failed to delete feedback.', 'ras-website-feedback' ) ) );
        }

        // Get updated counts
        $counts = RAS_WF_CPT_Feedback::get_status_counts();

        wp_send_json_success( array(
            'message' => __( 'Feedback deleted.', 'ras-website-feedback' ),
            'counts'  => $counts,
        ) );
    }

    /**
     * Search users (admin only)
     */
    public function search_users() {
        if ( ! $this->verify_admin_access() ) {
            wp_send_json_error( array( 'message' => __( 'Access denied.', 'ras-website-feedback' ) ) );
        }

        $search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
        $users  = RAS_WF_User_Access::search_users( $search, 20 );

        wp_send_json_success( array( 'users' => $users ) );
    }

    /**
     * Toggle user access (admin only)
     */
    public function toggle_user() {
        if ( ! $this->verify_admin_access() ) {
            wp_send_json_error( array( 'message' => __( 'Access denied.', 'ras-website-feedback' ) ) );
        }

        if ( empty( $_POST['user_id'] ) || ! isset( $_POST['enabled'] ) ) {
            wp_send_json_error( array( 'message' => __( 'User ID and enabled status required.', 'ras-website-feedback' ) ) );
        }

        $user_id = absint( $_POST['user_id'] );
        $enabled = filter_var( $_POST['enabled'], FILTER_VALIDATE_BOOLEAN );

        if ( $enabled ) {
            $result = RAS_WF_User_Access::enable_user( $user_id );
        } else {
            $result = RAS_WF_User_Access::disable_user( $user_id );
        }

        if ( ! $result ) {
            wp_send_json_error( array( 'message' => __( 'Failed to update user access.', 'ras-website-feedback' ) ) );
        }

        wp_send_json_success( array(
            'message' => $enabled
                ? __( 'User enabled for feedback.', 'ras-website-feedback' )
                : __( 'User removed from feedback.', 'ras-website-feedback' ),
        ) );
    }

    /**
     * Save settings (admin only)
     */
    public function save_settings() {
        if ( ! $this->verify_admin_access() ) {
            wp_send_json_error( array( 'message' => __( 'Access denied.', 'ras-website-feedback' ) ) );
        }

        $guest_enabled = isset( $_POST['guest_enabled'] ) && filter_var( $_POST['guest_enabled'], FILTER_VALIDATE_BOOLEAN );
        RAS_WF_User_Access::set_guest_access( $guest_enabled );

        wp_send_json_success( array(
            'message'   => __( 'Settings saved.', 'ras-website-feedback' ),
            'guest_url' => RAS_WF_User_Access::get_guest_url(),
        ) );
    }

    /**
     * Regenerate guest token (admin only)
     */
    public function regenerate_token() {
        if ( ! $this->verify_admin_access() ) {
            wp_send_json_error( array( 'message' => __( 'Access denied.', 'ras-website-feedback' ) ) );
        }

        $new_token = RAS_WF_User_Access::regenerate_guest_token();

        wp_send_json_success( array(
            'message'   => __( 'Token regenerated. All existing guest links are now invalid.', 'ras-website-feedback' ),
            'token'     => $new_token,
            'guest_url' => RAS_WF_User_Access::get_guest_url(),
            'param'     => RAS_WF_GUEST_PARAM . '=' . $new_token,
        ) );
    }

    /**
     * Update notification preference for a user (admin only)
     */
    public function update_notification_preference() {
        if ( ! $this->verify_admin_access() ) {
            wp_send_json_error( array( 'message' => __( 'Access denied.', 'ras-website-feedback' ) ) );
        }

        if ( empty( $_POST['user_id'] ) || ! isset( $_POST['mode'] ) ) {
            wp_send_json_error( array( 'message' => __( 'User ID and notification mode required.', 'ras-website-feedback' ) ) );
        }

        $user_id = absint( $_POST['user_id'] );
        $mode    = sanitize_key( $_POST['mode'] );

        // Validate mode
        $valid_modes = array(
            RAS_WF_Email_Notifications::MODE_LIVE,
            RAS_WF_Email_Notifications::MODE_DIGEST,
            RAS_WF_Email_Notifications::MODE_OFF,
        );

        if ( ! in_array( $mode, $valid_modes, true ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid notification mode.', 'ras-website-feedback' ) ) );
        }

        $notifications = ras_website_feedback()->get_module( 'notifications' );
        if ( ! $notifications ) {
            wp_send_json_error( array( 'message' => __( 'Notifications module not available.', 'ras-website-feedback' ) ) );
        }

        $notifications->set_user_preference( $user_id, $mode );

        $mode_labels = array(
            RAS_WF_Email_Notifications::MODE_LIVE   => __( 'Live emails', 'ras-website-feedback' ),
            RAS_WF_Email_Notifications::MODE_DIGEST => __( 'Daily digest', 'ras-website-feedback' ),
            RAS_WF_Email_Notifications::MODE_OFF    => __( 'Off', 'ras-website-feedback' ),
        );

        wp_send_json_success( array(
            'message' => sprintf(
                /* translators: %s: notification mode */
                __( 'Notification preference updated to: %s', 'ras-website-feedback' ),
                $mode_labels[ $mode ]
            ),
        ) );
    }
}
