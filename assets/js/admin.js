/**
 * RAS Website Feedback - Admin JavaScript
 *
 * @package RAS_Website_Feedback
 */

(function($) {
    'use strict';

    // Settings object from PHP
    const settings = window.rasWfAdmin || {};

    /**
     * Initialize admin functionality
     */
    function init() {
        initUserSearch();
        initUserManagement();
        initFeedbackActions();
        initReplies();
        initSettings();
    }

    /**
     * User search functionality
     */
    function initUserSearch() {
        const $searchInput = $('#ras-wf-user-search');
        const $results = $('#ras-wf-search-results');

        if (!$searchInput.length) return;

        let searchTimeout;

        $searchInput.on('input', function() {
            const query = $(this).val().trim();

            clearTimeout(searchTimeout);

            if (query.length < 2) {
                $results.removeClass('active').empty();
                return;
            }

            searchTimeout = setTimeout(function() {
                searchUsers(query);
            }, 300);
        });

        // Close on click outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.ras-wf-search-wrapper').length) {
                $results.removeClass('active');
            }
        });

        function searchUsers(query) {
            $results.html('<div class="ras-wf-search-item">' + settings.i18n.loading + '</div>').addClass('active');

            $.post(settings.ajaxurl, {
                action: 'ras_wf_search_users',
                nonce: settings.nonce,
                search: query
            }, function(response) {
                if (response.success && response.data.users.length) {
                    renderSearchResults(response.data.users);
                } else {
                    $results.html('<div class="ras-wf-search-item">' + settings.i18n.noResults + '</div>');
                }
            }).fail(function() {
                $results.html('<div class="ras-wf-search-item">' + settings.i18n.error + '</div>');
            });
        }

        function renderSearchResults(users) {
            let html = '';
            users.forEach(function(user) {
                const enabledClass = user.enabled ? ' ras-wf-is-enabled' : '';
                html += `
                    <div class="ras-wf-search-item${enabledClass}" data-user-id="${user.id}">
                        <img src="${user.avatar}" alt="" class="ras-wf-avatar">
                        <div class="ras-wf-user-info">
                            <span class="ras-wf-user-name">${escapeHtml(user.display_name)}</span>
                            <span class="ras-wf-user-email">${escapeHtml(user.email)}</span>
                        </div>
                    </div>
                `;
            });
            $results.html(html).addClass('active');
        }

        // Add user on click
        $results.on('click', '.ras-wf-search-item', function() {
            const $item = $(this);
            const userId = $item.data('user-id');

            if (!userId || $item.hasClass('ras-wf-is-enabled')) return;

            toggleUser(userId, true, function() {
                $item.addClass('ras-wf-is-enabled');
                $searchInput.val('');
                $results.removeClass('active');
                refreshEnabledUsers();
            });
        });
    }

    /**
     * User management (remove users)
     */
    function initUserManagement() {
        $(document).on('click', '.ras-wf-remove-user', function(e) {
            e.preventDefault();

            if (!confirm(settings.i18n.confirmRemoveUser)) return;

            const $item = $(this).closest('.ras-wf-user-item');
            const userId = $item.data('user-id');

            toggleUser(userId, false, function() {
                $item.fadeOut(200, function() {
                    $(this).remove();
                    updateUserCount();
                });
            });
        });

        // Notification preference change
        $(document).on('change', '.ras-wf-notification-pref', function() {
            const $select = $(this);
            const userId = $select.data('user-id');
            const mode = $select.val();

            $select.prop('disabled', true);

            $.post(settings.ajaxurl, {
                action: 'ras_wf_update_notification_pref',
                nonce: settings.nonce,
                user_id: userId,
                mode: mode
            }, function(response) {
                $select.prop('disabled', false);

                if (!response.success) {
                    alert(response.data?.message || settings.i18n.error);
                }
            }).fail(function() {
                $select.prop('disabled', false);
                alert(settings.i18n.error);
            });
        });
    }

    /**
     * Toggle user access
     */
    function toggleUser(userId, enabled, callback) {
        $.post(settings.ajaxurl, {
            action: 'ras_wf_toggle_user',
            nonce: settings.nonce,
            user_id: userId,
            enabled: enabled ? 1 : 0
        }, function(response) {
            if (response.success && callback) {
                callback();
            } else if (!response.success) {
                alert(response.data?.message || settings.i18n.error);
            }
        }).fail(function() {
            alert(settings.i18n.error);
        });
    }

    /**
     * Refresh enabled users list
     */
    function refreshEnabledUsers() {
        $.post(settings.ajaxurl, {
            action: 'ras_wf_search_users',
            nonce: settings.nonce,
            search: ''
        }, function(response) {
            if (!response.success) return;

            const enabledUsers = response.data.users.filter(u => u.enabled);
            const $list = $('#ras-wf-enabled-users');

            if (enabledUsers.length === 0) {
                $list.html('<p class="ras-wf-no-users">No users enabled. Search and add users above.</p>');
            } else {
                let html = '';
                enabledUsers.forEach(function(user) {
                    html += `
                        <div class="ras-wf-user-item" data-user-id="${user.id}">
                            <img src="${user.avatar}" alt="" class="ras-wf-avatar">
                            <div class="ras-wf-user-info">
                                <span class="ras-wf-user-name">${escapeHtml(user.display_name)}</span>
                                <span class="ras-wf-user-email">${escapeHtml(user.email)}</span>
                            </div>
                            <div class="ras-wf-user-notifications">
                                <select class="ras-wf-notification-pref" data-user-id="${user.id}">
                                    <option value="live">Live emails</option>
                                    <option value="digest">Daily digest</option>
                                    <option value="off">Off</option>
                                </select>
                            </div>
                            <button type="button" class="button ras-wf-remove-user" title="Remove access">
                                <span class="dashicons dashicons-no-alt"></span>
                            </button>
                        </div>
                    `;
                });
                $list.html(html);
            }
            updateUserCount();
        });
    }

    /**
     * Update user count display
     */
    function updateUserCount() {
        const count = $('#ras-wf-enabled-users .ras-wf-user-item').length;
        $('.ras-wf-user-count').text('(' + count + ')');
    }

    /**
     * Feedback actions (status change, delete)
     */
    function initFeedbackActions() {
        // Status change
        $(document).on('click', '.ras-wf-action-status', function() {
            const $btn = $(this);
            const $item = $btn.closest('.ras-wf-feedback-item');
            const feedbackId = $item.data('feedback-id');
            const newStatus = $btn.data('status');

            $item.addClass('ras-wf-loading');

            $.post(settings.ajaxurl, {
                action: 'ras_wf_update_status',
                nonce: settings.nonce,
                feedback_id: feedbackId,
                status: newStatus
            }, function(response) {
                $item.removeClass('ras-wf-loading');

                if (response.success) {
                    // Update UI
                    $item.removeClass('ras-wf-status-unresolved ras-wf-status-pending ras-wf-status-resolved')
                         .addClass('ras-wf-status-' + newStatus);

                    $item.find('.ras-wf-status-badge')
                         .removeClass('ras-wf-badge-unresolved ras-wf-badge-pending ras-wf-badge-resolved')
                         .addClass('ras-wf-badge-' + newStatus)
                         .text(newStatus.charAt(0).toUpperCase() + newStatus.slice(1));

                    // Update button states
                    $item.find('.ras-wf-action-status').prop('disabled', false);
                    $btn.prop('disabled', true);

                    // Update counts
                    if (response.data.counts) {
                        updateStatusCounts(response.data.counts);
                    }
                } else {
                    alert(response.data?.message || settings.i18n.error);
                }
            }).fail(function() {
                $item.removeClass('ras-wf-loading');
                alert(settings.i18n.error);
            });
        });

        // Delete feedback
        $(document).on('click', '.ras-wf-action-delete', function() {
            if (!confirm(settings.i18n.confirmDelete)) return;

            const $item = $(this).closest('.ras-wf-feedback-item');
            const feedbackId = $item.data('feedback-id');

            $item.addClass('ras-wf-loading');

            $.post(settings.ajaxurl, {
                action: 'ras_wf_delete_feedback',
                nonce: settings.nonce,
                feedback_id: feedbackId
            }, function(response) {
                if (response.success) {
                    $item.fadeOut(300, function() {
                        $(this).remove();

                        // Check if list is empty
                        if ($('.ras-wf-feedback-item').length === 0) {
                            $('.ras-wf-feedback-list').html(
                                '<div class="ras-wf-no-results"><p>No feedback found.</p></div>'
                            );
                        }
                    });

                    // Update counts
                    if (response.data.counts) {
                        updateStatusCounts(response.data.counts);
                    }
                } else {
                    $item.removeClass('ras-wf-loading');
                    alert(response.data?.message || settings.i18n.error);
                }
            }).fail(function() {
                $item.removeClass('ras-wf-loading');
                alert(settings.i18n.error);
            });
        });
    }

    /**
     * Update status count displays
     */
    function updateStatusCounts(counts) {
        const total = (counts.unresolved || 0) + (counts.pending || 0) + (counts.resolved || 0);

        $('.ras-wf-stat-total .ras-wf-stat-number').text(total);
        $('.ras-wf-stat-unresolved .ras-wf-stat-number').text(counts.unresolved || 0);
        $('.ras-wf-stat-pending .ras-wf-stat-number').text(counts.pending || 0);
        $('.ras-wf-stat-resolved .ras-wf-stat-number').text(counts.resolved || 0);
    }

    /**
     * Replies functionality
     */
    function initReplies() {
        // Load replies when feedback item is visible
        $('.ras-wf-replies-list').each(function() {
            loadReplies($(this));
        });

        // Submit reply
        $(document).on('click', '.ras-wf-submit-reply', function() {
            const $btn = $(this);
            const $form = $btn.closest('.ras-wf-reply-form');
            const $textarea = $form.find('textarea');
            const $item = $btn.closest('.ras-wf-feedback-item');
            const feedbackId = $item.data('feedback-id');
            const content = $textarea.val().trim();

            if (!content) return;

            $btn.prop('disabled', true).text(settings.i18n.saving);

            $.post(settings.ajaxurl, {
                action: 'ras_wf_add_reply',
                nonce: settings.nonce,
                feedback_id: feedbackId,
                content: content
            }, function(response) {
                $btn.prop('disabled', false).text('Reply');

                if (response.success) {
                    $textarea.val('');
                    renderReplies($item.find('.ras-wf-replies-list'), response.data.replies);
                    $item.find('.ras-wf-reply-count').text('(' + response.data.replies.length + ')');
                } else {
                    alert(response.data?.message || settings.i18n.error);
                }
            }).fail(function() {
                $btn.prop('disabled', false).text('Reply');
                alert(settings.i18n.error);
            });
        });
    }

    /**
     * Load replies for a feedback
     */
    function loadReplies($container) {
        const feedbackId = $container.data('feedback-id');

        $.post(settings.ajaxurl, {
            action: 'ras_wf_get_replies',
            nonce: settings.nonce,
            feedback_id: feedbackId
        }, function(response) {
            if (response.success) {
                renderReplies($container, response.data.replies);
            }
        });
    }

    /**
     * Render replies
     */
    function renderReplies($container, replies) {
        if (!replies || replies.length === 0) {
            $container.html('<p style="color:#646970;font-size:12px;margin:0;">No replies yet.</p>');
            return;
        }

        let html = '';
        replies.forEach(function(reply) {
            html += `
                <div class="ras-wf-reply-item">
                    <div class="ras-wf-reply-header">
                        <span class="ras-wf-reply-author">${escapeHtml(reply.display_name)}</span>
                        <span class="ras-wf-reply-date">${escapeHtml(reply.created_at)}</span>
                    </div>
                    <div class="ras-wf-reply-content">${escapeHtml(reply.reply_content)}</div>
                </div>
            `;
        });

        $container.html(html);
    }

    /**
     * Settings functionality
     */
    function initSettings() {
        // Guest access toggle
        $('#ras-wf-guest-enabled').on('change', function() {
            const enabled = $(this).is(':checked');
            $('.ras-wf-guest-url-row').toggle(enabled);
        });

        // Save settings
        $('#ras-wf-settings-form').on('submit', function(e) {
            e.preventDefault();

            const $form = $(this);
            const $status = $form.find('.ras-wf-save-status');
            const guestEnabled = $('#ras-wf-guest-enabled').is(':checked');

            $status.text(settings.i18n.saving);

            $.post(settings.ajaxurl, {
                action: 'ras_wf_save_settings',
                nonce: settings.nonce,
                guest_enabled: guestEnabled ? 1 : 0
            }, function(response) {
                if (response.success) {
                    $status.text(settings.i18n.saved);

                    if (response.data.guest_url) {
                        $('#ras-wf-guest-url').text(response.data.guest_url);
                        $('.ras-wf-copy-url').data('url', response.data.guest_url);
                    }

                    setTimeout(function() {
                        $status.text('');
                    }, 2000);
                } else {
                    $status.text(settings.i18n.error);
                }
            }).fail(function() {
                $status.text(settings.i18n.error);
            });
        });

        // Copy URL
        $(document).on('click', '.ras-wf-copy-url', function() {
            const url = $(this).data('url');
            copyToClipboard(url);

            const $btn = $(this);
            const originalHtml = $btn.html();
            $btn.html('<span class="dashicons dashicons-yes"></span> Copied!');
            setTimeout(function() {
                $btn.html(originalHtml);
            }, 1500);
        });

        // Regenerate token
        $(document).on('click', '.ras-wf-regenerate-token', function() {
            if (!confirm(settings.i18n.confirmRegenToken)) return;

            const $btn = $(this);
            $btn.prop('disabled', true);

            $.post(settings.ajaxurl, {
                action: 'ras_wf_regenerate_token',
                nonce: settings.nonce
            }, function(response) {
                $btn.prop('disabled', false);

                if (response.success) {
                    $('#ras-wf-guest-url').text(response.data.guest_url);
                    $('.ras-wf-copy-url').data('url', response.data.guest_url);
                    $btn.prev('code').text(response.data.param);
                    alert(response.data.message);
                } else {
                    alert(response.data?.message || settings.i18n.error);
                }
            }).fail(function() {
                $btn.prop('disabled', false);
                alert(settings.i18n.error);
            });
        });
    }

    /**
     * Utility: Escape HTML
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Utility: Copy to clipboard
     */
    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text);
        } else {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
        }
    }

    // Initialize on DOM ready
    $(init);

})(jQuery);
