<?php
/**
 * User Access Management
 *
 * @package RAS_Website_Feedback
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handle user access to feedback tool
 */
class RAS_WF_User_Access {

    /**
     * Option name for enabled users
     */
    const OPTION_ENABLED_USERS = 'ras_wf_enabled_users';

    /**
     * Option name for guest access
     */
    const OPTION_GUEST_ENABLED = 'ras_wf_guest_enabled';

    /**
     * Option name for guest token
     */
    const OPTION_GUEST_TOKEN = 'ras_wf_guest_token';

    /**
     * Constructor
     */
    public function __construct() {
        // Nothing needed
    }

    /**
     * Check if current user can use feedback tool
     *
     * @return bool
     */
    public static function can_current_user_access() {
        // Check for guest token in URL
        if ( self::is_valid_guest_access() ) {
            return true;
        }

        // Must be logged in otherwise
        if ( ! is_user_logged_in() ) {
            return false;
        }

        $user_id       = get_current_user_id();
        $enabled_users = get_option( self::OPTION_ENABLED_USERS, array() );

        return in_array( $user_id, array_map( 'intval', (array) $enabled_users ), true );
    }

    /**
     * Check if this is a valid guest access
     *
     * @return bool
     */
    public static function is_valid_guest_access() {
        // Guest access must be enabled
        if ( ! get_option( self::OPTION_GUEST_ENABLED, false ) ) {
            return false;
        }

        // Check URL parameter
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( empty( $_GET[ RAS_WF_GUEST_PARAM ] ) ) {
            return false;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $provided_token = sanitize_text_field( wp_unslash( $_GET[ RAS_WF_GUEST_PARAM ] ) );
        $stored_token   = get_option( self::OPTION_GUEST_TOKEN, '' );

        return ! empty( $stored_token ) && hash_equals( $stored_token, $provided_token );
    }

    /**
     * Check if current visitor is a guest (not logged in but has token)
     *
     * @return bool
     */
    public static function is_guest() {
        return ! is_user_logged_in() && self::is_valid_guest_access();
    }

    /**
     * Get enabled users list
     *
     * @return array User IDs.
     */
    public static function get_enabled_users() {
        return array_map( 'intval', (array) get_option( self::OPTION_ENABLED_USERS, array() ) );
    }

    /**
     * Update enabled users list
     *
     * @param array $user_ids Array of user IDs.
     * @return bool
     */
    public static function update_enabled_users( $user_ids ) {
        $user_ids = array_filter( array_map( 'absint', (array) $user_ids ) );
        return update_option( self::OPTION_ENABLED_USERS, $user_ids );
    }

    /**
     * Add a user to enabled list
     *
     * @param int $user_id User ID.
     * @return bool
     */
    public static function enable_user( $user_id ) {
        $users   = self::get_enabled_users();
        $user_id = absint( $user_id );

        if ( ! in_array( $user_id, $users, true ) ) {
            $users[] = $user_id;
            return self::update_enabled_users( $users );
        }

        return true;
    }

    /**
     * Remove a user from enabled list
     *
     * @param int $user_id User ID.
     * @return bool
     */
    public static function disable_user( $user_id ) {
        $users   = self::get_enabled_users();
        $user_id = absint( $user_id );

        $users = array_diff( $users, array( $user_id ) );
        return self::update_enabled_users( $users );
    }

    /**
     * Check if guest access is enabled
     *
     * @return bool
     */
    public static function is_guest_access_enabled() {
        return (bool) get_option( self::OPTION_GUEST_ENABLED, false );
    }

    /**
     * Set guest access
     *
     * @param bool $enabled Whether to enable guest access.
     * @return bool
     */
    public static function set_guest_access( $enabled ) {
        return update_option( self::OPTION_GUEST_ENABLED, (bool) $enabled );
    }

    /**
     * Get guest access URL
     *
     * @param string $page_url Optional page URL to append token to.
     * @return string
     */
    public static function get_guest_url( $page_url = '' ) {
        $token = get_option( self::OPTION_GUEST_TOKEN, '' );

        if ( empty( $token ) ) {
            $token = wp_generate_uuid4();
            update_option( self::OPTION_GUEST_TOKEN, $token );
        }

        $base_url = $page_url ?: home_url( '/' );
        return add_query_arg( RAS_WF_GUEST_PARAM, $token, $base_url );
    }

    /**
     * Regenerate guest token
     *
     * @return string New token.
     */
    public static function regenerate_guest_token() {
        $token = wp_generate_uuid4();
        update_option( self::OPTION_GUEST_TOKEN, $token );
        return $token;
    }

    /**
     * Search users for admin interface
     *
     * @param string $search Search term.
     * @param int    $limit  Max results.
     * @return array
     */
    public static function search_users( $search = '', $limit = 20 ) {
        $args = array(
            'number'  => $limit,
            'orderby' => 'display_name',
            'order'   => 'ASC',
            'fields'  => array( 'ID', 'display_name', 'user_email', 'user_login' ),
        );

        if ( ! empty( $search ) ) {
            $args['search']         = '*' . sanitize_text_field( $search ) . '*';
            $args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
        }

        $users         = get_users( $args );
        $enabled_users = self::get_enabled_users();

        $result = array();
        foreach ( $users as $user ) {
            $result[] = array(
                'id'           => $user->ID,
                'display_name' => $user->display_name,
                'email'        => $user->user_email,
                'login'        => $user->user_login,
                'avatar'       => get_avatar_url( $user->ID, array( 'size' => 32 ) ),
                'enabled'      => in_array( $user->ID, $enabled_users, true ),
            );
        }

        return $result;
    }

    /**
     * Get enabled users with details
     *
     * @return array
     */
    public static function get_enabled_users_details() {
        $user_ids = self::get_enabled_users();

        if ( empty( $user_ids ) ) {
            return array();
        }

        $users = get_users( array(
            'include' => $user_ids,
            'orderby' => 'display_name',
            'order'   => 'ASC',
            'fields'  => array( 'ID', 'display_name', 'user_email' ),
        ) );

        $result = array();
        foreach ( $users as $user ) {
            $result[] = array(
                'id'           => $user->ID,
                'display_name' => $user->display_name,
                'email'        => $user->user_email,
                'avatar'       => get_avatar_url( $user->ID, array( 'size' => 32 ) ),
            );
        }

        return $result;
    }
}
