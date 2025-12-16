/**
 * RAS Website Feedback - Frontend JavaScript
 *
 * Visual feedback tool with element inspector functionality.
 * Performance-optimized: Uses requestAnimationFrame, passive listeners, deferred initialization.
 *
 * @package RAS_Website_Feedback
 */

(function() {
    'use strict';

    // Settings from PHP
    const settings = window.rasWfFrontend || {};

    // State
    const state = {
        initialized: false,
        selectionMode: false,
        drawerOpen: false,
        drawerTab: 'unresolved',
        feedbacks: [],
        currentTarget: null,
        rafId: null
    };

    // Cache DOM references
    const dom = {};

    /**
     * Initialize when browser is idle
     */
    function scheduleInit() {
        if ('requestIdleCallback' in window) {
            requestIdleCallback(init, { timeout: 2000 });
        } else {
            setTimeout(init, 100);
        }
    }

    /**
     * Main initialization
     */
    function init() {
        if (state.initialized) return;
        state.initialized = true;

        createUI();
        bindEvents();
        loadFeedbacks();
    }

    /**
     * Create all UI elements
     */
    function createUI() {
        // Container
        const container = document.createElement('div');
        container.id = 'ras-wf-container';
        container.innerHTML = getUITemplate();
        document.body.appendChild(container);

        // Cache DOM references
        dom.fab = container.querySelector('.ras-wf-fab');
        dom.fabCount = container.querySelector('.ras-wf-fab-count');
        dom.highlighter = container.querySelector('.ras-wf-highlighter');
        dom.banner = container.querySelector('.ras-wf-selection-banner');
        dom.modal = container.querySelector('.ras-wf-modal');
        dom.modalOverlay = container.querySelector('.ras-wf-modal-overlay');
        dom.drawer = container.querySelector('.ras-wf-drawer');
        dom.drawerOverlay = container.querySelector('.ras-wf-drawer-overlay');
        dom.toast = container.querySelector('.ras-wf-toast');
        dom.feedbackForm = container.querySelector('.ras-wf-feedback-form');
        dom.feedbackContent = container.querySelector('#ras-wf-feedback-content');
        dom.elementPreview = container.querySelector('.ras-wf-element-preview');
        dom.feedbackList = container.querySelector('.ras-wf-feedback-list');
        dom.drawerTabs = container.querySelectorAll('.ras-wf-drawer-tab');
    }

    /**
     * Get UI template HTML
     */
    function getUITemplate() {
        return `
            <!-- Floating Action Button -->
            <button type="button" class="ras-wf-fab" aria-label="${settings.i18n.openFeedback}">
                <svg viewBox="0 0 24 24" fill="currentColor" width="24" height="24">
                    <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/>
                    <path d="M7 9h10v2H7zm0-3h10v2H7zm0 6h7v2H7z"/>
                </svg>
                <span class="ras-wf-fab-count" style="display:none;">0</span>
            </button>

            <!-- Element Highlighter -->
            <div class="ras-wf-highlighter" aria-hidden="true"></div>

            <!-- Selection Mode Banner -->
            <div class="ras-wf-selection-banner" aria-live="polite">
                <span>${settings.i18n.selectElement}</span>
                <button type="button" class="ras-wf-cancel-selection">${settings.i18n.cancel}</button>
            </div>

            <!-- Feedback Modal -->
            <div class="ras-wf-modal-overlay" aria-hidden="true"></div>
            <div class="ras-wf-modal" role="dialog" aria-labelledby="ras-wf-modal-title" aria-hidden="true">
                <div class="ras-wf-modal-header">
                    <h3 id="ras-wf-modal-title">${settings.i18n.addFeedback}</h3>
                    <button type="button" class="ras-wf-modal-close" aria-label="${settings.i18n.close}">&times;</button>
                </div>
                <div class="ras-wf-modal-body">
                    <div class="ras-wf-element-preview"></div>
                    <form class="ras-wf-feedback-form">
                        <label for="ras-wf-feedback-content">${settings.i18n.yourFeedback}</label>
                        <textarea id="ras-wf-feedback-content" rows="4" placeholder="${settings.i18n.feedbackPlaceholder}" required></textarea>
                        <div class="ras-wf-modal-actions">
                            <button type="button" class="ras-wf-btn ras-wf-btn-secondary ras-wf-modal-cancel">${settings.i18n.cancel}</button>
                            <button type="submit" class="ras-wf-btn ras-wf-btn-primary">${settings.i18n.submit}</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Feedback Drawer -->
            <div class="ras-wf-drawer-overlay" aria-hidden="true"></div>
            <div class="ras-wf-drawer" aria-hidden="true">
                <div class="ras-wf-drawer-header">
                    <h3>${settings.i18n.pageFeedback}</h3>
                    <button type="button" class="ras-wf-drawer-close" aria-label="${settings.i18n.close}">&times;</button>
                </div>
                <div class="ras-wf-drawer-tabs">
                    <button type="button" class="ras-wf-drawer-tab active" data-tab="unresolved">
                        ${settings.i18n.unresolved} <span class="ras-wf-tab-count" data-count="unresolved">0</span>
                    </button>
                    <button type="button" class="ras-wf-drawer-tab" data-tab="pending">
                        ${settings.i18n.pending} <span class="ras-wf-tab-count" data-count="pending">0</span>
                    </button>
                    <button type="button" class="ras-wf-drawer-tab" data-tab="resolved">
                        ${settings.i18n.resolved} <span class="ras-wf-tab-count" data-count="resolved">0</span>
                    </button>
                </div>
                <div class="ras-wf-drawer-content">
                    <div class="ras-wf-feedback-list"></div>
                    <button type="button" class="ras-wf-add-feedback-btn">
                        <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
                            <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                        </svg>
                        ${settings.i18n.addNew}
                    </button>
                </div>
            </div>

            <!-- Toast Notification -->
            <div class="ras-wf-toast" role="alert" aria-live="polite"></div>
        `;
    }

    /**
     * Bind all event listeners
     */
    function bindEvents() {
        // FAB click - toggle drawer
        dom.fab.addEventListener('click', toggleDrawer);

        // Selection mode banner cancel
        dom.banner.querySelector('.ras-wf-cancel-selection').addEventListener('click', exitSelectionMode);

        // Modal close buttons
        dom.modal.querySelector('.ras-wf-modal-close').addEventListener('click', closeModal);
        dom.modal.querySelector('.ras-wf-modal-cancel').addEventListener('click', closeModal);
        dom.modalOverlay.addEventListener('click', closeModal);

        // Drawer close
        dom.drawer.querySelector('.ras-wf-drawer-close').addEventListener('click', closeDrawer);
        dom.drawerOverlay.addEventListener('click', closeDrawer);

        // Drawer tabs
        dom.drawerTabs.forEach(tab => {
            tab.addEventListener('click', () => switchTab(tab.dataset.tab));
        });

        // Add feedback button in drawer
        dom.drawer.querySelector('.ras-wf-add-feedback-btn').addEventListener('click', () => {
            closeDrawer();
            enterSelectionMode();
        });

        // Feedback form submit
        dom.feedbackForm.addEventListener('submit', handleFeedbackSubmit);

        // Keyboard shortcuts
        document.addEventListener('keydown', handleKeyboard);

        // Window resize - update highlighter
        window.addEventListener('resize', () => {
            if (state.selectionMode && state.currentTarget) {
                updateHighlighter(state.currentTarget);
            }
        }, { passive: true });
    }

    /**
     * Enter element selection mode
     */
    function enterSelectionMode() {
        state.selectionMode = true;
        dom.banner.classList.add('active');
        document.body.style.cursor = 'crosshair';

        // Add mouse tracking
        document.addEventListener('mousemove', handleMouseMove, { passive: true });
        document.addEventListener('click', handleElementClick, { capture: true });

        // Prevent scrolling from affecting other things
        document.addEventListener('scroll', handleScroll, { passive: true });
    }

    /**
     * Exit element selection mode
     */
    function exitSelectionMode() {
        state.selectionMode = false;
        state.currentTarget = null;
        dom.banner.classList.remove('active');
        dom.highlighter.classList.remove('active');
        document.body.style.cursor = '';

        document.removeEventListener('mousemove', handleMouseMove);
        document.removeEventListener('click', handleElementClick, { capture: true });
        document.removeEventListener('scroll', handleScroll);

        if (state.rafId) {
            cancelAnimationFrame(state.rafId);
            state.rafId = null;
        }
    }

    /**
     * Handle mouse movement during selection mode
     */
    function handleMouseMove(e) {
        if (!state.selectionMode) return;

        // Throttle with rAF
        if (state.rafId) return;

        state.rafId = requestAnimationFrame(() => {
            state.rafId = null;

            const target = document.elementFromPoint(e.clientX, e.clientY);

            // Ignore our own UI elements
            if (!target || target.closest('#ras-wf-container')) {
                dom.highlighter.classList.remove('active');
                state.currentTarget = null;
                return;
            }

            // Skip body/html
            if (target === document.body || target === document.documentElement) {
                dom.highlighter.classList.remove('active');
                state.currentTarget = null;
                return;
            }

            state.currentTarget = target;
            updateHighlighter(target);
        });
    }

    /**
     * Update highlighter position
     */
    function updateHighlighter(element) {
        const rect = element.getBoundingClientRect();

        dom.highlighter.style.top = (rect.top + window.scrollY) + 'px';
        dom.highlighter.style.left = (rect.left + window.scrollX) + 'px';
        dom.highlighter.style.width = rect.width + 'px';
        dom.highlighter.style.height = rect.height + 'px';
        dom.highlighter.classList.add('active');
    }

    /**
     * Handle scroll during selection mode
     */
    function handleScroll() {
        if (state.currentTarget) {
            updateHighlighter(state.currentTarget);
        }
    }

    /**
     * Handle element click during selection mode
     */
    function handleElementClick(e) {
        if (!state.selectionMode) return;

        // Ignore our UI
        if (e.target.closest('#ras-wf-container')) return;

        e.preventDefault();
        e.stopPropagation();

        const target = state.currentTarget || e.target;

        // Store element data for submission
        state.selectedElement = {
            selector: generateSelector(target),
            tagName: target.tagName.toLowerCase(),
            text: (target.textContent || '').substring(0, 100).trim(),
            clickX: e.clientX,
            clickY: e.clientY,
            windowWidth: window.innerWidth,
            windowHeight: window.innerHeight
        };

        exitSelectionMode();
        openModal();
    }

    /**
     * Generate CSS selector for element
     */
    function generateSelector(element) {
        // Check for ID first
        if (element.id) {
            return '#' + CSS.escape(element.id);
        }

        // Build path
        const path = [];
        let current = element;

        while (current && current !== document.body && current !== document.documentElement) {
            let selector = current.tagName.toLowerCase();

            // Add class if present
            if (current.className && typeof current.className === 'string') {
                const classes = current.className.trim().split(/\s+/).filter(c => c && !c.startsWith('ras-wf-'));
                if (classes.length) {
                    selector += '.' + classes.slice(0, 2).map(c => CSS.escape(c)).join('.');
                }
            }

            // Add nth-child for uniqueness
            const parent = current.parentElement;
            if (parent) {
                const siblings = Array.from(parent.children).filter(el => el.tagName === current.tagName);
                if (siblings.length > 1) {
                    const index = siblings.indexOf(current) + 1;
                    selector += ':nth-child(' + index + ')';
                }
            }

            path.unshift(selector);
            current = current.parentElement;

            // Limit depth
            if (path.length >= 5) break;
        }

        return path.join(' > ');
    }

    /**
     * Open feedback modal
     */
    function openModal() {
        dom.modal.classList.add('active');
        dom.modal.setAttribute('aria-hidden', 'false');
        dom.modalOverlay.classList.add('active');

        // Show element preview
        if (state.selectedElement) {
            dom.elementPreview.innerHTML = `
                <div class="ras-wf-preview-tag">&lt;${state.selectedElement.tagName}&gt;</div>
                <div class="ras-wf-preview-text">${escapeHtml(state.selectedElement.text) || '<em>No text content</em>'}</div>
            `;
        }

        // Focus textarea
        setTimeout(() => dom.feedbackContent.focus(), 100);
    }

    /**
     * Close feedback modal
     */
    function closeModal() {
        dom.modal.classList.remove('active');
        dom.modal.setAttribute('aria-hidden', 'true');
        dom.modalOverlay.classList.remove('active');
        dom.feedbackContent.value = '';
        dom.elementPreview.innerHTML = '';
        state.selectedElement = null;
    }

    /**
     * Toggle drawer
     */
    function toggleDrawer() {
        if (state.drawerOpen) {
            closeDrawer();
        } else {
            openDrawer();
        }
    }

    /**
     * Open drawer
     */
    function openDrawer() {
        state.drawerOpen = true;
        dom.drawer.classList.add('active');
        dom.drawer.setAttribute('aria-hidden', 'false');
        dom.drawerOverlay.classList.add('active');
        renderFeedbackList();
    }

    /**
     * Close drawer
     */
    function closeDrawer() {
        state.drawerOpen = false;
        dom.drawer.classList.remove('active');
        dom.drawer.setAttribute('aria-hidden', 'true');
        dom.drawerOverlay.classList.remove('active');
    }

    /**
     * Switch drawer tab
     */
    function switchTab(tab) {
        state.drawerTab = tab;

        dom.drawerTabs.forEach(t => {
            t.classList.toggle('active', t.dataset.tab === tab);
        });

        renderFeedbackList();
    }

    /**
     * Load feedbacks for current page
     */
    function loadFeedbacks() {
        const data = new FormData();
        data.append('action', 'ras_wf_get_page_feedbacks');
        data.append('nonce', settings.nonce);
        data.append('page_url', window.location.href);

        fetch(settings.ajaxurl, {
            method: 'POST',
            body: data,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(response => {
            if (response.success) {
                state.feedbacks = response.data.feedbacks || [];
                updateFabCount();
                updateTabCounts();

                if (state.drawerOpen) {
                    renderFeedbackList();
                }
            }
        })
        .catch(error => {
            console.error('RAS Feedback: Failed to load feedbacks', error);
        });
    }

    /**
     * Update FAB badge count
     */
    function updateFabCount() {
        const unresolvedCount = state.feedbacks.filter(f => f.status === 'unresolved').length;

        if (unresolvedCount > 0) {
            dom.fabCount.textContent = unresolvedCount > 99 ? '99+' : unresolvedCount;
            dom.fabCount.style.display = '';
        } else {
            dom.fabCount.style.display = 'none';
        }
    }

    /**
     * Update tab counts
     */
    function updateTabCounts() {
        const counts = {
            unresolved: state.feedbacks.filter(f => f.status === 'unresolved').length,
            pending: state.feedbacks.filter(f => f.status === 'pending').length,
            resolved: state.feedbacks.filter(f => f.status === 'resolved').length
        };

        Object.keys(counts).forEach(status => {
            const el = dom.drawer.querySelector(`[data-count="${status}"]`);
            if (el) el.textContent = counts[status];
        });
    }

    /**
     * Render feedback list in drawer
     */
    function renderFeedbackList() {
        const filtered = state.feedbacks.filter(f => f.status === state.drawerTab);

        if (filtered.length === 0) {
            dom.feedbackList.innerHTML = `
                <div class="ras-wf-empty-state">
                    <p>${settings.i18n.noFeedback}</p>
                </div>
            `;
            return;
        }

        let html = '';
        filtered.forEach(feedback => {
            html += `
                <div class="ras-wf-feedback-card" data-id="${feedback.id}">
                    <div class="ras-wf-card-header">
                        <span class="ras-wf-card-element">${escapeHtml(feedback.element_selector)}</span>
                        <span class="ras-wf-card-date">${escapeHtml(feedback.date)}</span>
                    </div>
                    <div class="ras-wf-card-content">${escapeHtml(feedback.comment)}</div>
                    <div class="ras-wf-card-meta">
                        <span class="ras-wf-card-author">${escapeHtml(feedback.author_name)}</span>
                        ${feedback.reply_count > 0 ? `<span class="ras-wf-card-replies">${feedback.reply_count} ${settings.i18n.replies}</span>` : ''}
                    </div>
                    <div class="ras-wf-card-actions">
                        <button type="button" class="ras-wf-card-locate" title="${settings.i18n.locateElement}">
                            <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zM7 9c0-2.76 2.24-5 5-5s5 2.24 5 5c0 2.88-2.88 7.19-5 9.88C9.92 16.21 7 11.85 7 9z"/>
                                <circle cx="12" cy="9" r="2.5"/>
                            </svg>
                        </button>
                        ${feedback.status !== 'resolved' ? `
                            <button type="button" class="ras-wf-card-status" data-status="resolved" title="${settings.i18n.markResolved}">
                                <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
                                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                </svg>
                            </button>
                        ` : `
                            <button type="button" class="ras-wf-card-status" data-status="unresolved" title="${settings.i18n.reopen}">
                                <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
                                    <path d="M12 5V1L7 6l5 5V7c3.31 0 6 2.69 6 6s-2.69 6-6 6-6-2.69-6-6H4c0 4.42 3.58 8 8 8s8-3.58 8-8-3.58-8-8-8z"/>
                                </svg>
                            </button>
                        `}
                    </div>
                </div>
            `;
        });

        dom.feedbackList.innerHTML = html;

        // Bind card actions
        dom.feedbackList.querySelectorAll('.ras-wf-card-locate').forEach(btn => {
            btn.addEventListener('click', handleLocateElement);
        });

        dom.feedbackList.querySelectorAll('.ras-wf-card-status').forEach(btn => {
            btn.addEventListener('click', handleStatusChange);
        });
    }

    /**
     * Handle feedback form submission
     */
    function handleFeedbackSubmit(e) {
        e.preventDefault();

        const content = dom.feedbackContent.value.trim();
        if (!content || !state.selectedElement) return;

        const submitBtn = dom.feedbackForm.querySelector('[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = settings.i18n.submitting;

        const data = new FormData();
        data.append('action', 'ras_wf_submit_feedback');
        data.append('nonce', settings.nonce);
        data.append('page_url', window.location.href);
        data.append('page_title', document.title);
        data.append('element_selector', state.selectedElement.selector);
        data.append('element_tag', state.selectedElement.tagName);
        data.append('element_text', state.selectedElement.text);
        data.append('click_x', state.selectedElement.clickX);
        data.append('click_y', state.selectedElement.clickY);
        data.append('window_width', state.selectedElement.windowWidth);
        data.append('window_height', state.selectedElement.windowHeight);
        data.append('comment', content);

        // Add guest token if present
        const guestToken = getGuestToken();
        if (guestToken) {
            data.append('guest_token', guestToken);
        }

        fetch(settings.ajaxurl, {
            method: 'POST',
            body: data,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(response => {
            submitBtn.disabled = false;
            submitBtn.textContent = settings.i18n.submit;

            if (response.success) {
                closeModal();
                showToast(settings.i18n.feedbackSubmitted);
                loadFeedbacks(); // Refresh list
            } else {
                showToast(response.data?.message || settings.i18n.error, 'error');
            }
        })
        .catch(error => {
            submitBtn.disabled = false;
            submitBtn.textContent = settings.i18n.submit;
            showToast(settings.i18n.error, 'error');
            console.error('RAS Feedback: Submit failed', error);
        });
    }

    /**
     * Handle locate element button
     */
    function handleLocateElement(e) {
        const card = e.target.closest('.ras-wf-feedback-card');
        const feedbackId = card.dataset.id;
        const feedback = state.feedbacks.find(f => f.id == feedbackId);

        if (!feedback || !feedback.element_selector) return;

        closeDrawer();

        // Try to find and highlight element
        try {
            const element = document.querySelector(feedback.element_selector);
            if (element) {
                // Scroll into view
                element.scrollIntoView({ behavior: 'smooth', block: 'center' });

                // Highlight temporarily
                updateHighlighter(element);
                dom.highlighter.classList.add('active', 'ras-wf-locate-highlight');

                setTimeout(() => {
                    dom.highlighter.classList.remove('active', 'ras-wf-locate-highlight');
                }, 3000);
            } else {
                showToast(settings.i18n.elementNotFound, 'warning');
            }
        } catch (err) {
            showToast(settings.i18n.elementNotFound, 'warning');
        }
    }

    /**
     * Handle status change button
     */
    function handleStatusChange(e) {
        const btn = e.target.closest('.ras-wf-card-status');
        const card = btn.closest('.ras-wf-feedback-card');
        const feedbackId = card.dataset.id;
        const newStatus = btn.dataset.status;

        btn.disabled = true;

        const data = new FormData();
        data.append('action', 'ras_wf_update_status');
        data.append('nonce', settings.nonce);
        data.append('feedback_id', feedbackId);
        data.append('status', newStatus);

        fetch(settings.ajaxurl, {
            method: 'POST',
            body: data,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(response => {
            btn.disabled = false;

            if (response.success) {
                // Update local state
                const feedback = state.feedbacks.find(f => f.id == feedbackId);
                if (feedback) {
                    feedback.status = newStatus;
                }

                updateFabCount();
                updateTabCounts();
                renderFeedbackList();
                showToast(settings.i18n.statusUpdated);
            } else {
                showToast(response.data?.message || settings.i18n.error, 'error');
            }
        })
        .catch(error => {
            btn.disabled = false;
            showToast(settings.i18n.error, 'error');
        });
    }

    /**
     * Handle keyboard shortcuts
     */
    function handleKeyboard(e) {
        // Escape - exit selection mode or close modal/drawer
        if (e.key === 'Escape') {
            if (state.selectionMode) {
                exitSelectionMode();
            } else if (dom.modal.classList.contains('active')) {
                closeModal();
            } else if (state.drawerOpen) {
                closeDrawer();
            }
        }
    }

    /**
     * Show toast notification
     */
    function showToast(message, type = 'success') {
        dom.toast.textContent = message;
        dom.toast.className = 'ras-wf-toast active ras-wf-toast-' + type;

        // Auto-hide
        setTimeout(() => {
            dom.toast.classList.remove('active');
        }, 3000);
    }

    /**
     * Get guest token from URL
     */
    function getGuestToken() {
        const params = new URLSearchParams(window.location.search);
        return params.get(settings.guestParam);
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

    // Start initialization
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scheduleInit);
    } else {
        scheduleInit();
    }

})();
