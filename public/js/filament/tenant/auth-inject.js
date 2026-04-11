// Inject custom auth styles to match landing page design
(function() {
    'use strict';

    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectAuthStyles);
    } else {
        injectAuthStyles();
    }

    function injectAuthStyles() {
        // Check if we're on an auth page (simple layout)
        const layout = document.querySelector('.fi-simple-layout');
        if (!layout) return;

        // Create and inject style element
        const style = document.createElement('style');
        style.textContent = `
            .fi-simple-layout {
                background: linear-gradient(135deg, #1e1b4b 0%, #312e81 30%, #4338ca 60%, #6366f1 100%) !important;
                min-height: 100vh !important;
            }
            .fi-simple-main {
                background: rgba(255, 255, 255, 0.95) !important;
                backdrop-filter: blur(12px) !important;
                border-radius: 16px !important;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3) !important;
                padding: 2.5rem !important;
            }
            .fi-simple-heading {
                color: #1e1b4b !important;
                font-weight: 700 !important;
                font-size: 1.75rem !important;
                margin-bottom: 1.5rem !important;
            }
            .fi-btn-color-primary {
                background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%) !important;
                border-radius: 12px !important;
                border: none !important;
                font-weight: 600 !important;
            }
            .fi-btn-color-primary:hover {
                opacity: 0.9 !important;
                transform: translateY(-1px) !important;
            }
            .fi-input {
                border-radius: 10px !important;
                border: 2px solid #e2e8f0 !important;
                background: #f8fafc !important;
            }
            .fi-input:focus {
                border-color: #6366f1 !important;
                box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15) !important;
                background: white !important;
            }
            .fi-link {
                color: #6366f1 !important;
                font-weight: 500 !important;
            }
            .fi-link:hover {
                color: #4338ca !important;
            }
        `;
        document.head.appendChild(style);
        console.log('Auth styles injected');
    }
})();
