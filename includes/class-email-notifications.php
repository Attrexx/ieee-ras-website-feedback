<?php
/**
 * Email Notifications Handler
 *
 * @package RAS_Website_Feedback
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handle email notifications for feedback events
 */
class RAS_WF_Email_Notifications {

    /**
     * Digest queue table name (without prefix)
     */
    const DIGEST_TABLE = 'ras_wf_email_queue';

    /**
     * Cron hook name
     */
    const CRON_HOOK = 'ras_wf_send_daily_digest';

    /**
     * Option for user preferences
     */
    const OPTION_USER_PREFS = 'ras_wf_user_notification_prefs';

    /**
     * Notification types
     */
    const TYPE_NEW_FEEDBACK   = 'new_feedback';
    const TYPE_NEW_REPLY      = 'new_reply';
    const TYPE_STATUS_CHANGE  = 'status_change';

    /**
     * User preference modes
     */
    const MODE_LIVE   = 'live';
    const MODE_DIGEST = 'digest';
    const MODE_OFF    = 'off';

    /**
     * Constructor
     */
    public function __construct() {
        // Schedule daily digest cron
        add_action( 'init', array( $this, 'schedule_digest_cron' ) );
        add_action( self::CRON_HOOK, array( $this, 'send_daily_digest' ) );

        // Clean up on deactivation
        register_deactivation_hook( RAS_WF_PLUGIN_FILE, array( $this, 'unschedule_cron' ) );
    }

    /**
     * Schedule daily digest cron at 15:00 EST
     */
    public function schedule_digest_cron() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            // Calculate next 15:00 EST
            $timezone = new DateTimeZone( 'America/New_York' );
            $now      = new DateTime( 'now', $timezone );
            $target   = new DateTime( 'today 15:00', $timezone );

            // If already past 15:00 today, schedule for tomorrow
            if ( $now > $target ) {
                $target->modify( '+1 day' );
            }

            wp_schedule_event( $target->getTimestamp(), 'daily', self::CRON_HOOK );
        }
    }

    /**
     * Unschedule cron on deactivation
     */
    public function unschedule_cron() {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }
    }

    /**
     * Create digest queue table
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name      = $wpdb->prefix . self::DIGEST_TABLE;

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            notification_type VARCHAR(50) NOT NULL,
            feedback_id BIGINT UNSIGNED NOT NULL,
            reply_id BIGINT UNSIGNED NULL,
            actor_name VARCHAR(100) NOT NULL,
            old_status VARCHAR(20) NULL,
            new_status VARCHAR(20) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_created (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Get user notification preference
     *
     * @param int $user_id User ID.
     * @return string 'live', 'digest', or 'off'
     */
    public static function get_user_preference( $user_id ) {
        $prefs = get_option( self::OPTION_USER_PREFS, array() );
        return isset( $prefs[ $user_id ] ) ? $prefs[ $user_id ] : self::MODE_LIVE;
    }

    /**
     * Set user notification preference
     *
     * @param int    $user_id User ID.
     * @param string $mode    'live', 'digest', or 'off'.
     * @return bool
     */
    public static function set_user_preference( $user_id, $mode ) {
        if ( ! in_array( $mode, array( self::MODE_LIVE, self::MODE_DIGEST, self::MODE_OFF ), true ) ) {
            return false;
        }

        $prefs              = get_option( self::OPTION_USER_PREFS, array() );
        $prefs[ $user_id ]  = $mode;
        return update_option( self::OPTION_USER_PREFS, $prefs );
    }

    /**
     * Get all user preferences
     *
     * @return array
     */
    public static function get_all_preferences() {
        return get_option( self::OPTION_USER_PREFS, array() );
    }

    /**
     * Notify on new feedback
     *
     * @param int    $feedback_id Feedback post ID.
     * @param string $actor_name  Name of user who submitted.
     */
    public function notify_new_feedback( $feedback_id, $actor_name ) {
        $this->send_notifications( self::TYPE_NEW_FEEDBACK, $feedback_id, $actor_name );
    }

    /**
     * Notify on new reply
     *
     * @param int    $feedback_id Feedback post ID.
     * @param int    $reply_id    Reply ID.
     * @param string $actor_name  Name of user who replied.
     */
    public function notify_new_reply( $feedback_id, $reply_id, $actor_name ) {
        $this->send_notifications( self::TYPE_NEW_REPLY, $feedback_id, $actor_name, array(
            'reply_id' => $reply_id,
        ) );
    }

    /**
     * Notify on status change
     *
     * @param int    $feedback_id Feedback post ID.
     * @param string $old_status  Previous status.
     * @param string $new_status  New status.
     * @param string $actor_name  Name of user who changed status.
     */
    public function notify_status_change( $feedback_id, $old_status, $new_status, $actor_name ) {
        // Only notify on Pending or Resolved
        if ( ! in_array( $new_status, array( 'pending', 'resolved' ), true ) ) {
            return;
        }

        $this->send_notifications( self::TYPE_STATUS_CHANGE, $feedback_id, $actor_name, array(
            'old_status' => $old_status,
            'new_status' => $new_status,
        ) );
    }

    /**
     * Send notifications to all enabled users
     *
     * @param string $type        Notification type.
     * @param int    $feedback_id Feedback post ID.
     * @param string $actor_name  Actor name.
     * @param array  $extra       Extra data.
     */
    private function send_notifications( $type, $feedback_id, $actor_name, $extra = array() ) {
        $enabled_users = RAS_WF_User_Access::get_enabled_users();

        foreach ( $enabled_users as $user_id ) {
            $preference = self::get_user_preference( $user_id );

            if ( self::MODE_OFF === $preference ) {
                continue;
            }

            if ( self::MODE_LIVE === $preference ) {
                $this->send_live_email( $user_id, $type, $feedback_id, $actor_name, $extra );
            } else {
                $this->queue_for_digest( $user_id, $type, $feedback_id, $actor_name, $extra );
            }
        }
    }

    /**
     * Send immediate email
     *
     * @param int    $user_id     Recipient user ID.
     * @param string $type        Notification type.
     * @param int    $feedback_id Feedback post ID.
     * @param string $actor_name  Actor name.
     * @param array  $extra       Extra data.
     */
    private function send_live_email( $user_id, $type, $feedback_id, $actor_name, $extra = array() ) {
        $user = get_user_by( 'ID', $user_id );
        if ( ! $user || ! $user->user_email ) {
            return;
        }

        $feedback = get_post( $feedback_id );
        if ( ! $feedback ) {
            return;
        }

        $page_url  = get_post_meta( $feedback_id, '_ras_wf_page_url', true );
        $page_path = get_post_meta( $feedback_id, '_ras_wf_page_path', true );
        $comment   = $feedback->post_content;

        $subject = $this->get_email_subject( $type, $page_path, $extra );
        $message = $this->get_email_body( $type, $actor_name, $page_url, $page_path, $comment, $extra );

        $headers = array( 'Content-Type: text/html; charset=UTF-8' );

        wp_mail( $user->user_email, $subject, $message, $headers );
    }

    /**
     * Queue notification for daily digest
     *
     * @param int    $user_id     Recipient user ID.
     * @param string $type        Notification type.
     * @param int    $feedback_id Feedback post ID.
     * @param string $actor_name  Actor name.
     * @param array  $extra       Extra data.
     */
    private function queue_for_digest( $user_id, $type, $feedback_id, $actor_name, $extra = array() ) {
        global $wpdb;

        $table = $wpdb->prefix . self::DIGEST_TABLE;

        $data = array(
            'user_id'           => $user_id,
            'notification_type' => $type,
            'feedback_id'       => $feedback_id,
            'actor_name'        => $actor_name,
            'created_at'        => current_time( 'mysql' ),
        );

        if ( ! empty( $extra['reply_id'] ) ) {
            $data['reply_id'] = $extra['reply_id'];
        }
        if ( ! empty( $extra['old_status'] ) ) {
            $data['old_status'] = $extra['old_status'];
        }
        if ( ! empty( $extra['new_status'] ) ) {
            $data['new_status'] = $extra['new_status'];
        }

        $wpdb->insert( $table, $data );
    }

    /**
     * Send daily digest emails
     */
    public function send_daily_digest() {
        global $wpdb;

        $table = $wpdb->prefix . self::DIGEST_TABLE;

        // Get all pending digest items grouped by user
        $users = $wpdb->get_col( "SELECT DISTINCT user_id FROM {$table}" );

        foreach ( $users as $user_id ) {
            $items = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at ASC",
                    $user_id
                )
            );

            if ( empty( $items ) ) {
                continue;
            }

            $user = get_user_by( 'ID', $user_id );
            if ( ! $user || ! $user->user_email ) {
                continue;
            }

            // Build digest email
            $subject = sprintf(
                /* translators: %s: site name */
                __( '[%s] Website Feedback Daily Digest', 'ras-website-feedback' ),
                get_bloginfo( 'name' )
            );

            $message = $this->build_digest_email( $items );

            $headers = array( 'Content-Type: text/html; charset=UTF-8' );

            wp_mail( $user->user_email, $subject, $message, $headers );

            // Clear processed items
            $wpdb->delete( $table, array( 'user_id' => $user_id ) );
        }
    }

    /**
     * Get email subject based on type
     *
     * @param string $type      Notification type.
     * @param string $page_path Page path.
     * @param array  $extra     Extra data.
     * @return string
     */
    private function get_email_subject( $type, $page_path, $extra = array() ) {
        $site_name = get_bloginfo( 'name' );

        switch ( $type ) {
            case self::TYPE_NEW_FEEDBACK:
                return sprintf(
                    /* translators: %1$s: site name, %2$s: page path */
                    __( '[%1$s] New feedback on %2$s', 'ras-website-feedback' ),
                    $site_name,
                    $page_path
                );

            case self::TYPE_NEW_REPLY:
                return sprintf(
                    /* translators: %1$s: site name, %2$s: page path */
                    __( '[%1$s] New reply to feedback on %2$s', 'ras-website-feedback' ),
                    $site_name,
                    $page_path
                );

            case self::TYPE_STATUS_CHANGE:
                return sprintf(
                    /* translators: %1$s: site name, %2$s: new status */
                    __( '[%1$s] Feedback marked as %2$s', 'ras-website-feedback' ),
                    $site_name,
                    ucfirst( $extra['new_status'] ?? 'updated' )
                );

            default:
                return sprintf(
                    /* translators: %s: site name */
                    __( '[%s] Website Feedback Notification', 'ras-website-feedback' ),
                    $site_name
                );
        }
    }

    /**
     * Get email body based on type
     *
     * @param string $type       Notification type.
     * @param string $actor_name Actor name.
     * @param string $page_url   Page URL.
     * @param string $page_path  Page path.
     * @param string $comment    Feedback comment.
     * @param array  $extra      Extra data.
     * @return string
     */
    private function get_email_body( $type, $actor_name, $page_url, $page_path, $comment, $extra = array() ) {
        $admin_url = admin_url( 'admin.php?page=ras-website-feedback' );

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2271b1; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
                .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-top: none; }
                .footer { padding: 15px 20px; font-size: 12px; color: #666; border: 1px solid #ddd; border-top: none; border-radius: 0 0 8px 8px; }
                .button { display: inline-block; background: #2271b1; color: white !important; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin-top: 15px; }
                .meta { background: #fff; padding: 10px; border-radius: 4px; margin: 10px 0; }
                .meta strong { color: #1d2327; }
                .status-badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 12px; font-weight: 600; }
                .status-pending { background: #dba617; color: #fff; }
                .status-resolved { background: #00a32a; color: #fff; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2 style="margin:0;"><?php echo esc_html( $this->get_email_title( $type, $extra ) ); ?></h2>
                </div>
                <div class="content">
                    <?php echo $this->get_email_content( $type, $actor_name, $page_url, $page_path, $comment, $extra ); ?>
                    <a href="<?php echo esc_url( $admin_url ); ?>" class="button">
                        <?php esc_html_e( 'View in Dashboard', 'ras-website-feedback' ); ?>
                    </a>
                </div>
                <div class="footer">
                    <?php
                    printf(
                        /* translators: %s: site name */
                        esc_html__( 'This notification was sent from %s Website Feedback.', 'ras-website-feedback' ),
                        get_bloginfo( 'name' )
                    );
                    ?>
                    <br>
                    <?php esc_html_e( 'You can change your notification preferences in the feedback dashboard.', 'ras-website-feedback' ); ?>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Get email title
     *
     * @param string $type  Notification type.
     * @param array  $extra Extra data.
     * @return string
     */
    private function get_email_title( $type, $extra = array() ) {
        switch ( $type ) {
            case self::TYPE_NEW_FEEDBACK:
                return __( 'New Feedback Submitted', 'ras-website-feedback' );
            case self::TYPE_NEW_REPLY:
                return __( 'New Reply to Feedback', 'ras-website-feedback' );
            case self::TYPE_STATUS_CHANGE:
                return sprintf(
                    /* translators: %s: status name */
                    __( 'Feedback Marked as %s', 'ras-website-feedback' ),
                    ucfirst( $extra['new_status'] ?? 'Updated' )
                );
            default:
                return __( 'Website Feedback Update', 'ras-website-feedback' );
        }
    }

    /**
     * Get email content HTML
     *
     * @param string $type       Notification type.
     * @param string $actor_name Actor name.
     * @param string $page_url   Page URL.
     * @param string $page_path  Page path.
     * @param string $comment    Feedback comment.
     * @param array  $extra      Extra data.
     * @return string
     */
    private function get_email_content( $type, $actor_name, $page_url, $page_path, $comment, $extra = array() ) {
        ob_start();

        switch ( $type ) {
            case self::TYPE_NEW_FEEDBACK:
                ?>
                <p>
                    <?php
                    printf(
                        /* translators: %s: user name */
                        esc_html__( '%s submitted new feedback:', 'ras-website-feedback' ),
                        '<strong>' . esc_html( $actor_name ) . '</strong>'
                    );
                    ?>
                </p>
                <div class="meta">
                    <strong><?php esc_html_e( 'Page:', 'ras-website-feedback' ); ?></strong>
                    <a href="<?php echo esc_url( $page_url ); ?>"><?php echo esc_html( $page_path ); ?></a>
                </div>
                <div class="meta">
                    <strong><?php esc_html_e( 'Comment:', 'ras-website-feedback' ); ?></strong><br>
                    <?php echo wp_kses_post( wpautop( $comment ) ); ?>
                </div>
                <?php
                break;

            case self::TYPE_NEW_REPLY:
                ?>
                <p>
                    <?php
                    printf(
                        /* translators: %s: user name */
                        esc_html__( '%s replied to feedback:', 'ras-website-feedback' ),
                        '<strong>' . esc_html( $actor_name ) . '</strong>'
                    );
                    ?>
                </p>
                <div class="meta">
                    <strong><?php esc_html_e( 'Page:', 'ras-website-feedback' ); ?></strong>
                    <a href="<?php echo esc_url( $page_url ); ?>"><?php echo esc_html( $page_path ); ?></a>
                </div>
                <div class="meta">
                    <strong><?php esc_html_e( 'Original Feedback:', 'ras-website-feedback' ); ?></strong><br>
                    <?php echo wp_kses_post( wpautop( $comment ) ); ?>
                </div>
                <?php
                break;

            case self::TYPE_STATUS_CHANGE:
                $status_class = 'status-' . ( $extra['new_status'] ?? 'pending' );
                ?>
                <p>
                    <?php
                    printf(
                        /* translators: %s: user name */
                        esc_html__( '%s updated feedback status:', 'ras-website-feedback' ),
                        '<strong>' . esc_html( $actor_name ) . '</strong>'
                    );
                    ?>
                </p>
                <div class="meta">
                    <strong><?php esc_html_e( 'Status:', 'ras-website-feedback' ); ?></strong>
                    <span class="status-badge <?php echo esc_attr( $status_class ); ?>">
                        <?php echo esc_html( ucfirst( $extra['new_status'] ?? 'Updated' ) ); ?>
                    </span>
                </div>
                <div class="meta">
                    <strong><?php esc_html_e( 'Page:', 'ras-website-feedback' ); ?></strong>
                    <a href="<?php echo esc_url( $page_url ); ?>"><?php echo esc_html( $page_path ); ?></a>
                </div>
                <div class="meta">
                    <strong><?php esc_html_e( 'Feedback:', 'ras-website-feedback' ); ?></strong><br>
                    <?php echo wp_kses_post( wpautop( $comment ) ); ?>
                </div>
                <?php
                break;
        }

        return ob_get_clean();
    }

    /**
     * Build digest email content
     *
     * @param array $items Queued notification items.
     * @return string
     */
    private function build_digest_email( $items ) {
        $admin_url = admin_url( 'admin.php?page=ras-website-feedback' );

        // Group by type
        $grouped = array(
            self::TYPE_NEW_FEEDBACK  => array(),
            self::TYPE_NEW_REPLY     => array(),
            self::TYPE_STATUS_CHANGE => array(),
        );

        foreach ( $items as $item ) {
            if ( isset( $grouped[ $item->notification_type ] ) ) {
                $grouped[ $item->notification_type ][] = $item;
            }
        }

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2271b1; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
                .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-top: none; }
                .footer { padding: 15px 20px; font-size: 12px; color: #666; border: 1px solid #ddd; border-top: none; border-radius: 0 0 8px 8px; }
                .button { display: inline-block; background: #2271b1; color: white !important; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin-top: 15px; }
                .section { margin-bottom: 20px; }
                .section h3 { color: #1d2327; margin-bottom: 10px; padding-bottom: 5px; border-bottom: 2px solid #2271b1; }
                .item { background: #fff; padding: 10px; border-radius: 4px; margin-bottom: 10px; border-left: 3px solid #2271b1; }
                .item-meta { font-size: 12px; color: #666; }
                .status-badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: 600; }
                .status-pending { background: #dba617; color: #fff; }
                .status-resolved { background: #00a32a; color: #fff; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2 style="margin:0;"><?php esc_html_e( 'Website Feedback Daily Digest', 'ras-website-feedback' ); ?></h2>
                    <p style="margin:10px 0 0;opacity:0.9;">
                        <?php
                        printf(
                            /* translators: %d: number of updates */
                            esc_html( _n( '%d update today', '%d updates today', count( $items ), 'ras-website-feedback' ) ),
                            count( $items )
                        );
                        ?>
                    </p>
                </div>
                <div class="content">
                    <?php if ( ! empty( $grouped[ self::TYPE_NEW_FEEDBACK ] ) ) : ?>
                        <div class="section">
                            <h3><?php esc_html_e( 'New Feedback', 'ras-website-feedback' ); ?> (<?php echo count( $grouped[ self::TYPE_NEW_FEEDBACK ] ); ?>)</h3>
                            <?php foreach ( $grouped[ self::TYPE_NEW_FEEDBACK ] as $item ) : ?>
                                <?php $feedback = get_post( $item->feedback_id ); ?>
                                <?php if ( $feedback ) : ?>
                                    <div class="item">
                                        <strong><?php echo esc_html( get_post_meta( $item->feedback_id, '_ras_wf_page_path', true ) ); ?></strong>
                                        <p style="margin:5px 0;"><?php echo esc_html( wp_trim_words( $feedback->post_content, 20 ) ); ?></p>
                                        <div class="item-meta">
                                            <?php
                                            printf(
                                                /* translators: %1$s: user name, %2$s: time */
                                                esc_html__( 'By %1$s at %2$s', 'ras-website-feedback' ),
                                                esc_html( $item->actor_name ),
                                                esc_html( date_i18n( get_option( 'time_format' ), strtotime( $item->created_at ) ) )
                                            );
                                            ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ( ! empty( $grouped[ self::TYPE_NEW_REPLY ] ) ) : ?>
                        <div class="section">
                            <h3><?php esc_html_e( 'New Replies', 'ras-website-feedback' ); ?> (<?php echo count( $grouped[ self::TYPE_NEW_REPLY ] ); ?>)</h3>
                            <?php foreach ( $grouped[ self::TYPE_NEW_REPLY ] as $item ) : ?>
                                <div class="item">
                                    <strong><?php echo esc_html( get_post_meta( $item->feedback_id, '_ras_wf_page_path', true ) ); ?></strong>
                                    <div class="item-meta">
                                        <?php
                                        printf(
                                            /* translators: %1$s: user name, %2$s: time */
                                            esc_html__( 'Reply by %1$s at %2$s', 'ras-website-feedback' ),
                                            esc_html( $item->actor_name ),
                                            esc_html( date_i18n( get_option( 'time_format' ), strtotime( $item->created_at ) ) )
                                        );
                                        ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ( ! empty( $grouped[ self::TYPE_STATUS_CHANGE ] ) ) : ?>
                        <div class="section">
                            <h3><?php esc_html_e( 'Status Updates', 'ras-website-feedback' ); ?> (<?php echo count( $grouped[ self::TYPE_STATUS_CHANGE ] ); ?>)</h3>
                            <?php foreach ( $grouped[ self::TYPE_STATUS_CHANGE ] as $item ) : ?>
                                <div class="item">
                                    <strong><?php echo esc_html( get_post_meta( $item->feedback_id, '_ras_wf_page_path', true ) ); ?></strong>
                                    <span class="status-badge status-<?php echo esc_attr( $item->new_status ); ?>">
                                        <?php echo esc_html( ucfirst( $item->new_status ) ); ?>
                                    </span>
                                    <div class="item-meta">
                                        <?php
                                        printf(
                                            /* translators: %1$s: user name, %2$s: time */
                                            esc_html__( 'By %1$s at %2$s', 'ras-website-feedback' ),
                                            esc_html( $item->actor_name ),
                                            esc_html( date_i18n( get_option( 'time_format' ), strtotime( $item->created_at ) ) )
                                        );
                                        ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <a href="<?php echo esc_url( $admin_url ); ?>" class="button">
                        <?php esc_html_e( 'View All Feedback', 'ras-website-feedback' ); ?>
                    </a>
                </div>
                <div class="footer">
                    <?php
                    printf(
                        /* translators: %s: site name */
                        esc_html__( 'This digest was sent from %s Website Feedback.', 'ras-website-feedback' ),
                        get_bloginfo( 'name' )
                    );
                    ?>
                    <br>
                    <?php esc_html_e( 'Digest is sent daily at 3:00 PM EST. Change your preferences in the feedback dashboard.', 'ras-website-feedback' ); ?>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
