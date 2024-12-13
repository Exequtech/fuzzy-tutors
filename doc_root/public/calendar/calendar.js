import { getLessons, addNewLessonRecord, getStudentPage, getSubjectsPage } from '../DataHandler.js';

let currentDate = new Date();
let currentMonth = currentDate.getMonth();
let currentYear = currentDate.getFullYear();
let lessons = [];

// DOM Elements
const monthDisplay = document.getElementById('monthDisplay');
const calendar = document.getElementById('calendar');
const prevBtn = document.getElementById('prevMonth');
const nextBtn = document.getElementById('nextMonth');
const addLessonBtn = document.getElementById('addLessonBtn');
const lessonModal = document.getElementById('lessonModal');
const lessonForm = document.getElementById('lessonForm');
const alertMessage = document.getElementById('alertMessage');
const availableStudentsList = document.getElementById('availableStudents');
const lessonStudentsList = document.getElementById('lessonStudents');

// Initialize
async function init() {
    try {
        await fetchAndRenderLessons();
        setupEventListeners();
        populateSubjectSelect();
        renderStudentLists();
    } catch (error) {
        showAlert('Failed to initialize calendar: ' + error.message, false);
    }
}

async function fetchAndRenderLessons() {
    const firstDay = new Date(currentYear, currentMonth, 1);
    const lastDay = new Date(currentYear, currentMonth + 1, 0);
    
    try {
        console.log(firstDay);
        console.log(firstDay.toISOString());
        lessons = await getLessons(
            firstDay.toISOString(),
            lastDay.toISOString()
        );
        renderCalendar();
    } catch (error) {
        showAlert('Failed to fetch lessons: ' + error.message, false);
    }
}

function setupEventListeners() {
    prevBtn.addEventListener('click', () => {
        currentMonth--;
        if (currentMonth < 0) {
            currentMonth = 11;
            currentYear--;
        }
        fetchAndRenderLessons();
    });

    nextBtn.addEventListener('click', () => {
        currentMonth++;
        if (currentMonth > 11) {
            currentMonth = 0;
            currentYear++;
        }
        fetchAndRenderLessons();
    });

    addLessonBtn.addEventListener('click', () => openLessonModal());
    
    lessonForm.addEventListener('submit', handleLessonSubmit);
    
    document.getElementById('addStudentBtn').addEventListener('click', () => {
        transferStudents(availableStudentsList, lessonStudentsList);
    });

    document.getElementById('removeStudentBtn').addEventListener('click', () => {
        transferStudents(lessonStudentsList, availableStudentsList);
    });

    // Close modal handlers
    document.querySelectorAll('.close-button, .cancel-button').forEach(button => {
        button.addEventListener('click', closeLessonModal);
    });
}

async function populateSubjectSelect() {
    try {
        const subjects = await getSubjectsPage();
        const select = document.getElementById('subjectSelect');
        
        select.innerHTML = subjects.map(subject => 
            `<option value="${subject.id}">${subject.name}</option>`
        ).join('');
    } catch (error) {
        showAlert('Failed to load subjects: ' + error.message, false);
    }
}

async function renderStudentLists() {
    try {
        const students = await getStudentPage();
        
        availableStudentsList.innerHTML = students.map(student => `
            <div class="student-item" data-id="${student.id}">
                ${student.username}
            </div>
        `).join('');

        // Add click handlers for selection
        document.querySelectorAll('.student-item').forEach(item => {
            item.addEventListener('click', () => {
                item.classList.toggle('selected');
            });
        });
    } catch (error) {
        showAlert('Failed to load students: ' + error.message, false);
    }
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

    monthDisplay.textContent = `${months[currentMonth]} ${currentYear}`;
    
    let days = "";

    // Previous month's days
    for (let x = firstDayIndex; x > 0; x--) {
        const day = prevLastDay.getDate() - x + 1;
        days += createDayElement(day, 'other-month', []);
    }

    // Current month's days
    for (let i = 1; i <= lastDay.getDate(); i++) {
        const today = new Date();
        const isToday = i === today.getDate() && 
                        currentMonth === today.getMonth() && 
                        currentYear === today.getFullYear();
        
        const dayLessons = lessons.filter(lesson => {
            const lessonDate = new Date(lesson.startDate);
            return lessonDate.getDate() === i;
        });
        
        days += createDayElement(i, isToday ? 'today' : '', dayLessons);
    }

    // Next month's days
    for (let j = 1; j <= nextDays; j++) {
        days += createDayElement(j, 'other-month', []);
    }

    calendar.innerHTML = days;
}

function createDayElement(dayNumber, extraClass, dayLessons) {
    return `
        <div class="calendar-day ${extraClass}">
            <div class="day-number">${dayNumber}</div>
            ${dayLessons.map(lesson => `
                <div class="lesson-item">
                    <div class="lesson-time">
                        ${formatTime(new Date(lesson.startDate))} - 
                        ${formatTime(new Date(lesson.endDate))}
                    </div>
                    <div class="lesson-subject">${lesson.subjectId}</div>
                </div>
            `).join('')}
        </div>
    `;
}

function formatTime(date) {
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function transferStudents(fromList, toList) {
    const selectedItems = fromList.querySelectorAll('.student-item.selected');
    selectedItems.forEach(item => {
        item.classList.remove('selected');
        toList.appendChild(item);
    });
}

function openLessonModal() {
    lessonModal.classList.add('show');
    document.getElementById('lessonDate').min = new Date().toISOString().split('T')[0];
}

function closeLessonModal() {
    lessonModal.classList.remove('show');
    lessonForm.reset();
    renderStudentLists();
}

async function handleLessonSubmit(e) {
    e.preventDefault();
    
    try {
        const lessonDate = document.getElementById('lessonDate').value;
        const startTime = document.getElementById('startTime').value;
        const endTime = document.getElementById('endTime').value;
        
        // Create date objects for manipulation
        const startDate = new Date(`${lessonDate}T${startTime}`);
        const endDate = new Date(`${lessonDate}T${endTime}`);
        
        // Format dates to match API requirement: YYYY-MM-DD HH:mm:ss
        const formatDateForApi = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            const seconds = String(date.getSeconds()).padStart(2, '0');
            
            return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
        };

        const formattedStartDate = formatDateForApi(startDate);
        const formattedEndDate = formatDateForApi(endDate);
        
        const subjectId = parseInt(document.getElementById('subjectSelect').value);
        const studentIds = Array.from(lessonStudentsList.children).map(item => 
            parseInt(item.dataset.id)
        );
        
        // Get trackables as array of strings from comma-separated input
        const trackables = document.getElementById('trackables').value
            .split(',')
            .map(item => item.trim())
            .filter(item => item.length > 0);

        const response = await addNewLessonRecord(
            formattedStartDate,
            formattedEndDate,
            subjectId,
            trackables,
            studentIds
        );

        if (response.isSuccessful) {
            showAlert('Lesson scheduled successfully!', true);
            await fetchAndRenderLessons();
            closeLessonModal();
        } else {
            showAlert(response.message, false);
        }
    } catch (error) {
        showAlert('Failed to schedule lesson: ' + error.message, false);
    }
}

function showAlert(message, isSuccess) {
    alertMessage.textContent = message;
    alertMessage.className = `alert alert-${isSuccess ? 'success' : 'error'} show`;
    
    setTimeout(() => {
        alertMessage.classList.remove('show');
    }, 3000);
}

// Initialize the calendar
init();