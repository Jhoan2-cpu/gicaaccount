jQuery(document).ready(function($) {
    'use strict';
    
    // Tab Navigation System
    const TabManager = {
        init: function() {
            this.bindEvents();
            this.setInitialTab();
        },
        
        bindEvents: function() {
            // Direct tab button clicks
            $(document).on('click', '.gica-admin-tab', this.handleTabClick);
            
            // Tab trigger buttons (from dashboard quick actions)
            $(document).on('click', '.gica-admin-tab-trigger', this.handleTabTriggerClick);
            
            // Handle browser back/forward
            $(window).on('popstate', this.handlePopState.bind(this));
        },
        
        handleTabClick: function(e) {
            e.preventDefault();
            const $tab = $(this);
            const tabId = $tab.data('tab');
            
            if (tabId && !$tab.hasClass('active')) {
                TabManager.switchTab(tabId);
                TabManager.updateUrl(tabId);
            }
        },
        
        handleTabTriggerClick: function(e) {
            e.preventDefault();
            const $trigger = $(this);
            const tabId = $trigger.data('tab');
            
            if (tabId) {
                TabManager.switchTab(tabId);
                TabManager.updateUrl(tabId);
            }
        },
        
        switchTab: function(tabId) {
            // Remove active class from all tabs and panels
            $('.gica-admin-tab').removeClass('active');
            $('.gica-tab-panel').removeClass('active');
            
            // Add active class to selected tab and panel
            $(`.gica-admin-tab[data-tab="${tabId}"]`).addClass('active');
            $(`#gica-tab-${tabId}`).addClass('active');
            
            // Trigger custom event
            $(document).trigger('gicaTabChanged', [tabId]);
        },
        
        updateUrl: function(tabId) {
            const url = new URL(window.location);
            url.searchParams.set('tab', tabId);
            window.history.pushState({ tab: tabId }, '', url);
        },
        
        handlePopState: function(e) {
            if (e.originalEvent.state && e.originalEvent.state.tab) {
                this.switchTab(e.originalEvent.state.tab);
            } else {
                this.setInitialTab();
            }
        },
        
        setInitialTab: function() {
            const urlParams = new URLSearchParams(window.location.search);
            const activeTab = urlParams.get('tab') || 'dashboard';
            this.switchTab(activeTab);
        }
    };
    
    // Form Enhancement System
    const FormManager = {
        init: function() {
            this.enhanceInputs();
            this.bindFormEvents();
        },
        
        enhanceInputs: function() {
            // Add floating label effect
            $('.form-table input[type="text"]').each(function() {
                const $input = $(this);
                const $label = $input.closest('tr').find('th label');
                
                if ($label.length) {
                    $input.attr('placeholder', $label.text().replace(':', ''));
                }
            });
        },
        
        bindFormEvents: function() {
            // Form submission handling
            $(document).on('submit', '.gica-fcm-form', this.handleFormSubmit);
            
            // Input validation
            $(document).on('blur', '.form-table input[type="text"]', this.validateInput);
            $(document).on('input', '.form-table input[type="text"]', this.clearValidation);
        },
        
        handleFormSubmit: function(e) {
            const $form = $(this);
            const $submitBtn = $form.find('input[type="submit"]');
            
            // Add loading state
            $submitBtn.prop('disabled', true);
            $submitBtn.val('Guardando...');
            $form.addClass('gica-loading');
            
            // Remove loading state after submission
            setTimeout(function() {
                $submitBtn.prop('disabled', false);
                $submitBtn.val('Guardar Configuración');
                $form.removeClass('gica-loading');
            }, 1000);
        },
        
        validateInput: function() {
            const $input = $(this);
            const value = $input.val().trim();
            const $row = $input.closest('tr');
            
            // Remove existing validation classes
            $row.removeClass('has-error has-success');
            
            if (value && $input.attr('id') === 'server_key') {
                // Basic validation for server key format
                if (value.length < 50) {
                    $row.addClass('has-error');
                    FormManager.showValidationMessage($input, 'El Server Key parece ser muy corto');
                } else {
                    $row.addClass('has-success');
                    FormManager.hideValidationMessage($input);
                }
            }
            
            if (value && $input.attr('id') === 'sender_id') {
                // Basic validation for sender ID (should be numeric)
                if (!/^\d+$/.test(value)) {
                    $row.addClass('has-error');
                    FormManager.showValidationMessage($input, 'El Sender ID debe contener solo números');
                } else {
                    $row.addClass('has-success');
                    FormManager.hideValidationMessage($input);
                }
            }
        },
        
        clearValidation: function() {
            const $input = $(this);
            const $row = $input.closest('tr');
            $row.removeClass('has-error has-success');
            FormManager.hideValidationMessage($input);
        },
        
        showValidationMessage: function($input, message) {
            const $row = $input.closest('tr');
            let $message = $row.find('.validation-message');
            
            if (!$message.length) {
                $message = $('<div class="validation-message"></div>');
                $input.closest('td').append($message);
            }
            
            $message.text(message);
        },
        
        hideValidationMessage: function($input) {
            $input.closest('tr').find('.validation-message').remove();
        }
    };
    
    // Notification System
    const NotificationManager = {
        init: function() {
            this.bindEvents();
            this.autoHideNotices();
        },
        
        bindEvents: function() {
            $(document).on('click', '.notice-dismiss', this.dismissNotice);
        },
        
        dismissNotice: function() {
            $(this).closest('.notice').fadeOut(300, function() {
                $(this).remove();
            });
        },
        
        autoHideNotices: function() {
            $('.notice.is-dismissible').each(function() {
                const $notice = $(this);
                setTimeout(function() {
                    if ($notice.length && $notice.is(':visible')) {
                        $notice.fadeOut(500, function() {
                            $notice.remove();
                        });
                    }
                }, 5000);
            });
        },
        
        show: function(message, type = 'success') {
            const $notice = $(`
                <div class="notice notice-${type} is-dismissible">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Descartar este aviso.</span>
                    </button>
                </div>
            `);
            
            $('.gica-admin-wrap').prepend($notice);
            
            // Auto hide after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(500, function() {
                    $notice.remove();
                });
            }, 5000);
        }
    };
    
    // Statistics Updater
    const StatsManager = {
        init: function() {
            this.updateUserCount();
            this.bindEvents();
        },
        
        bindEvents: function() {
            // Listen for tab changes to update stats if needed
            $(document).on('gicaTabChanged', this.handleTabChange.bind(this));
            
            // Refresh stats button (if added in the future)
            $(document).on('click', '.refresh-stats', this.updateStats.bind(this));
        },
        
        handleTabChange: function(e, tabId) {
            if (tabId === 'dashboard') {
                this.updateUserCount();
            }
        },
        
        updateUserCount: function() {
            const $userCountCard = $('.gica-stat-card').first();
            const $count = $userCountCard.find('h3');
            
            // Add a subtle loading animation
            $count.css('opacity', '0.7');
            
            // In a real implementation, you might fetch this via AJAX
            // For now, we'll just add a nice counter animation
            this.animateNumber($count, parseInt($count.text().replace(/,/g, '')));
        },
        
        animateNumber: function($element, targetNumber) {
            const startNumber = 0;
            const duration = 1000;
            const increment = targetNumber / (duration / 16);
            let currentNumber = startNumber;
            
            const timer = setInterval(function() {
                currentNumber += increment;
                if (currentNumber >= targetNumber) {
                    currentNumber = targetNumber;
                    clearInterval(timer);
                }
                $element.text(Math.floor(currentNumber).toLocaleString());
                $element.css('opacity', '1');
            }, 16);
        },
        
        updateStats: function() {
            // Future implementation for real-time stats updates
            NotificationManager.show('Estadísticas actualizadas', 'info');
        }
    };
    
    // Accessibility Enhancements
    const AccessibilityManager = {
        init: function() {
            this.enhanceKeyboardNavigation();
            this.addAriaLabels();
            this.addSkipLinks();
        },
        
        enhanceKeyboardNavigation: function() {
            // Tab navigation with arrow keys
            $(document).on('keydown', '.gica-admin-tab', function(e) {
                const $tabs = $('.gica-admin-tab');
                const currentIndex = $tabs.index(this);
                let newIndex;
                
                switch(e.keyCode) {
                    case 37: // Left arrow
                        newIndex = currentIndex > 0 ? currentIndex - 1 : $tabs.length - 1;
                        e.preventDefault();
                        break;
                    case 39: // Right arrow
                        newIndex = currentIndex < $tabs.length - 1 ? currentIndex + 1 : 0;
                        e.preventDefault();
                        break;
                    case 13: // Enter
                    case 32: // Space
                        $(this).click();
                        e.preventDefault();
                        break;
                    default:
                        return;
                }
                
                if (newIndex !== undefined) {
                    $tabs.eq(newIndex).focus().click();
                }
            });
        },
        
        addAriaLabels: function() {
            // Add ARIA labels to tabs
            $('.gica-admin-tab').each(function() {
                const $tab = $(this);
                const tabText = $tab.text();
                $tab.attr({
                    'role': 'tab',
                    'aria-label': `Pestaña ${tabText}`,
                    'tabindex': $tab.hasClass('active') ? '0' : '-1'
                });
            });
            
            // Add ARIA labels to tab panels
            $('.gica-tab-panel').each(function() {
                const $panel = $(this);
                $panel.attr({
                    'role': 'tabpanel',
                    'aria-hidden': !$panel.hasClass('active')
                });
            });
            
            // Update ARIA attributes when tabs change
            $(document).on('gicaTabChanged', function(e, tabId) {
                $('.gica-admin-tab').attr('tabindex', '-1');
                $('.gica-admin-tab.active').attr('tabindex', '0');
                
                $('.gica-tab-panel').attr('aria-hidden', 'true');
                $(`#gica-tab-${tabId}`).attr('aria-hidden', 'false');
            });
        },
        
        addSkipLinks: function() {
            // Add skip link to main content
            const $skipLink = $('<a href="#gica-tab-content" class="screen-reader-shortcut">Saltar al contenido principal</a>');
            $('.gica-admin-wrap').prepend($skipLink);
            
            // Style skip link
            $skipLink.css({
                'position': 'absolute',
                'top': '-40px',
                'left': '6px',
                'z-index': '100000',
                'color': '#fff',
                'background': '#000',
                'padding': '8px 16px',
                'text-decoration': 'none',
                'border-radius': '3px'
            });
            
            $skipLink.on('focus', function() {
                $(this).css('top', '7px');
            }).on('blur', function() {
                $(this).css('top', '-40px');
            });
        }
    };
    
    // Performance Optimizations
    const PerformanceManager = {
        init: function() {
            this.lazyLoadContent();
            this.debounceEvents();
        },
        
        lazyLoadContent: function() {
            // Load tab content only when needed
            $(document).on('gicaTabChanged', function(e, tabId) {
                const $panel = $(`#gica-tab-${tabId}`);
                
                if (!$panel.hasClass('loaded')) {
                    // Simulate loading time for heavy content
                    if (tabId === 'users' && $panel.find('table tbody tr').length > 50) {
                        $panel.addClass('gica-loading');
                        setTimeout(function() {
                            $panel.removeClass('gica-loading').addClass('loaded');
                        }, 300);
                    } else {
                        $panel.addClass('loaded');
                    }
                }
            });
        },
        
        debounceEvents: function() {
            // Debounce search input if it exists
            let searchTimeout;
            $(document).on('input', '.gica-search-input', function() {
                const $input = $(this);
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    // Perform search
                    PerformanceManager.filterTable($input.val());
                }, 300);
            });
        },
        
        filterTable: function(searchTerm) {
            // Future implementation for table filtering
            console.log('Filtering table with term:', searchTerm);
        }
    };
    
    // Copy to Clipboard Functionality
    const ClipboardManager = {
        init: function() {
            this.addCopyButtons();
        },
        
        addCopyButtons: function() {
            // Add copy button to shortcode
            $('.gica-shortcode').each(function() {
                const $code = $(this);
                const $copyBtn = $('<button class="copy-shortcode-btn" title="Copiar al portapapeles">📋</button>');
                
                $copyBtn.css({
                    'margin-left': '10px',
                    'background': 'transparent',
                    'border': 'none',
                    'cursor': 'pointer',
                    'font-size': '14px',
                    'opacity': '0.7',
                    'transition': 'opacity 0.2s'
                });
                
                $copyBtn.on('mouseenter', function() {
                    $(this).css('opacity', '1');
                }).on('mouseleave', function() {
                    $(this).css('opacity', '0.7');
                });
                
                $copyBtn.on('click', function(e) {
                    e.preventDefault();
                    const text = $code.text();
                    
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(text).then(function() {
                            NotificationManager.show('Shortcode copiado al portapapeles', 'success');
                        });
                    } else {
                        // Fallback for older browsers
                        const textArea = document.createElement('textarea');
                        textArea.value = text;
                        document.body.appendChild(textArea);
                        textArea.select();
                        document.execCommand('copy');
                        document.body.removeChild(textArea);
                        NotificationManager.show('Shortcode copiado al portapapeles', 'success');
                    }
                });
                
                $code.after($copyBtn);
            });
        }
    };
    
    // User Management System
    const UserManager = {
        currentPage: 1,
        usersPerPage: 10,
        currentSearch: '',
        
        init: function() {
            this.bindEvents();
            this.loadUsers();
        },
        
        bindEvents: function() {
            // Search functionality
            $(document).on('input', '#gica-user-search', this.debounce(this.handleSearch.bind(this), 300));
            $(document).on('click', '#gica-search-btn', this.handleSearchClick.bind(this));
            $(document).on('click', '#gica-clear-search', this.clearSearch.bind(this));
            
            // Table actions
            $(document).on('click', '.gica-edit-user', this.editUser.bind(this));
            $(document).on('click', '.gica-delete-user', this.deleteUser.bind(this));
            $(document).on('click', '#gica-refresh-users', this.refreshUsers.bind(this));
            
            // Modal functionality
            $(document).on('click', '.gica-modal-close, .gica-modal-cancel, .gica-modal-backdrop', this.closeModal.bind(this));
            $(document).on('submit', '#gica-user-form', this.saveUser.bind(this));
            
            // Pagination
            $(document).on('click', '.gica-page-btn', this.changePage.bind(this));
            
            // Load users when switching to users tab
            $(document).on('gicaTabChanged', function(e, tabId) {
                if (tabId === 'users') {
                    UserManager.loadUsers();
                }
            });
        },
        
        loadUsers: function(page = 1, search = '') {
            const $loading = $('#gica-users-loading');
            const $tbody = $('#gica-users-tbody');
            
            $loading.show();
            
            const data = {
                action: 'gica_load_users',
                nonce: gica_admin_ajax.nonce,
                page: page,
                per_page: this.usersPerPage,
                search: search
            };
            
            $.post(gica_admin_ajax.ajax_url, data, function(response) {
                $loading.hide();
                
                if (response.success) {
                    UserManager.renderUsers(response.data.users);
                    UserManager.renderPagination(response.data.pagination);
                } else {
                    NotificationManager.show(response.data || gica_admin_ajax.error_loading, 'error');
                }
            }).fail(function() {
                $loading.hide();
                NotificationManager.show(gica_admin_ajax.error_loading, 'error');
            });
        },
        
        renderUsers: function(users) {
            const $tbody = $('#gica-users-tbody');
            $tbody.empty();
            
            if (users.length === 0) {
                $tbody.append(`
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px;">
                            <p>${gica_admin_ajax.no_users_found}</p>
                        </td>
                    </tr>
                `);
                return;
            }
            
            users.forEach(function(user) {
                const completionColor = user.completion_percentage >= 80 ? '#27ae60' : 
                                      user.completion_percentage >= 50 ? '#f39c12' : '#e74c3c';
                
                const row = `
                    <tr data-user-id="${user.ID}">
                        <td class="gica-col-id">${user.ID}</td>
                        <td class="gica-col-user">
                            <strong>${user.display_name || user.username}</strong>
                            <br><small>${user.username}</small>
                            ${user.first_name || user.last_name ? 
                                `<br><small>${user.first_name} ${user.last_name}</small>` : ''}
                        </td>
                        <td class="gica-col-email">${user.email}</td>
                        <td class="gica-col-dni">${user.dni || '<span style="color: #999;">No especificado</span>'}</td>
                        <td class="gica-col-location">
                            ${user.city || user.country ? 
                                `${user.city || ''}${user.city && user.country ? ', ' : ''}${user.country || ''}` : 
                                '<span style="color: #999;">No especificado</span>'}
                        </td>
                        <td class="gica-col-completion">
                            <div class="gica-completion-mini">
                                <div class="gica-completion-bar-mini">
                                    <div class="gica-completion-fill-mini" 
                                         style="width: ${user.completion_percentage}%; background: ${completionColor};"></div>
                                </div>
                                <span style="color: ${completionColor}; font-weight: 600;">${user.completion_percentage}%</span>
                            </div>
                        </td>
                        <td class="gica-col-date">${user.formatted_date}</td>
                        <td class="gica-col-actions">
                            <button class="button button-small gica-edit-user" data-user-id="${user.ID}" 
                                    title="Editar usuario">
                                <span class="dashicons dashicons-edit"></span>
                            </button>
                            <button class="button button-small button-link-delete gica-delete-user" 
                                    data-user-id="${user.ID}" title="Eliminar usuario">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </td>
                    </tr>
                `;
                
                $tbody.append(row);
            });
        },
        
        renderPagination: function(pagination) {
            const $pagination = $('#gica-pagination');
            $pagination.empty();
            
            if (pagination.total_pages <= 1) return;
            
            let paginationHTML = '<div class="gica-pagination-controls">';
            
            // Previous button
            if (pagination.current_page > 1) {
                paginationHTML += `<button class="button gica-page-btn" data-page="${pagination.current_page - 1}">« Anterior</button>`;
            }
            
            // Page numbers
            for (let i = 1; i <= pagination.total_pages; i++) {
                if (i === pagination.current_page) {
                    paginationHTML += `<button class="button button-primary gica-page-btn" data-page="${i}">${i}</button>`;
                } else {
                    paginationHTML += `<button class="button gica-page-btn" data-page="${i}">${i}</button>`;
                }
            }
            
            // Next button
            if (pagination.current_page < pagination.total_pages) {
                paginationHTML += `<button class="button gica-page-btn" data-page="${pagination.current_page + 1}">Siguiente »</button>`;
            }
            
            paginationHTML += '</div>';
            paginationHTML += `<div class="gica-pagination-info">Página ${pagination.current_page} de ${pagination.total_pages} (${pagination.total_users} usuarios)</div>`;
            
            $pagination.html(paginationHTML);
        },
        
        handleSearch: function() {
            this.currentSearch = $('#gica-user-search').val();
            this.currentPage = 1;
            this.loadUsers(this.currentPage, this.currentSearch);
        },
        
        handleSearchClick: function(e) {
            e.preventDefault();
            this.handleSearch();
        },
        
        clearSearch: function(e) {
            e.preventDefault();
            $('#gica-user-search').val('');
            this.currentSearch = '';
            this.currentPage = 1;
            this.loadUsers();
        },
        
        changePage: function(e) {
            e.preventDefault();
            const page = parseInt($(e.currentTarget).data('page'));
            this.currentPage = page;
            this.loadUsers(page, this.currentSearch);
        },
        
        editUser: function(e) {
            e.preventDefault();
            const userId = $(e.currentTarget).data('user-id');
            this.showUserModal(userId);
        },
        
        showUserModal: function(userId) {
            const data = {
                action: 'gica_get_user',
                nonce: gica_admin_ajax.nonce,
                user_id: userId
            };
            
            $.post(gica_admin_ajax.ajax_url, data, function(response) {
                if (response.success) {
                    UserManager.populateModal(response.data);
                    $('#gica-user-modal').show();
                } else {
                    NotificationManager.show(response.data || 'Error al cargar usuario', 'error');
                }
            });
        },
        
        populateModal: function(userData) {
            $('#user-id').val(userData.ID);
            $('#display-name').val(userData.display_name);
            $('#user-email').val(userData.email);
            $('#first-name').val(userData.first_name);
            $('#last-name').val(userData.last_name);
            $('#dni').val(userData.dni);
            $('#phone').val(userData.phone);
            $('#city').val(userData.city);
            $('#region').val(userData.region);
            $('#country').val(userData.country);
            $('#address').val(userData.address);
            $('#reference').val(userData.reference);
        },
        
        saveUser: function(e) {
            e.preventDefault();
            const $form = $(e.currentTarget);
            const formData = $form.serialize();
            
            const data = formData + '&action=gica_update_user&nonce=' + gica_admin_ajax.nonce;
            
            $.post(gica_admin_ajax.ajax_url, data, function(response) {
                if (response.success) {
                    NotificationManager.show(response.data.message, 'success');
                    UserManager.closeModal();
                    UserManager.loadUsers(UserManager.currentPage, UserManager.currentSearch);
                } else {
                    NotificationManager.show(response.data || 'Error al guardar usuario', 'error');
                }
            });
        },
        
        deleteUser: function(e) {
            e.preventDefault();
            
            if (!confirm(gica_admin_ajax.confirm_delete)) {
                return;
            }
            
            const userId = $(e.currentTarget).data('user-id');
            const data = {
                action: 'gica_delete_user',
                nonce: gica_admin_ajax.nonce,
                user_id: userId
            };
            
            $.post(gica_admin_ajax.ajax_url, data, function(response) {
                if (response.success) {
                    NotificationManager.show(response.data, 'success');
                    UserManager.loadUsers(UserManager.currentPage, UserManager.currentSearch);
                } else {
                    NotificationManager.show(response.data || 'Error al eliminar usuario', 'error');
                }
            });
        },
        
        refreshUsers: function(e) {
            e.preventDefault();
            this.loadUsers(this.currentPage, this.currentSearch);
        },
        
        closeModal: function(e) {
            if (e && $(e.target).is('.gica-modal-content, .gica-modal-content *') && 
                !$(e.target).is('.gica-modal-close, .gica-modal-cancel')) {
                return;
            }
            $('#gica-user-modal').hide();
        },
        
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };
    
    // Initialize all managers
    TabManager.init();
    FormManager.init();
    NotificationManager.init();
    StatsManager.init();
    AccessibilityManager.init();
    PerformanceManager.init();
    ClipboardManager.init();
    UserManager.init();
    
    // Global event handlers
    $(document).on('gicaTabChanged', function(e, tabId) {
        console.log('Tab changed to:', tabId);
    });
    
    // Add custom CSS for validation states
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .has-error input {
                border-color: #e74c3c !important;
                box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1) !important;
            }
            
            .has-success input {
                border-color: #27ae60 !important;
                box-shadow: 0 0 0 3px rgba(39, 174, 96, 0.1) !important;
            }
            
            .validation-message {
                color: #e74c3c;
                font-size: 12px;
                margin-top: 5px;
                font-style: italic;
            }
            
            .screen-reader-shortcut {
                position: absolute !important;
                clip: rect(1px, 1px, 1px, 1px);
                padding: 0 !important;
                border: 0 !important;
                height: 1px !important;
                width: 1px !important;
                overflow: hidden;
            }
            
            .screen-reader-shortcut:focus {
                clip: auto !important;
                height: auto !important;
                width: auto !important;
                display: block;
                font-size: 14px;
                font-weight: bold;
                padding: 15px 23px 14px;
                text-decoration: none;
                line-height: normal;
                color: #21759b;
                background: #f1f1f1;
                border-radius: 3px;
            }
        `)
        .appendTo('head');
});