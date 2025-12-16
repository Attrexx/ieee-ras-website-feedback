<?php
/**
 * Database handler for feedback plugin
 *
 * @package RAS_Website_Feedback
 */

defined( 'ABSPATH' ) || exit;

/**
 * Database class - handles replies table creation
 */
class RAS_WF_Database {

    /**
     * Replies table name (without prefix)
     */
    const REPLIES_TABLE = 'ras_wf_replies';

    /**
     * Constructor
     */
    public function __construct() {
        // Nothing needed here for now
    }

    /**
     * Get full table name with prefix
     *
     * @param string $table Table name without prefix.
     * @return string
     */
    public static function get_table_name( $table ) {
        global $wpdb;
        return $wpdb->prefix . $table;
    }

    /**
     * Create custom tables on activation
     */
    public function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $replies_table   = self::get_table_name( self::REPLIES_TABLE );

        $sql = "CREATE TABLE {$replies_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            feedback_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            guest_name VARCHAR(100) NULL,
            reply_content TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_feedback (feedback_id),
            INDEX idx_user (user_id),
            INDEX idx_created (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Store table version
        update_option( 'ras_wf_db_version', '1.0.0' );
    }

    /**
     * Get replies for a feedback post
     *
     * @param int $feedback_id Feedback post ID.
     * @return array
     */
    public static function get_replies( $feedback_id ) {
        global $wpdb;

        $table = self::get_table_name( self::REPLIES_TABLE );

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE feedback_id = %d ORDER BY created_at ASC",
                $feedback_id
            ),
            ARRAY_A
        );

        // Add user display names
        foreach ( $results as &$reply ) {
            if ( ! empty( $reply['user_id'] ) ) {
                $user = get_user_by( 'ID', $reply['user_id'] );
                $reply['display_name'] = $user ? $user->display_name : __( 'Unknown User', 'ras-website-feedback' );
            } else {
                $reply['display_name'] = ! empty( $reply['guest_name'] ) ? $reply['guest_name'] : __( 'Guest', 'ras-website-feedback' );
            }
        }

        return $results;
    }

    /**
     * Add a reply to a feedback
     *
     * @param int    $feedback_id Feedback post ID.
     * @param string $content     Reply content.
     * @param int    $user_id     User ID (0 for guests).
     * @param string $guest_name  Guest name if not logged in.
     * @return int|false Insert ID or false on failure.
     */
    public static function add_reply( $feedback_id, $content, $user_id = 0, $guest_name = '' ) {
        global $wpdb;

        $table = self::get_table_name( self::REPLIES_TABLE );

        $data = array(
            'feedback_id'   => absint( $feedback_id ),
            'reply_content' => sanitize_textarea_field( $content ),
            'created_at'    => current_time( 'mysql' ),
        );

        $formats = array( '%d', '%s', '%s' );

        if ( $user_id ) {
            $data['user_id'] = absint( $user_id );
            $formats[]       = '%d';
        } else {
            $data['guest_name'] = sanitize_text_field( $guest_name );
            $formats[]          = '%s';
        }

        $result = $wpdb->insert( $table, $data, $formats );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Delete all replies for a feedback
     *
     * @param int $feedback_id Feedback post ID.
     * @return int|false Number of rows deleted or false.
     */
    public static function delete_replies( $feedback_id ) {
        global $wpdb;

        $table = self::get_table_name( self::REPLIES_TABLE );

        return $wpdb->delete(
            $table,
            array( 'feedback_id' => absint( $feedback_id ) ),
            array( '%d' )
        );
    }

    /**
     * Get reply count for a feedback
     *
     * @param int $feedback_id Feedback post ID.
     * @return int
     */
    public static function get_reply_count( $feedback_id ) {
        global $wpdb;

        $table = self::get_table_name( self::REPLIES_TABLE );

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE feedback_id = %d",
                $feedback_id
            )
        );
    }
}
