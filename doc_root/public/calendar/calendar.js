import { getLessons, addNewLessonRecord, getStudentPage, getSubjectsPage, getClassPage, getTrackables, getLocationPage } from '../dataHandler.js';

let currentDate = new Date();
let currentMonth = currentDate.getMonth();
let currentYear = currentDate.getFullYear();
let lessons = [];
let subjects = [];
let classes = [];
let locations = [];
let trackables = [];
let selectedStudents = new Set();
let searchTimeout = null;

let currentLesson = null;
const lessonDetailsModal = document.getElementById('lessonDetailsModal');

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
        await loadFormData();
        setupEventListeners();
        setupFormEventListeners();
    } catch (error) {
        showAlert('Failed to initialize calendar: ' + error.message, false);
    }
}

async function loadFormData() {
    try {
        // Load all necessary data
        subjects = await getSubjectsPage();
        classes = await getClassPage();
        locations = await getLocationPage();
        trackables = await getTrackables();

        // Populate form selects
        populateSubjectSelect();
        populateClassSelect();
        populateLocationSelect();
        populateTrackables();
        await renderStudentLists();
    } catch (error) {
        showAlert('Failed to load form data: ' + error.message, false);
    }
}

async function fetchAndRenderLessons() {
    const firstDay = new Date(currentYear, currentMonth, 1);
    const lastDay = new Date(currentYear, currentMonth + 1, 0);
    
    try {
        lessons = await getLessons(
            formatDateForApi(firstDay),
            formatDateForApi(lastDay)
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

function setupFormEventListeners() {
    // Student selection type toggle
    document.querySelectorAll('input[name="studentType"]').forEach(radio => {
        radio.addEventListener('change', (e) => {
            const classSelection = document.getElementById('classSelection');
            const studentSelection = document.getElementById('studentSelection');
            
            if (e.target.value === 'class') {
                classSelection.classList.remove('hidden');
                studentSelection.classList.add('hidden');
            } else {
                classSelection.classList.add('hidden');
                studentSelection.classList.remove('hidden');
                setupStudentManagement();
            }
        });
    });

    // Subject change handler for topics
    document.getElementById('subjectSelect').addEventListener('change', async (e) => {
        const subjectId = parseInt(e.target.value);
        const subject = subjects.find(s => s.id === subjectId);
        if (subject) {
            populateTopicSelect(subject.topics || []);
        }
    });

    // Close modal handlers
    document.querySelectorAll('.close-button, .cancel-button').forEach(button => {
        button.addEventListener('click', closeLessonModal);
    });

    lessonForm.addEventListener('submit', handleLessonSubmit);
}

function populateSubjectSelect() {
    const select = document.getElementById('subjectSelect');
    select.innerHTML = subjects.map(subject => 
        `<option value="${subject.id}">${subject.name}</option>`
    ).join('');
    
    // Initially populate topics for first subject
    if (subjects.length > 0) {
        populateTopicSelect(subjects[0].topics || []);
    }
}

function populateTopicSelect(topics) {
    const topicList = document.getElementById('availableTopics');
    topicList.innerHTML = topics.map(topic => `
        <div class="topic-item" data-id="${topic.id}">
            <span class="topic-name">${topic.name}</span>
        </div>
    `).join('');

    // Add click handlers for selection
    document.querySelectorAll('.topic-item').forEach(item => {
        item.addEventListener('click', () => {
            item.classList.toggle('selected');
        });
    });
}

function populateClassSelect() {
    const select = document.getElementById('classSelect');
    select.innerHTML = classes.map(cls => 
        `<option value="${cls.id}">${cls.name}</option>`
    ).join('');
}

function populateLocationSelect() {
    const select = document.getElementById('locationSelect');
    select.innerHTML = locations.map(location => 
        `<option value="${location.id}">${location.name}</option>`
    ).join('');
}

function populateTrackables() {
    const trackableList = document.getElementById('availableTrackables');
    trackableList.innerHTML = trackables.map(trackable => `
        <div class="trackable-item" data-name="${trackable.name}">
            <span class="trackable-name">${trackable.name}</span>
        </div>
    `).join('');

    // Add click handlers for selection
    document.querySelectorAll('.trackable-item').forEach(item => {
        item.addEventListener('click', () => {
            item.classList.toggle('selected');
        });
    });
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

function setupStudentManagement() {
    const searchInput = document.getElementById('studentSearchInput');
    const searchResults = document.getElementById('searchResults');

    searchInput.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        const searchTerm = e.target.value.trim();
        
        if (searchTerm.length === 0) {
            searchResults.classList.add('hidden');
            return;
        }

        searchTimeout = setTimeout(async () => {
            try {
                const students = await getStudentPage(1, 5, "asc", "username", {
                    username: searchTerm
                });
                renderSearchResults(students, searchResults);
            } catch (error) {
                showAlert('Search failed: ' + error.message, false);
            }
        }, 300);
    });

    // Hide search results when clicking outside
    document.addEventListener('click', (e) => {
        if (!searchResults.contains(e.target) && e.target !== searchInput) {
            searchResults.classList.add('hidden');
        }
    });
}

function renderSearchResults(students, searchResults) {
    const existingIds = new Set(Array.from(selectedStudents).map(student => student.id));
    const results = students.filter(student => !existingIds.has(student.id));
    
    if (results.length === 0) {
        searchResults.innerHTML = '<div class="search-result-item">No students found</div>';
    } else {
        searchResults.innerHTML = results.map(student => `
            <div class="search-result-item">
                <div class="student-info">
                    <div class="student-name">${student.username}</div>
                    <div class="student-email">${student.email}</div>
                </div>
                <button onclick="addLessonMember(${student.id}, '${student.username}', '${student.email}')" 
                        class="action-button add" title="Add to lesson" type="button">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        `).join('');
    }
    
    searchResults.classList.remove('hidden');
}

function renderLessonMembers() {
    const membersList = document.getElementById('lessonMembersList');
    const members = Array.from(selectedStudents.values());
    
    membersList.innerHTML = members.map(member => `
        <div class="member-item">
            <div class="student-info">
                <div class="student-name">${member.username}</div>
            </div>
            <button onclick="removeLessonMember(${member.id})" 
                    class="action-button" title="Remove from lesson">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `).join('');
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
                <div class="lesson-item" data-lesson-id="${lesson.id}" onclick="showLessonDetails(${JSON.stringify(lesson).replace(/"/g, '&quot;')})">
                    <div class="lesson-time">
                        ${formatTimeFromDate(new Date(lesson.startDate))} - 
                        ${formatTimeFromDate(new Date(lesson.endDate))}
                    </div>
                    <div class="lesson-subject-name">${lesson.subjectName}</div>
                    <div class="lesson-location">${lesson.locationName || 'No location'}</div>
                </div>
            `).join('')}
        </div>
    `;
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
    setupFormEventListeners();
}

function closeLessonModal() {
    lessonModal.classList.remove('show');
    lessonForm.reset();
    selectedStudents = new Set();
    const searchResults = document.getElementById('searchResults');
    if (searchResults) {
        searchResults.classList.add('hidden');
    }
}

async function handleLessonSubmit(e) {
    e.preventDefault();
    
    try {
        // Get date and time values
        const lessonDate = document.getElementById('lessonDate').value;
        const startTime = document.getElementById('startTime').value;
        const endTime = document.getElementById('endTime').value;
        
        // Format dates for API
        const startDate = formatDateForApi(new Date(`${lessonDate}T${startTime}`));
        const endDate = formatDateForApi(new Date(`${lessonDate}T${endTime}`));
        
        // Get subject and topic selections
        const subjectId = parseInt(document.getElementById('subjectSelect').value);
        const topics = Array.from(document.querySelectorAll('#availableTopics .topic-item.selected'))
            .map(item => parseInt(item.dataset.id));
        
        // Get selected trackables
        const selectedTrackables = Array.from(document.querySelectorAll('#availableTrackables .trackable-item.selected'))
            .map(item => item.dataset.name);

        // Get location
        const locationId = parseInt(document.getElementById('locationSelect').value);
        
        // Get students based on selection type
        const studentType = document.querySelector('input[name="studentType"]:checked').value;
        let classId = null;
        let students = null;
        
        if (studentType === 'class') {
            classId = parseInt(document.getElementById('classSelect').value);
        } else {
            students = Array.from(selectedStudents).map(student => student.id);
        }

        // Validate that either class or students are selected
        if (!classId && (!students || students.length === 0)) {
            showAlert('Please select either a class or at least one student', false);
            return;
        }

        // Validate end time is after start time
        const startDateTime = new Date(`${lessonDate}T${startTime}`);
        const endDateTime = new Date(`${lessonDate}T${endTime}`);
        if (endDateTime <= startDateTime) {
            showAlert('End time must be after start time', false);
            return;
        }

        // Make API call to create lesson
        const response = await addNewLessonRecord(
            subjectId,
            classId,
            students,
            startDate,
            endDate,
            selectedTrackables,
            topics,
            locationId
        );

        if (response.isSuccessful) {
            showAlert('Lesson scheduled successfully!', true);
            await fetchAndRenderLessons();
            closeLessonModal();
        } else {
            showAlert(response.message || 'Failed to schedule lesson', false);
        }
    } catch (error) {
        showAlert('Failed to schedule lesson: ' + error.message, false);
    }
}

function formatDateForApi(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    const seconds = String(date.getSeconds()).padStart(2, '0');
    
    return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
}

function formatTimeFromDate(date) {
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function showAlert(message, isSuccess) {
    alertMessage.textContent = message ?? '';
    alertMessage.className = `alert alert-${isSuccess ? 'success' : 'error'} show`;
    
    setTimeout(() => {
        alertMessage.classList.remove('show');
    }, 3000);
}

// Function to show lesson details
function showLessonDetails(lesson) {
    currentLesson = lesson;
    const modal = document.getElementById('lessonDetailsModal');
    
    // Populate modal with lesson details
    const startDate = new Date(lesson.startDate);
    const endDate = new Date(lesson.endDate);
    
    modal.querySelector('.date').textContent = startDate.toLocaleDateString();
    modal.querySelector('.time').textContent = `${formatTimeFromDate(startDate)} - ${formatTimeFromDate(endDate)}`;
    
    modal.querySelector('.subject').textContent = lesson.subjectName;
    modal.querySelector('.location').textContent = lesson.locationName || 'No location';
    
    const locationDetails = [];
    if (lesson.locationAddress) locationDetails.push(lesson.locationAddress);
    if (lesson.locationDescription) locationDetails.push(lesson.locationDescription);
    modal.querySelector('.location-details').textContent = locationDetails.join(' | ');
    
    // Populate topics
    const topicsList = modal.querySelector('.topics-list');
    topicsList.innerHTML = lesson.topics ? lesson.topics.map(topic => 
        `<span class="topic-tag">${topic.name}</span>`
    ).join('') : 'No topics assigned';
    
    // Show the modal
    modal.classList.add('show');
    
    // Setup event handlers
    setupModalEventHandlers();
}

function setupModalEventHandlers() {
    // Close button
    lessonDetailsModal.querySelector('.close-button').onclick = () => {
        lessonDetailsModal.classList.remove('show');
    };
    
    // Action buttons
    lessonDetailsModal.querySelectorAll('.action-button').forEach(button => {
        button.onclick = () => handleAction(button.dataset.action);
    });
    
    // Tracking button
    lessonDetailsModal.querySelector('.tracking-button').onclick = () => {
        handleTracking();
    };
    
    // Delete and Update buttons
    lessonDetailsModal.querySelector('[data-action="delete"]').onclick = () => {
        handleDelete();
    };
    
    lessonDetailsModal.querySelector('[data-action="update"]').onclick = () => {
        handleUpdate();
    };
    
    lessonDetailsModal.querySelector('[data-action="repeat"]').onclick = () => {
        handleRepeat()
    };
}

// Handler functions
function handleAction(action) {
    switch(action) {
        case 'topics':
            // TODO: Implement topics management
            console.log('Managing topics');
            break;
        case 'trackables':
            // TODO: Implement trackables management
            console.log('Managing trackables');
            break;
        case 'students':
            // TODO: Implement students management
            console.log('Managing students');
            break;
    }
}

function handleTracking() {
    // TODO: Implement tracking functionality
    console.log('Opening tracking');
}

function handleDelete() {
    if (confirm('Are you sure you want to delete this lesson?')) {
        // TODO: Implement delete functionality
        console.log('Deleting lesson');
    }
}

function handleUpdate() {
    // TODO: Implement update functionality
    console.log('Updating lesson');
}

function handleRepeat() {
    // TODO: Implement repeat functionality
    console.log('Setting repeat');
}

// Make functions available globally
window.showLessonDetails = showLessonDetails;

window.addLessonMember = function(id, username, email) {
    selectedStudents.add({ id, username, email });
    renderLessonMembers();
    document.getElementById('searchResults').classList.add('hidden');
    document.getElementById('studentSearchInput').value = '';
};

window.removeLessonMember = function(id) {
    selectedStudents.delete(Array.from(selectedStudents).find(student => student.id === id));
    renderLessonMembers();
};

// Initialize the calendar
init();