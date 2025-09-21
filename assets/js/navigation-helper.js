/**
 * Navigation Helper
 * Ensures navigation links work correctly and prevents interference
 */

(function() {
    'use strict';

    console.log('Navigation helper loaded');

    document.addEventListener('DOMContentLoaded', function() {
        // Ensure navigation links work correctly
        const navLinks = document.querySelectorAll('.nav-link');

        navLinks.forEach(function(link) {
            // Only add logging, don't interfere with navigation
            link.addEventListener('click', function(e) {
                console.log('Navigation link clicked:', this.href);

                // Check if this is a problematic navigation
                if (this.href.includes('calendar.php') ||
                    this.href.includes('tasks.php') ||
                    this.href.includes('chat.php')) {

                    // If user is not authenticated, the PHP files will redirect anyway
                    // Just ensure the click isn't being prevented by other scripts
                    if (e.defaultPrevented) {
                        console.warn('Navigation was prevented by another script');
                    }
                }
            });
        });

        // Check for common navigation issues
        const currentUrl = window.location.href;
        console.log('Current page:', currentUrl);

        // Debug function for troubleshooting
        window.debugNavigation = function() {
            console.log('=== Navigation Debug ===');
            console.log('Current URL:', window.location.href);
            console.log('Base URL from PHP:', window.BASE_URL || 'Not set');

            const links = document.querySelectorAll('.nav-link');
            console.log('Navigation links found:', links.length);

            links.forEach(function(link, index) {
                console.log(`Link ${index + 1}:`, {
                    text: link.textContent.trim(),
                    href: link.href,
                    isActive: link.classList.contains('active')
                });
            });

            // Test if we can access the target files
            fetch('calendar.php', { method: 'HEAD' })
                .then(response => {
                    console.log('Calendar.php status:', response.status);
                })
                .catch(error => {
                    console.log('Calendar.php error:', error.message);
                });
        };
    });

    // Prevent other scripts from interfering with navigation
    document.addEventListener('click', function(e) {
        if (e.target.matches('.nav-link') || e.target.closest('.nav-link')) {
            // If another script prevents default, log it
            setTimeout(function() {
                if (e.defaultPrevented) {
                    console.warn('Navigation link was prevented from working by another script');
                }
            }, 0);
        }
    }, true); // Use capture phase to run before other handlers

})();