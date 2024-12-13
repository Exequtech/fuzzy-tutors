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

renderCalendar();