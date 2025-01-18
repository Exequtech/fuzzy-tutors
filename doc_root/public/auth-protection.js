// auth-protection.js
import { SessionManager } from './dataHandler.js';

// Add this to pages that require authentication
document.addEventListener('DOMContentLoaded', function() {
    if (!SessionManager.isLoggedIn()) {
        window.location.href = '/authentication.html';
    }
});