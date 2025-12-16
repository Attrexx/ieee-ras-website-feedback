<?php
/**
 * Plugin Name:       RAS Website Feedback
 * Plugin URI:        https://github.com/Attrexx/ieee-ras-website-feedback
 * Description:       Visual feedback tool allowing selected users to leave contextual feedback on page elements with Inspector-like element selection.
 * Version:           1.1.0
 * Requires at least: 5.0
 * Tested up to:      6.5
 * Requires PHP:      7.4
 * Author:            TAROS Web Services
 * Author URI:        https://taros.ro
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ras-website-feedback
 * Domain Path:       /languages
 * Network:           false
 *
 * GitHub Plugin URI: https://github.com/Attrexx/ieee-ras-website-feedback
 * Primary Branch:    main
 * Release Asset:     true
 *
 * @package RAS_Website_Feedback
 * @version 1.1.0
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants
define( 'RAS_WF_VERSION', '1.1.0' );
define( 'RAS_WF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RAS_WF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RAS_WF_PLUGIN_FILE', __FILE__ );
define( 'RAS_WF_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Guest access URL parameter
define( 'RAS_WF_GUEST_PARAM', 'ras_feedback_token' );

/**
 * Main plugin class - Singleton pattern
 */
final class RAS_Website_Feedback {

    /**
     * Single instance
     *
     * @var RAS_Website_Feedback|null
     */
    private static $instance = null;

    /**
     * Plugin modules
     *
     * @var array
     */
    private $modules = array();

    /**
     * Get singleton instance
     *
     * @return RAS_Website_Feedback
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - private for singleton
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        // Core classes
        require_once RAS_WF_PLUGIN_DIR . 'includes/class-database.php';
        require_once RAS_WF_PLUGIN_DIR . 'includes/class-cpt-feedback.php';
        require_once RAS_WF_PLUGIN_DIR . 'includes/class-user-access.php';
        require_once RAS_WF_PLUGIN_DIR . 'includes/class-email-notifications.php';
        require_once RAS_WF_PLUGIN_DIR . 'includes/class-ajax-handler.php';

        // Frontend classes
        if ( ! is_admin() || wp_doing_ajax() ) {
            require_once RAS_WF_PLUGIN_DIR . 'includes/frontend/class-feedback-tool.php';
        }
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Initialize modules after plugins loaded
        add_action( 'plugins_loaded', array( $this, 'init_modules' ) );

        // Register activation/deactivation hooks
        register_activation_hook( RAS_WF_PLUGIN_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( RAS_WF_PLUGIN_FILE, array( $this, 'deactivate' ) );

        // Load textdomain
        add_action( 'init', array( $this, 'load_textdomain' ) );

        // Add settings link on plugins page
        add_filter( 'plugin_action_links_' . RAS_WF_PLUGIN_BASENAME, array( $this, 'add_plugin_action_links' ) );
    }

    /**
     * Add settings link to plugins page
     *
     * @param array $links Existing links.
     * @return array
     */
    public function add_plugin_action_links( $links ) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url( 'admin.php?page=ras-website-feedback' ),
            __( 'Settings', 'ras-website-feedback' )
        );
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Initialize plugin modules
     */
    public function init_modules() {
        // Core modules
        $this->modules['database']      = new RAS_WF_Database();
        $this->modules['cpt']           = new RAS_WF_CPT_Feedback();
        $this->modules['user_access']   = new RAS_WF_User_Access();
        $this->modules['notifications'] = new RAS_WF_Email_Notifications();
        $this->modules['ajax']          = new RAS_WF_Ajax_Handler();

        // Admin module - always load to register menu
        if ( is_admin() ) {
            require_once RAS_WF_PLUGIN_DIR . 'includes/admin/class-admin-dashboard.php';
            $this->modules['admin'] = new RAS_WF_Admin_Dashboard();
        }

        // Frontend module
        if ( ! is_admin() || wp_doing_ajax() ) {
            $this->modules['frontend'] = new RAS_WF_Feedback_Tool();
        }
    }

    /**
     * Get a module instance
     *
     * @param string $module_name Module name.
     * @return object|null
     */
    public function get_module( $module_name ) {
        return isset( $this->modules[ $module_name ] ) ? $this->modules[ $module_name ] : null;
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        require_once RAS_WF_PLUGIN_DIR . 'includes/class-database.php';
        $database = new RAS_WF_Database();
        $database->create_tables();

        // Create email digest queue table
        require_once RAS_WF_PLUGIN_DIR . 'includes/class-email-notifications.php';
        RAS_WF_Email_Notifications::create_tables();

        // Register CPT (needed for flush_rewrite_rules)
        require_once RAS_WF_PLUGIN_DIR . 'includes/class-cpt-feedback.php';
        $cpt = new RAS_WF_CPT_Feedback();
        $cpt->register_post_type();

        // Pre-select all administrators
        $this->select_default_users();

        // Generate guest token
        if ( ! get_option( 'ras_wf_guest_token' ) ) {
            update_option( 'ras_wf_guest_token', wp_generate_uuid4() );
        }

        // Flush rewrite rules
        flush_rewrite_rules();

        // Set plugin version
        update_option( 'ras_wf_version', RAS_WF_VERSION );
    }

    /**
     * Select all administrators by default on install
     */
    private function select_default_users() {
        // Only run on fresh install
        if ( get_option( 'ras_wf_enabled_users' ) !== false ) {
            return;
        }

        $admins = get_users( array(
            'role'   => 'administrator',
            'fields' => 'ID',
        ) );

        update_option( 'ras_wf_enabled_users', array_map( 'intval', $admins ) );
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'ras-website-feedback',
            false,
            dirname( RAS_WF_PLUGIN_BASENAME ) . '/languages'
        );
    }
}

/**
 * Global accessor function
 *
 * @return RAS_Website_Feedback
 */
function ras_website_feedback() {
    return RAS_Website_Feedback::get_instance();
}

// Initialize plugin
ras_website_feedback();
