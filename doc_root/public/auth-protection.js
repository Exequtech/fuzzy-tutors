// auth-protection.js
import { SessionManager } from './DataHandler.js';

// Add this to pages that require authentication
document.addEventListener('DOMContentLoaded', function() {
    if (!SessionManager.isLoggedIn()) {
        window.location.href = '/authentication.html';
    }
});