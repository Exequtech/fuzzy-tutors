const toggleBtn = document.querySelector('.toggle-btn');
const sidebar = document.querySelector('.sidebar');
const mainContent = document.getElementById('mainContent');
const navItems = document.querySelectorAll('.nav-item');

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

        await loadPageScript(page);

        // Update URL without page reload
        history.pushState({page: page}, '', `?page=${page}`);
        // Load and execute the page's specific JavaScript
    } catch (error) {
        console.error('Error loading content:', error);
        mainContent.innerHTML = '<div class="error">Error loading content</div>';
    }
}

// Function to load and execute page-specific JavaScript
async function loadPageScript(page) {
    // Remove any previously loaded page script
    const oldScript = document.querySelector(`script[data-page="${page}"]`);
    if (oldScript) {
        oldScript.remove();
    }

    // Create and append new script
    const script = document.createElement('script');
    script.src = `${page}/${page}.js`;
    script.type = "module";
    script.dataset.page = page; // Add data attribute to identify the script
    
    // Create a promise to handle script loading
    const loadPromise = new Promise((resolve, reject) => {
        script.onload = () => {
            // Initialize the page if it has an init function
            if (typeof window[`init${page.charAt(0).toUpperCase() + page.slice(1)}`] === 'function') {
                window[`init${page.charAt(0).toUpperCase() + page.slice(1)}`]();
            }
            resolve();
        };
        script.onerror = () => reject(new Error(`Failed to load ${page}.js`));
    });

    document.body.appendChild(script);
    
    try {
        await loadPromise;
    } catch (error) {
        console.error('Error loading page script:', error);
    }
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
    const initialPage = urlParams.get('page') || 'dashboard';
    loadContent(initialPage);
});



