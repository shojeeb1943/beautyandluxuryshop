/**
 * Mega Menu Navigation Script
 * Professional mega menu with smooth animations
 */

(function() {
    'use strict';

    // Position mega menu panels correctly below the navbar
    function positionMegaMenus() {
        const navbar = document.querySelector('.navbar-stuck-menu');
        if (!navbar) return;

        const megaMenuPanels = document.querySelectorAll('.mega-menu-panel');
        const navbarRect = navbar.getBoundingClientRect();
        const topPosition = navbarRect.bottom;

        megaMenuPanels.forEach(function(panel) {
            panel.style.top = topPosition + 'px';
        });
    }

    // Debounce helper
    function debounce(func, wait) {
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

    // Initialize positioning
    function initPositioning() {
        positionMegaMenus();

        // Update on scroll (debounced for performance)
        window.addEventListener('scroll', debounce(positionMegaMenus, 10), { passive: true });

        // Update on resize
        window.addEventListener('resize', debounce(positionMegaMenus, 100));
    }

    // Add smooth hover intent to prevent flickering
    function initHoverIntent() {
        const megaMenuItems = document.querySelectorAll('.nav-item.has-mega-menu');

        megaMenuItems.forEach(function(item) {
            let hoverTimeout;
            const panel = item.querySelector('.mega-menu-panel');

            if (!panel) return;

            // Mouse enter - small delay before showing
            item.addEventListener('mouseenter', function() {
                clearTimeout(hoverTimeout);
                // Position before showing
                positionMegaMenus();
            });

            // Mouse leave - small delay before hiding (allows moving to panel)
            item.addEventListener('mouseleave', function() {
                hoverTimeout = setTimeout(function() {
                    // CSS handles the hiding via :hover
                }, 100);
            });
        });
    }

    // Keyboard Navigation & ARIA
    function initAccessibility() {
        const megaMenuItems = document.querySelectorAll('.nav-item.has-mega-menu');

        megaMenuItems.forEach(function(item) {
            const panel = item.querySelector('.mega-menu-panel');
            const link = item.querySelector('.nav-link');

            if (!link || !panel) return;
            if (link.dataset.accessibilityInit) return;
            link.dataset.accessibilityInit = 'true';

            // Escape key closes menu
            panel.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    link.focus();
                }
            });

            // ARIA attributes
            link.setAttribute('aria-haspopup', 'true');
            link.setAttribute('aria-expanded', 'false');

            const panelId = 'mega-menu-' + Math.random().toString(36).substr(2, 9);
            panel.setAttribute('id', panelId);
            link.setAttribute('aria-controls', panelId);

            // Update ARIA on hover
            item.addEventListener('mouseenter', function() {
                link.setAttribute('aria-expanded', 'true');
            });

            item.addEventListener('mouseleave', function() {
                link.setAttribute('aria-expanded', 'false');
            });
        });
    }

    // Tablet accordion behavior (768px - 991px)
    function initTabletMode() {
        if (window.innerWidth >= 768 && window.innerWidth <= 991) {
            const megaMenuItems = document.querySelectorAll('.nav-item.has-mega-menu');

            megaMenuItems.forEach(function(item) {
                const link = item.querySelector('.nav-link');
                if (!link || link.dataset.tabletInit) return;
                link.dataset.tabletInit = 'true';

                link.addEventListener('click', function(e) {
                    if (window.innerWidth >= 768 && window.innerWidth <= 991) {
                        e.preventDefault();

                        // Close others
                        megaMenuItems.forEach(function(other) {
                            if (other !== item) other.classList.remove('active');
                        });

                        // Toggle current
                        item.classList.toggle('active');
                    }
                });
            });
        }
    }

    // Initialize everything
    function init() {
        initPositioning();
        initHoverIntent();
        initAccessibility();
        initTabletMode();
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Also run after full page load
    window.addEventListener('load', function() {
        setTimeout(positionMegaMenus, 100);
    });

    // Re-init on resize
    window.addEventListener('resize', debounce(function() {
        initTabletMode();
        positionMegaMenus();
    }, 250));

})();
