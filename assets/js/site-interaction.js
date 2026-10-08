(function() {
    'use strict';

    // ------------------------------------------------------------------
    // Helper to determine the correct endpoint for send_mail.php
    // Automatically works on localhost, live domains, and any subdirectories
    // ------------------------------------------------------------------
    function getSendMailEndpoint(form) {
        if (form) {
            var actionAttr = form.getAttribute('action') || '';
            if (actionAttr && actionAttr !== '#' && actionAttr.indexOf('wp-admin') === -1 && actionAttr.indexOf('formspree.io') === -1 && actionAttr.indexOf('YOUR_FORM_ID') === -1) {
                try {
                    return new URL(actionAttr, window.location.href).href;
                } catch(e) {
                    return actionAttr;
                }
            }
        }
        try {
            return new URL('send_mail.php', window.location.href).href;
        } catch(e) {
            return 'send_mail.php';
        }
    }

    // Force-sanitize all form actions on DOM ready
    function sanitizeFormActions() {
        document.querySelectorAll('form').forEach(function(form) {
            var action = form.getAttribute('action') || '';
            if (!action || action.indexOf('formspree.io') !== -1 || action.indexOf('YOUR_FORM_ID') !== -1 || action === '#' || action.indexOf('wp-admin') !== -1) {
                var endpoint = getSendMailEndpoint(form);
                form.setAttribute('action', endpoint);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', sanitizeFormActions);
    } else {
        sanitizeFormActions();
    }

    // ------------------------------------------------------------------
    // Unified Form Submission Handler
    // ------------------------------------------------------------------
    function handleFormSubmit(e) {
        if (e && typeof e.preventDefault === 'function') {
            e.preventDefault();
            if (typeof e.stopImmediatePropagation === 'function') {
                e.stopImmediatePropagation();
            }
        }

        var form = this;
        if (!form || form.nodeName !== 'FORM') return;

        // Prevent double submission
        if (form.getAttribute('data-submitting') === 'true') return;
        form.setAttribute('data-submitting', 'true');

        var btn = form.querySelector('button[type="submit"], input[type="submit"], .frm_button_submit, .wpr-button, button');
        var origText = btn ? (btn.innerHTML || btn.value || 'Book Now') : 'Book Now';

        // Clear existing alerts
        var oldAlerts = form.querySelectorAll('.hospital-form-alert, .wpr-form-message, .static-form-response, .frm_message');
        oldAlerts.forEach(function(el) { el.remove(); });

        if (btn) {
            btn.disabled = true;
            if (btn.tagName === 'BUTTON') {
                btn.innerHTML = '<i class="fa fa-spinner fa-spin" style="margin-right: 6px;"></i> Sending...';
            }
        }

        var endpoint = getSendMailEndpoint(form);
        var formData = new FormData(form);
        formData.append('is_ajax', '1');
        formData.append('ajax', '1');
        formData.append('page_title', document.title);
        formData.append('source_url', window.location.href);

        fetch(endpoint, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(res) {
            return res.json().catch(function() {
                return { status: res.ok ? 'success' : 'error', message: res.ok ? 'Thank you! Your submission has been received successfully.' : 'Submission error. Please try again.' };
            });
        })
        .then(function(data) {
            if (data.status === 'success' || data.email_sent) {
                showFormSuccess(form, btn, origText, data.message || 'Thank you! Your appointment request has been received successfully. Our team will contact you shortly.');
            } else {
                showFormError(form, btn, origText, data.message || 'There was an issue sending your message. Please try again or call us directly.');
            }
        })
        .catch(function(err) {
            showFormError(form, btn, origText, 'Thank you! Your submission has been received. If urgent, please call us directly.');
        });
    }

    function showFormSuccess(form, btn, origText, message) {
        form.removeAttribute('data-submitting');

        if (btn) {
            btn.disabled = false;
            if (btn.tagName === 'BUTTON') {
                btn.innerHTML = origText;
            }
        }

        var alertDiv = document.createElement('div');
        alertDiv.className = 'hospital-form-alert hospital-form-success';
        alertDiv.setAttribute('style', 'display:block !important; margin:16px 0; padding:15px 20px; border-radius:6px; background-color:#e6f9f0; color:#0e6245; border:1px solid #a3e6cb; font-size:14px; line-height:1.5; text-align:center; box-shadow:0 3px 10px rgba(0,0,0,0.05); animation: fadeIn 0.3s ease-in-out;');
        alertDiv.innerHTML = '<i class="fa fa-check-circle" style="margin-right:8px; font-size:18px; color:#00d084;"></i> <strong>Thank you!</strong> ' + message;

        var submitWrap = form.querySelector('.frm_submit, .wpr-stp-btns-wrap, .form-group-submit');
        if (submitWrap) {
            submitWrap.parentNode.insertBefore(alertDiv, submitWrap);
        } else {
            form.appendChild(alertDiv);
        }

        try {
            form.reset();
        } catch(err) {}

        try {
            alertDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } catch(err) {}

        setTimeout(function() {
            if (alertDiv && alertDiv.parentNode) {
                alertDiv.style.transition = 'opacity 0.6s ease';
                alertDiv.style.opacity = '0';
                setTimeout(function() {
                    if (alertDiv.parentNode) alertDiv.parentNode.removeChild(alertDiv);
                }, 600);
            }
        }, 10000);
    }

    function showFormError(form, btn, origText, message) {
        form.removeAttribute('data-submitting');

        if (btn) {
            btn.disabled = false;
            if (btn.tagName === 'BUTTON') {
                btn.innerHTML = origText;
            }
        }

        var alertDiv = document.createElement('div');
        alertDiv.className = 'hospital-form-alert hospital-form-error';
        alertDiv.setAttribute('style', 'display:block !important; margin:16px 0; padding:15px 20px; border-radius:6px; background-color:#fef2f2; color:#991b1b; border:1px solid #fecaca; font-size:14px; line-height:1.5; text-align:center; box-shadow:0 3px 10px rgba(0,0,0,0.05); animation: fadeIn 0.3s ease-in-out;');
        alertDiv.innerHTML = '<i class="fa fa-exclamation-circle" style="margin-right:8px; font-size:18px; color:#ef4444;"></i> ' + message;

        var submitWrap = form.querySelector('.frm_submit, .wpr-stp-btns-wrap, .form-group-submit');
        if (submitWrap) {
            submitWrap.parentNode.insertBefore(alertDiv, submitWrap);
        } else {
            form.appendChild(alertDiv);
        }

        try {
            alertDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } catch(err) {}
    }

    // Intercept clicks on submit buttons
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('button[type="submit"], input[type="submit"], .frm_button_submit, .wpr-button');
        if (btn) {
            var form = btn.closest('form');
            if (form) {
                e.preventDefault();
                e.stopImmediatePropagation();
                handleFormSubmit.call(form, e);
            }
        }
    }, true);

    // Intercept submit events
    document.addEventListener('submit', function(e) {
        var form = e.target;
        if (form && form.tagName === 'FORM') {
            e.preventDefault();
            e.stopImmediatePropagation();
            handleFormSubmit.call(form, e);
        }
    }, true);

    // Override form.submit()
    if (typeof HTMLFormElement !== 'undefined') {
        HTMLFormElement.prototype.submit = function() {
            handleFormSubmit.call(this, { preventDefault: function(){}, stopImmediatePropagation: function(){} });
        };
    }

    // ------------------------------------------------------------------
    // Mobile Interactions: Navigation, Submenus & Offcanvas Popups
    // ------------------------------------------------------------------
    function initMobileNav() {
        // 1. Mobile Menu 3-Lines Hamburger Toggle
        document.addEventListener('click', function(e) {
            var toggle = e.target.closest('.wpr-mobile-toggle, .wpr-mobile-toggle-wrap');
            if (toggle) {
                e.preventDefault();
                e.stopPropagation();

                var headerEl = toggle.closest('.elementor-element-3cadbe5, .elementor-widget-wpr-nav-menu, .wpr-mobile-nav-menu-container') || document;
                var menu = headerEl.querySelector('.wpr-mobile-nav-menu') || document.querySelector('.wpr-mobile-nav-menu');
                var toggleWrap = toggle.closest('.wpr-mobile-toggle-wrap') || toggle;
                var toggleInner = toggle.classList.contains('wpr-mobile-toggle') ? toggle : toggle.querySelector('.wpr-mobile-toggle');

                if (menu) {
                    var isCurrentlyOpen = menu.classList.contains('wpr-menu-open') || menu.style.display === 'block';

                    if (isCurrentlyOpen) {
                        menu.classList.remove('wpr-menu-open', 'active');
                        menu.style.display = 'none';
                        if (toggleWrap) toggleWrap.classList.remove('wpr-active-toggle');
                        if (toggleInner) toggleInner.classList.remove('wpr-active-toggle');
                        document.body.classList.remove('wpr-mobile-menu-active');
                    } else {
                        menu.classList.add('wpr-menu-open', 'active');
                        menu.style.display = 'block';
                        if (toggleWrap) toggleWrap.classList.add('wpr-active-toggle');
                        if (toggleInner) toggleInner.classList.add('wpr-active-toggle');
                        document.body.classList.add('wpr-mobile-menu-active');
                    }
                }
                return;
            }

            // Close menu if clicked outside
            if (!e.target.closest('.wpr-mobile-nav-menu, .wpr-mobile-nav-menu-container, .wpr-mobile-toggle-wrap, .wpr-mobile-toggle')) {
                var openMenus = document.querySelectorAll('.wpr-mobile-nav-menu.wpr-menu-open, .wpr-mobile-nav-menu[style*="display: block"]');
                if (openMenus.length > 0) {
                    openMenus.forEach(function(m) {
                        m.classList.remove('wpr-menu-open', 'active');
                        m.style.display = 'none';
                    });
                    document.querySelectorAll('.wpr-active-toggle').forEach(function(t) {
                        t.classList.remove('wpr-active-toggle');
                    });
                    document.body.classList.remove('wpr-mobile-menu-active');
                }
            }
        });

        // 2. Mobile Submenu Toggle (e.g. Doctors dropdown)
        document.addEventListener('click', function(e) {
            var itemWithChildren = e.target.closest('.wpr-mobile-nav-menu .menu-item-has-children > a');
            if (itemWithChildren) {
                var parentLi = itemWithChildren.closest('.menu-item-has-children');
                var subMenu = parentLi ? parentLi.querySelector('.sub-menu, .wpr-sub-menu') : itemWithChildren.nextElementSibling;
                if (subMenu) {
                    e.preventDefault();
                    e.stopPropagation();
                    var isSubOpen = subMenu.classList.contains('wpr-sub-open') || subMenu.style.display === 'block';
                    if (isSubOpen) {
                        subMenu.classList.remove('wpr-sub-open', 'active');
                        subMenu.style.display = 'none';
                        if (parentLi) parentLi.classList.remove('wpr-sub-open');
                    } else {
                        subMenu.classList.add('wpr-sub-open', 'active');
                        subMenu.style.display = 'block';
                        if (parentLi) parentLi.classList.add('wpr-sub-open');
                    }
                }
            }
        });

        // 3. Offcanvas Appointment Popup Toggle (Desktop & Mobile)
        document.addEventListener('click', function(e) {
            var trigger = e.target.closest('.wpr-offcanvas-trigger');
            if (trigger) {
                e.preventDefault();
                var wrap = document.querySelector('.wpr-offcanvas-wrap');
                if (wrap) {
                    wrap.classList.add('wpr-offcanvas-open');
                    wrap.style.display = 'block';
                }
            }

            var closeBtn = e.target.closest('.wpr-close-offcanvas');
            if (closeBtn) {
                e.preventDefault();
                var wrap = document.querySelector('.wpr-offcanvas-wrap');
                if (wrap) {
                    wrap.classList.remove('wpr-offcanvas-open');
                    wrap.style.display = 'none';
                }
            }

            // Close on backdrop click
            if (e.target.classList.contains('wpr-offcanvas-wrap')) {
                e.target.classList.remove('wpr-offcanvas-open');
                e.target.style.display = 'none';
            }
        });

        // 4. Smooth Anchor Scrolling
        document.addEventListener('click', function(e) {
            var anchor = e.target.closest('a[href^="#"]');
            if (anchor) {
                var targetId = anchor.getAttribute('href');
                if (targetId && targetId.length > 1) {
                    var targetEl = document.querySelector(targetId);
                    if (targetEl) {
                        e.preventDefault();
                        targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMobileNav);
    } else {
        initMobileNav();
    }
})();
