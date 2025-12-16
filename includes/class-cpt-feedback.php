<?php
/**
 * Custom Post Type for Feedback
 *
 * @package RAS_Website_Feedback
 */

defined( 'ABSPATH' ) || exit;

/**
 * Feedback CPT registration and handling
 */
class RAS_WF_CPT_Feedback {

    /**
     * Post type name
     */
    const POST_TYPE = 'ras_wf_feedback';

    /**
     * Feedback statuses
     */
    const STATUS_UNRESOLVED = 'unresolved';
    const STATUS_PENDING    = 'pending';
    const STATUS_RESOLVED   = 'resolved';

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'before_delete_post', array( $this, 'delete_feedback_replies' ) );
    }

    /**
     * Register the feedback CPT
     */
    public function register_post_type() {
        $labels = array(
            'name'                  => _x( 'Feedbacks', 'Post type general name', 'ras-website-feedback' ),
            'singular_name'         => _x( 'Feedback', 'Post type singular name', 'ras-website-feedback' ),
            'menu_name'             => _x( 'Feedback', 'Admin Menu text', 'ras-website-feedback' ),
            'add_new'               => __( 'Add New', 'ras-website-feedback' ),
            'add_new_item'          => __( 'Add New Feedback', 'ras-website-feedback' ),
            'edit_item'             => __( 'Edit Feedback', 'ras-website-feedback' ),
            'new_item'              => __( 'New Feedback', 'ras-website-feedback' ),
            'view_item'             => __( 'View Feedback', 'ras-website-feedback' ),
            'search_items'          => __( 'Search Feedbacks', 'ras-website-feedback' ),
            'not_found'             => __( 'No feedbacks found', 'ras-website-feedback' ),
            'not_found_in_trash'    => __( 'No feedbacks found in Trash', 'ras-website-feedback' ),
            'all_items'             => __( 'All Feedbacks', 'ras-website-feedback' ),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => false, // We use custom admin page
            'show_in_menu'       => false,
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'supports'           => array( 'title', 'editor', 'author', 'custom-fields' ),
            'show_in_rest'       => false,
        );

        register_post_type( self::POST_TYPE, $args );
    }

    /**
     * Create a new feedback entry
     *
     * @param array $data Feedback data.
     * @return int|WP_Error Post ID or error.
     */
    public static function create_feedback( $data ) {
        // Generate UUID
        $uuid = wp_generate_uuid4();

        // Build post title from URL and element
        $url_path = wp_parse_url( $data['page_url'], PHP_URL_PATH );
        $title    = sprintf(
            /* translators: %1$s: URL path, %2$s: element selector */
            __( 'Feedback on %1$s - %2$s', 'ras-website-feedback' ),
            $url_path ?: '/',
            substr( $data['element_selector'], 0, 50 )
        );

        $post_data = array(
            'post_type'    => self::POST_TYPE,
            'post_status'  => 'publish',
            'post_title'   => $title,
            'post_content' => sanitize_textarea_field( $data['comment'] ),
            'post_author'  => ! empty( $data['user_id'] ) ? absint( $data['user_id'] ) : 0,
        );

        $post_id = wp_insert_post( $post_data, true );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        // Store metadata
        update_post_meta( $post_id, '_ras_wf_uuid', $uuid );
        update_post_meta( $post_id, '_ras_wf_page_url', esc_url_raw( $data['page_url'] ) );
        update_post_meta( $post_id, '_ras_wf_page_path', sanitize_text_field( $url_path ?: '/' ) );
        update_post_meta( $post_id, '_ras_wf_click_x', absint( $data['click_x'] ) );
        update_post_meta( $post_id, '_ras_wf_click_y', absint( $data['click_y'] ) );
        update_post_meta( $post_id, '_ras_wf_window_width', absint( $data['window_width'] ) );
        update_post_meta( $post_id, '_ras_wf_window_height', absint( $data['window_height'] ) );
        update_post_meta( $post_id, '_ras_wf_element_selector', sanitize_text_field( $data['element_selector'] ) );
        update_post_meta( $post_id, '_ras_wf_element_html', wp_kses_post( $data['element_html'] ?? '' ) );
        update_post_meta( $post_id, '_ras_wf_status', self::STATUS_UNRESOLVED );
        update_post_meta( $post_id, '_ras_wf_created_at', current_time( 'mysql' ) );

        // Guest info if not logged in
        if ( empty( $data['user_id'] ) && ! empty( $data['guest_name'] ) ) {
            update_post_meta( $post_id, '_ras_wf_guest_name', sanitize_text_field( $data['guest_name'] ) );
        }

        return $post_id;
    }

    /**
     * Get feedback by UUID
     *
     * @param string $uuid Feedback UUID.
     * @return WP_Post|null
     */
    public static function get_by_uuid( $uuid ) {
        $posts = get_posts( array(
            'post_type'   => self::POST_TYPE,
            'post_status' => 'publish',
            'meta_key'    => '_ras_wf_uuid',
            'meta_value'  => sanitize_text_field( $uuid ),
            'numberposts' => 1,
        ) );

        return ! empty( $posts ) ? $posts[0] : null;
    }

    /**
     * Get feedbacks for a specific URL/path
     *
     * @param string $page_path Page path.
     * @param string $status    Optional status filter.
     * @return array
     */
    public static function get_by_page( $page_path, $status = '' ) {
        $meta_query = array(
            array(
                'key'   => '_ras_wf_page_path',
                'value' => sanitize_text_field( $page_path ),
            ),
        );

        if ( $status && in_array( $status, array( self::STATUS_UNRESOLVED, self::STATUS_PENDING, self::STATUS_RESOLVED ), true ) ) {
            $meta_query[] = array(
                'key'   => '_ras_wf_status',
                'value' => $status,
            );
        }

        $posts = get_posts( array(
            'post_type'   => self::POST_TYPE,
            'post_status' => 'publish',
            'numberposts' => 100, // Limit for performance
            'meta_query'  => $meta_query,
            'orderby'     => 'date',
            'order'       => 'DESC',
        ) );

        return array_map( array( __CLASS__, 'format_feedback' ), $posts );
    }

    /**
     * Get all feedbacks with pagination
     *
     * @param array $args Query arguments.
     * @return array
     */
    public static function get_all( $args = array() ) {
        $defaults = array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'paged'          => 1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        $query_args = wp_parse_args( $args, $defaults );

        // Status filter
        if ( ! empty( $args['status'] ) ) {
            $query_args['meta_query'][] = array(
                'key'   => '_ras_wf_status',
                'value' => sanitize_text_field( $args['status'] ),
            );
        }

        // Search
        if ( ! empty( $args['search'] ) ) {
            $query_args['s'] = sanitize_text_field( $args['search'] );
        }

        // URL filter
        if ( ! empty( $args['page_path'] ) ) {
            $query_args['meta_query'][] = array(
                'key'   => '_ras_wf_page_path',
                'value' => sanitize_text_field( $args['page_path'] ),
                'compare' => 'LIKE',
            );
        }

        $query = new WP_Query( $query_args );

        $feedbacks = array_map( array( __CLASS__, 'format_feedback' ), $query->posts );

        return array(
            'items'       => $feedbacks,
            'total'       => $query->found_posts,
            'total_pages' => $query->max_num_pages,
            'page'        => $query_args['paged'],
        );
    }

    /**
     * Format feedback post into array
     *
     * @param WP_Post $post Post object.
     * @return array
     */
    public static function format_feedback( $post ) {
        $user_id = $post->post_author;
        $user    = $user_id ? get_user_by( 'ID', $user_id ) : null;

        $guest_name = get_post_meta( $post->ID, '_ras_wf_guest_name', true );

        return array(
            'id'               => $post->ID,
            'uuid'             => get_post_meta( $post->ID, '_ras_wf_uuid', true ),
            'comment'          => $post->post_content,
            'page_url'         => get_post_meta( $post->ID, '_ras_wf_page_url', true ),
            'page_path'        => get_post_meta( $post->ID, '_ras_wf_page_path', true ),
            'click_x'          => (int) get_post_meta( $post->ID, '_ras_wf_click_x', true ),
            'click_y'          => (int) get_post_meta( $post->ID, '_ras_wf_click_y', true ),
            'window_width'     => (int) get_post_meta( $post->ID, '_ras_wf_window_width', true ),
            'window_height'    => (int) get_post_meta( $post->ID, '_ras_wf_window_height', true ),
            'element_selector' => get_post_meta( $post->ID, '_ras_wf_element_selector', true ),
            'element_html'     => get_post_meta( $post->ID, '_ras_wf_element_html', true ),
            'status'           => get_post_meta( $post->ID, '_ras_wf_status', true ) ?: self::STATUS_UNRESOLVED,
            'user_id'          => $user_id,
            'user_name'        => $user ? $user->display_name : ( $guest_name ?: __( 'Guest', 'ras-website-feedback' ) ),
            'user_avatar'      => get_avatar_url( $user_id ?: 0, array( 'size' => 40 ) ),
            'created_at'       => get_post_meta( $post->ID, '_ras_wf_created_at', true ) ?: $post->post_date,
            'reply_count'      => RAS_WF_Database::get_reply_count( $post->ID ),
        );
    }

    /**
     * Update feedback status
     *
     * @param int    $feedback_id Post ID.
     * @param string $status      New status.
     * @return bool
     */
    public static function update_status( $feedback_id, $status ) {
        if ( ! in_array( $status, array( self::STATUS_UNRESOLVED, self::STATUS_PENDING, self::STATUS_RESOLVED ), true ) ) {
            return false;
        }

        return (bool) update_post_meta( $feedback_id, '_ras_wf_status', $status );
    }

    /**
     * Get status counts
     *
     * @return array
     */
    public static function get_status_counts() {
        global $wpdb;

        $counts = array(
            self::STATUS_UNRESOLVED => 0,
            self::STATUS_PENDING    => 0,
            self::STATUS_RESOLVED   => 0,
        );

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pm.meta_value as status, COUNT(*) as count
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = %s
                AND p.post_status = 'publish'
                AND pm.meta_key = '_ras_wf_status'
                GROUP BY pm.meta_value",
                self::POST_TYPE
            ),
            ARRAY_A
        );

        foreach ( $results as $row ) {
            if ( isset( $counts[ $row['status'] ] ) ) {
                $counts[ $row['status'] ] = (int) $row['count'];
            }
        }

        return $counts;
    }

    /**
     * Delete replies when feedback is deleted
     *
     * @param int $post_id Post ID.
     */
    public function delete_feedback_replies( $post_id ) {
        if ( get_post_type( $post_id ) !== self::POST_TYPE ) {
            return;
        }

        RAS_WF_Database::delete_replies( $post_id );
    }

    /**
     * Get count of feedbacks for a page path
     *
     * @param string $page_path Page path.
     * @param bool   $active_only Only count unresolved/pending.
     * @return int
     */
    public static function get_page_count( $page_path, $active_only = true ) {
        global $wpdb;

        $status_clause = '';
        if ( $active_only ) {
            $status_clause = $wpdb->prepare(
                " AND pm2.meta_key = '_ras_wf_status' AND pm2.meta_value IN (%s, %s)",
                self::STATUS_UNRESOLVED,
                self::STATUS_PENDING
            );
        }

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                " . ( $active_only ? "INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id" : "" ) . "
                WHERE p.post_type = %s
                AND p.post_status = 'publish'
                AND pm.meta_key = '_ras_wf_page_path'
                AND pm.meta_value = %s
                {$status_clause}",
                self::POST_TYPE,
                sanitize_text_field( $page_path )
            )
        );

        return (int) $count;
    }
}
