
import {initCalendar} from '/calendar/calendar.js'
import {initClasses} from './classes/classes.js'
import { initStudents } from './students/students.js';
import {initSubjects} from './subjects/subjects.js';
import { initLocations } from './locations/locations.js';
import { initTrackables } from './trackables/trackables.js';

import { services, SessionManager } from './dataHandler.js';

const toggleBtn = document.querySelector('.toggle-btn');
const sidebar = document.querySelector('.sidebar');
const mainContent = document.getElementById('mainContent');
const navItems = document.querySelectorAll('.nav-item');

let settingsModal = document.getElementById('settingsModal');
let settingsForm = document.getElementById('settingsForm');
let logoutBtn = document.getElementById('logoutBtn');
let closeButton = settingsModal.querySelector('.close-button');

let oldScript;

// Toggle sidebar
toggleBtn.addEventListener('click', () => {
    sidebar.classList.toggle('expanded');
    const icon = toggleBtn.querySelector('i');
    icon.classList.toggle('fa-chevron-right');
    icon.classList.toggle('fa-chevron-left');
});

// Navigation handling
async function loadContent(page) {
    try {
        if (page == 'settings') {
            openSettingsModal();
            return;
        }

        const response = await fetch(`${page}/${page}.html`);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const content = await response.text();
        mainContent.innerHTML = content;
        
        // Update active state
        navItems.forEach(item => {
            item.classList.remove('active-nav-item');
            if (item.dataset.page === page) {
                item.classList.add('active-nav-item');
            }
        });

        // Update URL without page reload
        history.pushState({page: page}, '', `?page=${page}`);
        // Load and execute the page's specific JavaScript
        await loadPageScript(page);
    } catch (error) {
        console.error('Error loading content:', error);
        mainContent.innerHTML = '<div class="error">Error loading content</div>';
    }
}

// Function to load and execute page-specific JavaScript
async function loadPageScript(page) {
    // Remove any previously loaded page script
    if(oldScript) {
        document.body.removeChild(oldScript);
        oldScript = null;
    }

    // Create and append new script
    const script = document.createElement('script');
    script.src = `${page}/${page}.js`;
    script.type = "module";
    script.dataset.page = page;
    
    // Create a promise to handle script loading
    const loadPromise = new Promise((resolve, reject) => {
        script.onload = () => resolve();
        script.onerror = () => reject(new Error(`Failed to load ${page}.js`));
    });

    document.body.appendChild(script);

    try {
        await loadPromise;

        switch (page) {
            case 'calendar':
                initCalendar();
                break;
            case 'subjects':
                initSubjects();
                break;
            case 'locations':
                initLocations();
                break;
            case 'students':
                initStudents();
                break;
            case 'trackables':
                initTrackables()
                break;
            case 'classes':
                initClasses();
                break;
            default:
                console.error("Script initialization function not set up in app shell")
                break;
        }

    } catch (error) {
        console.error('Error loading page script:', error);
    }

    oldScript = document.querySelector(`script[data-page="${page}"]`);
}

function openSettingsModal() {
    const settingsModal = document.getElementById('settingsModal');
    settingsModal.classList.add('show');
    populateCurrentUserData()
}

function setupSettingsModal() {
    settingsModal = document.getElementById('settingsModal');
    settingsForm = document.getElementById('settingsForm');
    logoutBtn = document.getElementById('logoutBtn');
    closeButton = settingsModal.querySelector('.close-button');

    // Close modal handlers
    closeButton.addEventListener('click', () => {
        settingsModal.classList.remove('show');
    });

    settingsModal.addEventListener('click', (e) => {
        if (e.target === settingsModal) {
            settingsModal.classList.remove('show');
        }
    });

    // Form submission
    settingsForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        
        if (newPassword !== confirmPassword) {
            alert('New passwords do not match');
            return;
        }

        let username = document.getElementById('username').value;
        let email = document.getElementById('email').value;

        try {
            const response = await services.auth.updateProfile(username, email);
            
            if (response.isSuccessful) {
                alert('Profile updated successfully!', true);
                settingsModal.classList.remove('show');
                // Reset password fields
                document.getElementById('currentPassword').value = '';
                document.getElementById('newPassword').value = '';
                document.getElementById('confirmPassword').value = '';
            } else {
                alert(response.message || 'Failed to update profile', false);
            }

            // Only update password data if a new password was entered
            if (newPassword) {
                let currentPassword = document.getElementById('currentPassword').value;
                const response = await services.auth.updatePassword(currentPassword, newPassword);

                if (response.isSuccessful) {
                    alert(response.message);
                } else {
                    alert(response.message || 'Failed to update password');
                }
            }

        } catch (error) {
            alert('An error occurred while updating profile', false);
        }
    });

    // Logout handler
    logoutBtn.addEventListener('click', async () => {
        try {
            await SessionManager.logOut();
        } catch (error) {
            alert('Failed to logout', false);
        }
    });
}

function populateCurrentUserData() {
    document.getElementById('username').value = "jeremia";
    document.getElementById('email').value = "jermia@gmail.com";

    document.getElementById('currentPassword').value = '';
    document.getElementById('newPassword').value = '';
    document.getElementById('confirmPassword').value = '';
}


// Add click handlers to navigation items
navItems.forEach(item => {
    item.addEventListener('click', () => {
        const page = item.dataset.page;
        loadContent(page);
    });
});

// Handle browser back/forward buttons
window.addEventListener('popstate', (event) => {
    if (event.state && event.state.page) {
        loadContent(event.state.page);
    }
});

// Load initial content
document.addEventListener('DOMContentLoaded', () => {
    // Get page from URL or default to dashboard
    const urlParams = new URLSearchParams(window.location.search);
    const initialPage = urlParams.get('page') || 'calendar';
    loadContent(initialPage);
    setupSettingsModal();
});



