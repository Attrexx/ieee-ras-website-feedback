<?php
/**
 * Admin Dashboard
 *
 * @package RAS_Website_Feedback
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin dashboard for feedback management
 */
class RAS_WF_Admin_Dashboard {

    /**
     * Admin page slug
     */
    const PAGE_SLUG = 'ras-website-feedback';

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    /**
     * Register admin menu - positioned in top half
     */
    public function register_admin_menu() {
        add_menu_page(
            __( 'Website Feedback', 'ras-website-feedback' ),
            __( 'Feedback', 'ras-website-feedback' ),
            'manage_options',
            self::PAGE_SLUG,
            array( $this, 'render_dashboard' ),
            'dashicons-testimonial',
            25 // Position in top half of menu
        );
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page.
     */
    public function enqueue_admin_assets( $hook ) {
        if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'ras-wf-admin',
            RAS_WF_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            RAS_WF_VERSION
        );

        wp_enqueue_script(
            'ras-wf-admin',
            RAS_WF_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            RAS_WF_VERSION,
            true
        );

        wp_localize_script( 'ras-wf-admin', 'rasWfAdmin', array(
            'ajaxurl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'ras_wf_admin_nonce' ),
            'i18n'     => array(
                'confirmRemoveUser'  => __( 'Remove this user from feedback access?', 'ras-website-feedback' ),
                'confirmRegenToken'  => __( 'Regenerating the token will invalidate all existing guest links. Continue?', 'ras-website-feedback' ),
                'confirmDelete'      => __( 'Delete this feedback permanently?', 'ras-website-feedback' ),
                'saving'             => __( 'Saving...', 'ras-website-feedback' ),
                'saved'              => __( 'Saved!', 'ras-website-feedback' ),
                'error'              => __( 'Error occurred', 'ras-website-feedback' ),
                'searchPlaceholder'  => __( 'Search users...', 'ras-website-feedback' ),
                'noResults'          => __( 'No users found', 'ras-website-feedback' ),
                'loading'            => __( 'Loading...', 'ras-website-feedback' ),
            ),
        ) );
    }

    /**
     * Render admin dashboard
     */
    public function render_dashboard() {
        // Get current tab
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'feedback';

        // Get status counts
        $status_counts = RAS_WF_CPT_Feedback::get_status_counts();
        $total_count   = array_sum( $status_counts );

        ?>
        <div class="wrap ras-wf-admin">
            <h1><?php esc_html_e( 'Website Feedback', 'ras-website-feedback' ); ?></h1>

            <!-- Status Summary Cards -->
            <div class="ras-wf-stats-cards">
                <div class="ras-wf-stat-card ras-wf-stat-total">
                    <span class="ras-wf-stat-number"><?php echo esc_html( $total_count ); ?></span>
                    <span class="ras-wf-stat-label"><?php esc_html_e( 'Total Feedback', 'ras-website-feedback' ); ?></span>
                </div>
                <div class="ras-wf-stat-card ras-wf-stat-unresolved">
                    <span class="ras-wf-stat-number"><?php echo esc_html( $status_counts[ RAS_WF_CPT_Feedback::STATUS_UNRESOLVED ] ); ?></span>
                    <span class="ras-wf-stat-label"><?php esc_html_e( 'Unresolved', 'ras-website-feedback' ); ?></span>
                </div>
                <div class="ras-wf-stat-card ras-wf-stat-pending">
                    <span class="ras-wf-stat-number"><?php echo esc_html( $status_counts[ RAS_WF_CPT_Feedback::STATUS_PENDING ] ); ?></span>
                    <span class="ras-wf-stat-label"><?php esc_html_e( 'Pending', 'ras-website-feedback' ); ?></span>
                </div>
                <div class="ras-wf-stat-card ras-wf-stat-resolved">
                    <span class="ras-wf-stat-number"><?php echo esc_html( $status_counts[ RAS_WF_CPT_Feedback::STATUS_RESOLVED ] ); ?></span>
                    <span class="ras-wf-stat-label"><?php esc_html_e( 'Resolved', 'ras-website-feedback' ); ?></span>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <nav class="nav-tab-wrapper">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=feedback' ) ); ?>"
                   class="nav-tab <?php echo 'feedback' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Feedback Log', 'ras-website-feedback' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=users' ) ); ?>"
                   class="nav-tab <?php echo 'users' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'User Access', 'ras-website-feedback' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=settings' ) ); ?>"
                   class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Settings', 'ras-website-feedback' ); ?>
                </a>
            </nav>

            <div class="ras-wf-tab-content">
                <?php
                switch ( $active_tab ) {
                    case 'users':
                        $this->render_users_tab();
                        break;
                    case 'settings':
                        $this->render_settings_tab();
                        break;
                    default:
                        $this->render_feedback_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render feedback log tab
     */
    private function render_feedback_tab() {
        // Get filters from URL
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $current_status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

        $feedbacks = RAS_WF_CPT_Feedback::get_all( array(
            'status' => $current_status,
            'paged'  => $current_page,
            'search' => $search,
        ) );

        ?>
        <div class="ras-wf-feedback-tab">
            <!-- Filters -->
            <div class="ras-wf-filters">
                <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
                    <input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
                    <input type="hidden" name="tab" value="feedback">

                    <select name="status">
                        <option value=""><?php esc_html_e( 'All Statuses', 'ras-website-feedback' ); ?></option>
                        <option value="unresolved" <?php selected( $current_status, 'unresolved' ); ?>>
                            <?php esc_html_e( 'Unresolved', 'ras-website-feedback' ); ?>
                        </option>
                        <option value="pending" <?php selected( $current_status, 'pending' ); ?>>
                            <?php esc_html_e( 'Pending', 'ras-website-feedback' ); ?>
                        </option>
                        <option value="resolved" <?php selected( $current_status, 'resolved' ); ?>>
                            <?php esc_html_e( 'Resolved', 'ras-website-feedback' ); ?>
                        </option>
                    </select>

                    <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>"
                           placeholder="<?php esc_attr_e( 'Search feedback...', 'ras-website-feedback' ); ?>">

                    <button type="submit" class="button"><?php esc_html_e( 'Filter', 'ras-website-feedback' ); ?></button>
                </form>
            </div>

            <!-- Feedback List -->
            <?php if ( empty( $feedbacks['items'] ) ) : ?>
                <div class="ras-wf-no-results">
                    <p><?php esc_html_e( 'No feedback found.', 'ras-website-feedback' ); ?></p>
                </div>
            <?php else : ?>
                <div class="ras-wf-feedback-list">
                    <?php foreach ( $feedbacks['items'] as $feedback ) : ?>
                        <div class="ras-wf-feedback-item ras-wf-status-<?php echo esc_attr( $feedback['status'] ); ?>"
                             data-feedback-id="<?php echo esc_attr( $feedback['id'] ); ?>">
                            <div class="ras-wf-feedback-header">
                                <div class="ras-wf-feedback-meta">
                                    <img src="<?php echo esc_url( $feedback['user_avatar'] ); ?>"
                                         alt="" class="ras-wf-avatar">
                                    <span class="ras-wf-user-name"><?php echo esc_html( $feedback['user_name'] ); ?></span>
                                    <span class="ras-wf-date"><?php echo esc_html( $feedback['created_at'] ); ?></span>
                                    <span class="ras-wf-status-badge ras-wf-badge-<?php echo esc_attr( $feedback['status'] ); ?>">
                                        <?php echo esc_html( ucfirst( $feedback['status'] ) ); ?>
                                    </span>
                                </div>
                                <div class="ras-wf-feedback-actions">
                                    <button type="button" class="button ras-wf-action-status"
                                            data-status="unresolved" <?php disabled( $feedback['status'], 'unresolved' ); ?>>
                                        <?php esc_html_e( 'Unresolved', 'ras-website-feedback' ); ?>
                                    </button>
                                    <button type="button" class="button ras-wf-action-status"
                                            data-status="pending" <?php disabled( $feedback['status'], 'pending' ); ?>>
                                        <?php esc_html_e( 'Pending', 'ras-website-feedback' ); ?>
                                    </button>
                                    <button type="button" class="button button-primary ras-wf-action-status"
                                            data-status="resolved" <?php disabled( $feedback['status'], 'resolved' ); ?>>
                                        <?php esc_html_e( 'Resolved', 'ras-website-feedback' ); ?>
                                    </button>
                                    <button type="button" class="button ras-wf-action-delete"
                                            title="<?php esc_attr_e( 'Delete', 'ras-website-feedback' ); ?>">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </div>
                            </div>

                            <div class="ras-wf-feedback-content">
                                <div class="ras-wf-feedback-url">
                                    <strong><?php esc_html_e( 'Page:', 'ras-website-feedback' ); ?></strong>
                                    <a href="<?php echo esc_url( $feedback['page_url'] ); ?>" target="_blank">
                                        <?php echo esc_html( $feedback['page_path'] ); ?>
                                        <span class="dashicons dashicons-external"></span>
                                    </a>
                                </div>
                                <div class="ras-wf-feedback-element">
                                    <strong><?php esc_html_e( 'Element:', 'ras-website-feedback' ); ?></strong>
                                    <code><?php echo esc_html( $feedback['element_selector'] ); ?></code>
                                </div>
                                <div class="ras-wf-feedback-comment">
                                    <?php echo wp_kses_post( wpautop( $feedback['comment'] ) ); ?>
                                </div>
                                <div class="ras-wf-feedback-details">
                                    <span title="<?php esc_attr_e( 'Click position', 'ras-website-feedback' ); ?>">
                                        <span class="dashicons dashicons-location"></span>
                                        <?php echo esc_html( $feedback['click_x'] . ', ' . $feedback['click_y'] ); ?>
                                    </span>
                                    <span title="<?php esc_attr_e( 'Window size', 'ras-website-feedback' ); ?>">
                                        <span class="dashicons dashicons-desktop"></span>
                                        <?php echo esc_html( $feedback['window_width'] . 'x' . $feedback['window_height'] ); ?>
                                    </span>
                                    <span title="<?php esc_attr_e( 'UUID', 'ras-website-feedback' ); ?>">
                                        <span class="dashicons dashicons-tag"></span>
                                        <?php echo esc_html( substr( $feedback['uuid'], 0, 8 ) ); ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Replies Section -->
                            <div class="ras-wf-replies-section">
                                <h4>
                                    <?php esc_html_e( 'Replies', 'ras-website-feedback' ); ?>
                                    <span class="ras-wf-reply-count">(<?php echo esc_html( $feedback['reply_count'] ); ?>)</span>
                                </h4>
                                <div class="ras-wf-replies-list" data-feedback-id="<?php echo esc_attr( $feedback['id'] ); ?>">
                                    <!-- Replies loaded via AJAX -->
                                </div>
                                <div class="ras-wf-reply-form">
                                    <textarea placeholder="<?php esc_attr_e( 'Add a reply...', 'ras-website-feedback' ); ?>"
                                              rows="2"></textarea>
                                    <button type="button" class="button button-primary ras-wf-submit-reply">
                                        <?php esc_html_e( 'Reply', 'ras-website-feedback' ); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ( $feedbacks['total_pages'] > 1 ) : ?>
                    <div class="ras-wf-pagination">
                        <?php
                        $base_url = admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=feedback' );
                        if ( $current_status ) {
                            $base_url = add_query_arg( 'status', $current_status, $base_url );
                        }
                        if ( $search ) {
                            $base_url = add_query_arg( 's', $search, $base_url );
                        }

                        echo paginate_links( array(
                            'base'      => add_query_arg( 'paged', '%#%', $base_url ),
                            'format'    => '',
                            'current'   => $current_page,
                            'total'     => $feedbacks['total_pages'],
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                        ) );
                        ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render users access tab
     */
    private function render_users_tab() {
        $enabled_users = RAS_WF_User_Access::get_enabled_users_details();

        // Get notification preferences
        $notifications = ras_website_feedback()->get_module( 'notifications' );
        ?>
        <div class="ras-wf-users-tab">
            <div class="ras-wf-users-container">
                <!-- User Search -->
                <div class="ras-wf-user-search-box">
                    <h3><?php esc_html_e( 'Add Users', 'ras-website-feedback' ); ?></h3>
                    <p class="description">
                        <?php esc_html_e( 'Search and select users who can access the feedback tool.', 'ras-website-feedback' ); ?>
                    </p>
                    <div class="ras-wf-search-wrapper">
                        <input type="text" id="ras-wf-user-search"
                               placeholder="<?php esc_attr_e( 'Search users by name or email...', 'ras-website-feedback' ); ?>">
                        <div id="ras-wf-search-results" class="ras-wf-search-results"></div>
                    </div>
                </div>

                <!-- Enabled Users List -->
                <div class="ras-wf-enabled-users-box">
                    <h3>
                        <?php esc_html_e( 'Enabled Users', 'ras-website-feedback' ); ?>
                        <span class="ras-wf-user-count">(<?php echo count( $enabled_users ); ?>)</span>
                    </h3>
                    <p class="description">
                        <?php esc_html_e( 'Configure notification preferences for each user. Live emails are sent immediately; daily digest is sent at 3:00 PM EST.', 'ras-website-feedback' ); ?>
                    </p>
                    <div id="ras-wf-enabled-users" class="ras-wf-user-list">
                        <?php if ( empty( $enabled_users ) ) : ?>
                            <p class="ras-wf-no-users">
                                <?php esc_html_e( 'No users enabled. Search and add users above.', 'ras-website-feedback' ); ?>
                            </p>
                        <?php else : ?>
                            <?php foreach ( $enabled_users as $user ) :
                                $user_pref = $notifications ? $notifications->get_user_preference( $user['id'] ) : RAS_WF_Email_Notifications::MODE_LIVE;
                            ?>
                                <div class="ras-wf-user-item" data-user-id="<?php echo esc_attr( $user['id'] ); ?>">
                                    <img src="<?php echo esc_url( $user['avatar'] ); ?>" alt="" class="ras-wf-avatar">
                                    <div class="ras-wf-user-info">
                                        <span class="ras-wf-user-name"><?php echo esc_html( $user['display_name'] ); ?></span>
                                        <span class="ras-wf-user-email"><?php echo esc_html( $user['email'] ); ?></span>
                                    </div>
                                    <div class="ras-wf-user-notifications">
                                        <select class="ras-wf-notification-pref" data-user-id="<?php echo esc_attr( $user['id'] ); ?>">
                                            <option value="<?php echo esc_attr( RAS_WF_Email_Notifications::MODE_LIVE ); ?>"
                                                    <?php selected( $user_pref, RAS_WF_Email_Notifications::MODE_LIVE ); ?>>
                                                <?php esc_html_e( 'Live emails', 'ras-website-feedback' ); ?>
                                            </option>
                                            <option value="<?php echo esc_attr( RAS_WF_Email_Notifications::MODE_DIGEST ); ?>"
                                                    <?php selected( $user_pref, RAS_WF_Email_Notifications::MODE_DIGEST ); ?>>
                                                <?php esc_html_e( 'Daily digest', 'ras-website-feedback' ); ?>
                                            </option>
                                            <option value="<?php echo esc_attr( RAS_WF_Email_Notifications::MODE_OFF ); ?>"
                                                    <?php selected( $user_pref, RAS_WF_Email_Notifications::MODE_OFF ); ?>>
                                                <?php esc_html_e( 'Off', 'ras-website-feedback' ); ?>
                                            </option>
                                        </select>
                                    </div>
                                    <button type="button" class="button ras-wf-remove-user"
                                            title="<?php esc_attr_e( 'Remove access', 'ras-website-feedback' ); ?>">
                                        <span class="dashicons dashicons-no-alt"></span>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render settings tab
     */
    private function render_settings_tab() {
        $guest_enabled = RAS_WF_User_Access::is_guest_access_enabled();
        $guest_url     = RAS_WF_User_Access::get_guest_url();
        $guest_token   = get_option( 'ras_wf_guest_token', '' );
        ?>
        <div class="ras-wf-settings-tab">
            <form id="ras-wf-settings-form">
                <!-- Guest Access Section -->
                <div class="ras-wf-settings-section">
                    <h3><?php esc_html_e( 'Guest Access', 'ras-website-feedback' ); ?></h3>
                    <p class="description">
                        <?php esc_html_e( 'Allow non-logged-in visitors to use the feedback tool via a special URL parameter.', 'ras-website-feedback' ); ?>
                    </p>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="ras-wf-guest-enabled">
                                    <?php esc_html_e( 'Enable Guest Access', 'ras-website-feedback' ); ?>
                                </label>
                            </th>
                            <td>
                                <label class="ras-wf-toggle">
                                    <input type="checkbox" id="ras-wf-guest-enabled" name="guest_enabled"
                                           <?php checked( $guest_enabled ); ?>>
                                    <span class="ras-wf-toggle-slider"></span>
                                </label>
                            </td>
                        </tr>
                        <tr class="ras-wf-guest-url-row" <?php echo $guest_enabled ? '' : 'style="display:none;"'; ?>>
                            <th scope="row">
                                <?php esc_html_e( 'Guest URL', 'ras-website-feedback' ); ?>
                            </th>
                            <td>
                                <div class="ras-wf-guest-url-box">
                                    <code id="ras-wf-guest-url"><?php echo esc_html( $guest_url ); ?></code>
                                    <button type="button" class="button ras-wf-copy-url"
                                            data-url="<?php echo esc_attr( $guest_url ); ?>">
                                        <span class="dashicons dashicons-clipboard"></span>
                                        <?php esc_html_e( 'Copy', 'ras-website-feedback' ); ?>
                                    </button>
                                </div>
                                <p class="description">
                                    <?php esc_html_e( 'Share this URL with guests. Add the parameter to any page URL on your site.', 'ras-website-feedback' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr class="ras-wf-guest-url-row" <?php echo $guest_enabled ? '' : 'style="display:none;"'; ?>>
                            <th scope="row">
                                <?php esc_html_e( 'URL Parameter', 'ras-website-feedback' ); ?>
                            </th>
                            <td>
                                <code><?php echo esc_html( RAS_WF_GUEST_PARAM . '=' . $guest_token ); ?></code>
                                <button type="button" class="button ras-wf-regenerate-token">
                                    <span class="dashicons dashicons-update"></span>
                                    <?php esc_html_e( 'Regenerate Token', 'ras-website-feedback' ); ?>
                                </button>
                                <p class="description">
                                    <?php esc_html_e( 'Regenerating the token will invalidate all existing guest links.', 'ras-website-feedback' ); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Tool Instructions -->
                <div class="ras-wf-settings-section">
                    <h3><?php esc_html_e( 'How to Use', 'ras-website-feedback' ); ?></h3>
                    <div class="ras-wf-instructions">
                        <ol>
                            <li><?php esc_html_e( 'Enable users from the "User Access" tab or enable guest access above.', 'ras-website-feedback' ); ?></li>
                            <li><?php esc_html_e( 'Enabled users will see a floating feedback button on the frontend.', 'ras-website-feedback' ); ?></li>
                            <li><?php esc_html_e( 'Click the button to activate element selection mode.', 'ras-website-feedback' ); ?></li>
                            <li><?php esc_html_e( 'Hover over page elements to highlight them (like browser inspect tool).', 'ras-website-feedback' ); ?></li>
                            <li><?php esc_html_e( 'Click an element to open the feedback form.', 'ras-website-feedback' ); ?></li>
                            <li><?php esc_html_e( 'Submit feedback which will appear in this dashboard.', 'ras-website-feedback' ); ?></li>
                            <li><?php esc_html_e( 'Click the feedback count badge to view all feedbacks for that page in a drawer.', 'ras-website-feedback' ); ?></li>
                        </ol>
                    </div>
                </div>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e( 'Save Settings', 'ras-website-feedback' ); ?>
                    </button>
                    <span class="ras-wf-save-status"></span>
                </p>
            </form>
        </div>
        <?php
    }
}
