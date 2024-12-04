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
        const response = await fetch(`${page}.html`);
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
    script.src = `${page}.js`;
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

function initializeCalendar() {
    let currentDate = new Date();
    let currentMonth = currentDate.getMonth();
    let currentYear = currentDate.getFullYear();

    const monthDisplay = document.getElementById('monthDisplay');
    const calendar = document.getElementById('calendar');
    const prevBtn = document.getElementById('prevMonth');
    const nextBtn = document.getElementById('nextMonth');

    // Only add event listeners if elements exist
    if (prevBtn && nextBtn) {
        prevBtn.addEventListener('click', () => {
            currentMonth--;
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            renderCalendar();
        });

        nextBtn.addEventListener('click', () => {
            currentMonth++;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
            renderCalendar();
        });
    }

    function renderCalendar() {
        const firstDay = new Date(currentYear, currentMonth, 1);
        const lastDay = new Date(currentYear, currentMonth + 1, 0);
        const prevLastDay = new Date(currentYear, currentMonth, 0);
        const firstDayIndex = firstDay.getDay();
        const lastDayIndex = lastDay.getDay();
        const nextDays = 7 - lastDayIndex - 1;

        const months = [
            "January", "February", "March", "April", "May", "June",
            "July", "August", "September", "October", "November", "December"
        ];

        if (monthDisplay) {
            monthDisplay.textContent = `${months[currentMonth]} ${currentYear}`;
        }
        
        let days = "";

        // Previous month's days
        for (let x = firstDayIndex; x > 0; x--) {
            days += `
                <div class="calendar-day other-month">
                    <div class="day-number">${prevLastDay.getDate() - x + 1}</div>
                </div>
            `;
        }

        // Current month's days
        for (let i = 1; i <= lastDay.getDate(); i++) {
            const today = new Date();
            const isToday = i === today.getDate() && 
                            currentMonth === today.getMonth() && 
                            currentYear === today.getFullYear();
            
            days += `
                <div class="calendar-day ${isToday ? 'today' : ''}">
                    <div class="day-number">${i}</div>
                </div>
            `;
        }

        // Next month's days
        for (let j = 1; j <= nextDays; j++) {
            days += `
                <div class="calendar-day other-month">
                    <div class="day-number">${j}</div>
                </div>
            `;
        }

        if (calendar) {
            calendar.innerHTML = days;
        }
    }

    // Initial render
    renderCalendar();
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



