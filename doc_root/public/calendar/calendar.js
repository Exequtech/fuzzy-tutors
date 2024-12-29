import { 
    services
} from '../dataHandler.js';

// State Management
let currentDate = new Date();
let currentMonth = currentDate.getMonth();
let currentYear = currentDate.getFullYear();
let lessons = [];
let subjects = [];
let classes = [];
let locations = [];
let trackables = [];
let selectedStudents = new Set();
let currentLesson = null;
let searchTimeout = null;

// Modal Management
let modals = {
    lesson: document.getElementById('lessonModal'),
    lessonDetails: document.getElementById('lessonDetailsModal'),
    update: document.getElementById('updateLessonModal'),
    topics: document.getElementById('manageTopicsModal'),
    trackables: document.getElementById('manageTrackablesModal'),
    students: document.getElementById('manageStudentsModal'),
    tracking: document.getElementById('trackingModal')
};

// Initialize
async function initCalendar() {
    try {
        currentDate = new Date();
        currentMonth = currentDate.getMonth();
        currentYear = currentDate.getFullYear();
        lessons = [];
        subjects = [];
        classes = [];
        locations = [];
        trackables = [];
        selectedStudents = new Set();
        currentLesson = null;
        searchTimeout = null;

        modals = {
            lesson: document.getElementById('lessonModal'),
            lessonDetails: document.getElementById('lessonDetailsModal'),
            update: document.getElementById('updateLessonModal'),
            topics: document.getElementById('manageTopicsModal'),
            trackables: document.getElementById('manageTrackablesModal'),
            students: document.getElementById('manageStudentsModal'),
            tracking: document.getElementById('trackingModal')
        };

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
        subjects = await services.subject.getPage();
        classes = await services.class.getPage();
        locations = await services.location.getAll();
        trackables = await services.trackable.getAll();

        populateSubjectSelect();
        populateClassSelect();
        populateLocationSelect();
    } catch (error) {
        showAlert('Failed to load form data: ' + error.message, false);
    }
}

// Event Listeners Setup
function setupEventListeners() {
    // Navigation
    document.getElementById('prevMonth').addEventListener('click', () => {
        currentMonth--;
        if (currentMonth < 0) {
            currentMonth = 11;
            currentYear--;
        }
        fetchAndRenderLessons();
    });

    document.getElementById('nextMonth').addEventListener('click', () => {
        currentMonth++;
        if (currentMonth > 11) {
            currentMonth = 0;
            currentYear++;
        }
        fetchAndRenderLessons();
    });

    // Add Lesson Button
    document.getElementById('addLessonBtn').addEventListener('click', () => openLessonModal());

    // Close Buttons
    document.querySelectorAll('.close-button, .cancel-button').forEach(button => {
        button.addEventListener('click', closeAllModals);
    });

    // Action Buttons in Lesson Details Modal
    document.querySelectorAll('.action-button, .tracking-button, .delete-button, .edit-button').forEach(button => {
        button.addEventListener('click', (e) => handleModalAction(e.target.closest('button').dataset.action));
    });

    // Setup Student Type Selection
    document.querySelectorAll('input[name="studentType"]').forEach(radio => {
        radio.addEventListener('change', toggleStudentSelectionType);
    });
}

function setupFormEventListeners() {
    // Main Lesson Form
    document.getElementById('lessonForm').addEventListener('submit', handleLessonSubmit);
    
    // Update Lesson Form
    document.getElementById('updateLessonForm').addEventListener('submit', handleUpdateLessonSubmit);
    
    // Save Buttons for Management Modals
    document.getElementById('saveTopicsBtn').addEventListener('click', handleSaveTopics);
    document.getElementById('saveTrackablesBtn').addEventListener('click', handleSaveTrackables);
    document.getElementById('saveStudentsBtn').addEventListener('click', handleSaveStudents);
    document.getElementById('saveTrackingBtn').addEventListener('click', handleSaveTracking);

    // Student Search
    setupStudentManagement();
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

// Modal Action Handlers
function handleModalAction(action) {
    hideAllModals();

    switch(action) {
        case 'update':
            showUpdateModal();
            break;
        case 'topics':
            showTopicsModal();
            break;
        case 'trackables':
            showTrackablesModal();
            break;
        case 'students':
            showStudentsModal();
            break;
        case 'tracking':
            showTrackingModal();
            break;
        case 'delete':
            handleDeleteLesson();
            break;
        default:
            console.error(`${action} (action) does not exist or is not defined in sistem`);
            break;
    }
}

// Modal Display Functions
function showUpdateModal() {
    const modal = modals.update;
    const startDate = new Date(currentLesson.startDate);
    const endDate = new Date(currentLesson.endDate);
    
    document.getElementById('updateDate').value = startDate.toISOString().split('T')[0];
    document.getElementById('updateStartTime').value = startDate.toTimeString().slice(0,5);
    document.getElementById('updateEndTime').value = endDate.toTimeString().slice(0,5);
    
    populateSubjectSelect('updateSubject', currentLesson.subjectId);
    populateLocationSelect('updateLocation', currentLesson.locationId);

    modal.classList.add('show');
}

function populateSubjectSelect(selectId = 'subjectSelect', currentSubjectId = null) {
    const select = document.getElementById(selectId);
    
    // Find current subject if an ID is provided
    const currentSubject = currentSubjectId 
        ? subjects.find(subject => subject.id === currentSubjectId) 
        : null;
    
    // Build options array starting with appropriate first option
    let options = [];
    
    if (currentSubjectId === null) {
        // Case 1: If null is provided, add empty option
        options.push('<option value="">Select a subject</option>');
    } else if (!currentSubject && currentSubjectId) {
        // Case 2: If ID provided but not found, add warning option
        options.push(`<option value="">Subject ID ${currentSubjectId} not found</option>`);
    } else if (currentSubject) {
        // Case 3: If current subject exists, make it first option
        options.push(`<option value="${currentSubject.id}">${currentSubject.name}</option>`);
    }
    
    // Add remaining subjects (excluding current subject to avoid duplication)
    const remainingSubjects = subjects.filter(subject => 
        subject.id !== currentSubjectId
    );
    
    options = options.concat(
        remainingSubjects.map(subject =>
            `<option value="${subject.id}">${subject.name}</option>`
        )
    );
    
    select.innerHTML = options.join('');
}

function populateClassSelect(selectId = 'classSelect', currentClassId = null) {
    const select = document.getElementById(selectId);
    
    // Find current class if an ID is provided
    const currentClass = currentClassId 
        ? classes.find(cls => cls.id === currentClassId) 
        : null;
    
    // Build options array starting with appropriate first option
    let options = [];
    
    if (currentClassId === null) {
        // Case 1: If null is provided, add empty option
        options.push('<option value="">Select a class</option>');
    } else if (!currentClass && currentClassId) {
        // Case 2: If ID provided but not found, add warning option
        options.push(`<option value="">Class ID ${currentClassId} not found</option>`);
    } else if (currentClass) {
        // Case 3: If current class exists, make it first option
        options.push(`<option value="${currentClass.id}">${currentClass.name}</option>`);
    }
    
    // Add remaining classes (excluding current class to avoid duplication)
    const remainingClasses = classes.filter(cls => 
        cls.id !== currentClassId
    );
    
    options = options.concat(
        remainingClasses.map(cls =>
            `<option value="${cls.id}">${cls.name}</option>`
        )
    );
    
    select.innerHTML = options.join('');
}

function populateLocationSelect(selectId = 'locationSelect', currentLocationId = null) {
    const select = document.getElementById(selectId);
    
    // Find current location if an ID is provided
    const currentLocation = currentLocationId 
        ? locations.find(location => location.id === currentLocationId) 
        : null;
    
    // Build options array starting with appropriate first option
    let options = [];
    
    if (currentLocationId === null) {
        // Case 1: If null is provided, add empty option
        options.push('<option value="">Select a location</option>');
    } else if (!currentLocation && currentLocationId) {
        // Case 2: If ID provided but not found, add warning option
        options.push(`<option value="">Location ID ${currentLocationId} not found</option>`);
    } else if (currentLocation) {
        // Case 3: If current location exists, make it first option
        options.push(`<option value="${currentLocation.id}">${currentLocation.name}</option>`);
    }
    
    // Add remaining locations (excluding current location to avoid duplication)
    const remainingLocations = locations.filter(location => 
        location.id !== currentLocationId
    );
    
    options = options.concat(
        remainingLocations.map(location =>
            `<option value="${location.id}">${location.name}</option>`
        )
    );
    
    select.innerHTML = options.join('');
}

async function showTopicsModal() {
    const modal = modals.topics;
    const subject = subjects.find(s => s.id === currentLesson.subjectId);

    console.log("Test to see if contains topic IDs and names:" + subject);
    
    if (subject && subject.topics) {
        console.log("Test: Decision construct runs that calls renderTopicLists()");
        renderTopicLists(subject.topics, currentLesson.topics || []);
    }
    
    modal.classList.add('show');
}

async function showTrackablesModal() {
    const modal = modals.trackables;
    renderTrackableLists(trackables, currentLesson.trackables || []);
    modal.classList.add('show');
}

function showStudentsModal() {
    const modal = modals.students;
    setupStudentManagement();
    modal.classList.add('show');
}

function showTrackingModal() {
    const modal = modals.tracking;
    renderTrackingTable();
    modal.classList.add('show');
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
        const topics = [];
        
        // Get selected trackables
        const selectedTrackables = [];

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

        const lessonData = {
            subjectId,
            startDate,
            endDate,
            trackables: [],
            topics,
            locationId
        };

        if (classId == null) {
            lessonData.students = students;
        } else {
            lessonData.classId = classId;
        }

        // Make API call to create lesson
        const response = await services.lesson.createLesson(lessonData);

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

// Form Submission Handlers
async function handleUpdateLessonSubmit(e) {
    e.preventDefault();
    try {
        const date = document.getElementById('updateDate').value;
        const startTime = document.getElementById('updateStartTime').value;
        const endTime = document.getElementById('updateEndTime').value;

        const startDate = new Date(`${date}T${startTime}`);
        const endDate = new Date(`${date}T${endTime}`);

        const formData = {
            subjectId: parseInt(document.getElementById('updateSubject').value),
            startDate: startDate,
            endDate: endDate
        };
        
        if (endDate <= startDate) {
            showAlert('End time must be after start time', false);
            return;
        }
        
        const response = await services.lesson.update(currentLesson.id, formData);

        if (response.isSuccessful) {
            showAlert('Lesson updated successfully!', true);
            await fetchAndRenderLessons();
            closeAllModals();
        } else {
            showAlert(response.message || 'Failed to update lesson', false);
        }
    } catch (error) {
        showAlert('Failed to update lesson: ' + error.message, false);
    }
}

async function handleSaveTopics() {
    try {
        const selectedTopics = Array.from(
            document.querySelectorAll('#selectedTopicsList .topic-item')
        ).map(item => parseInt(item.dataset.id));
        
        const data = {
            topics: selectedTopics
        }

        const response = await services.lesson.update(currentLesson.id, data);
        
        if (response.isSuccessful) {
            showAlert('Topics updated successfully!', true);
            await fetchAndRenderLessons();
            closeAllModals();
        } else {
            showAlert(response.message || 'Failed to update topics', false);
        }
    } catch (error) {
        showAlert('Failed to save topics: ' + error.message, false);
    }
}

async function handleSaveTrackables() {
    try {
        const selectedTrackables = Array.from(
            document.querySelectorAll('#selectedTrackablesList .trackable-item')
        ).map(item => item.dataset.name);
        
        const data = {
            trackables: selectedTrackables
        }

        const response = await services.lesson.update(currentLesson.id, data);
        
        if (response.isSuccessful) {
            showAlert('Trackables updated successfully!', true);
            await fetchAndRenderLessons();
            closeAllModals();
        } else {
            showAlert(response.message || 'Failed to update trackables', false);
        }
    } catch (error) {
        showAlert('Failed to save trackables: ' + error.message, false);
    }
}

async function handleSaveStudents() {
    try {
        const studentType = document.querySelector('input[name="studentType"]:checked').value;
        let classId = null;
        let students = null;
        
        if (studentType === 'class') {
            classId = parseInt(document.getElementById('classSelect').value);
        } else {
            students = Array.from(selectedStudents).map(student => student.id);
        }
        
        if (!classId && (!students || students.length === 0)) {
            showAlert('Please select either a class or at least one student', false);
            return;
        }

        const data = {};

        if (classId === null) {
            data.students = students;
        } else {
            data.classId = classId;
        }

        const response = await services.lesson.update(currentLesson.id, data);
        
        if (response.isSuccessful) {
            showAlert('Students updated successfully!', true);
            await fetchAndRenderLessons();
            closeAllModals();
        } else {
            showAlert(response.message || 'Failed to update students', false);
        }
    } catch (error) {
        showAlert('Failed to save students: ' + error.message, false);
    }
}

// Tracking table saving (todo)
async function handleSaveTracking() {
    try {
        const studentOverrides = {}
        document.querySelectorAll('#trackingTable tbody tr').forEach(row => {
            const student = {}
            student.attended = row.querySelector('.attendance-checkbox').checked;
            student.trackables = {};
            row.querySelectorAll('.trackable-checkbox').forEach(chkbox => {
                student.trackables[chkbox.dataset.trackable] = chkbox.checked;
            })
            studentOverrides[parseInt(row.dataset.studentId)] = student
        })
        // const trackingData = Array.from(
        //     document.querySelectorAll('#trackingTable tbody tr')
        // ).map(row => ({
        //     studentId: parseInt(row.dataset.studentId),
        //     attendance: row.querySelector('.attendance-checkbox').checked,
        //     trackables: Array.from(row.querySelectorAll('.trackable-checkbox')).map(checkbox => ({
        //         name: checkbox.dataset.trackable,
        //         value: checkbox.checked
        //     }))
        // }));

        const data = {
            studentOverrides
        }
        
        const response = await services.lesson.update(currentLesson.id, data);
        
        if (response.isSuccessful) {
            showAlert('Tracking data saved successfully!', true);
            closeAllModals();
        } else {
            showAlert(response.message || 'Failed to save tracking data', false);
        }
    } catch (error) {
        showAlert('Failed to save tracking data: ' + error.message, false);
    }
}

async function handleDeleteLesson() {
    if (confirm('Are you sure you want to delete this lesson?')) {
        try {
            const response = await services.lesson.delete(currentLesson.id);
            
            if (response.isSuccessful) {
                showAlert('Lesson deleted successfully!', true);
                await fetchAndRenderLessons();
                closeAllModals();
            } else {
                showAlert(response.message || 'Failed to delete lesson', false);
            }
        } catch (error) {
            showAlert('Failed to delete lesson: ' + error.message, false);
        }
    }
}

// Render Functions
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
                <!-- Params: JSON.stringify(lesson).replace(/"/g, '&quot;') -->
                <div class="lesson-item" data-lesson-id="${lesson.id}" onclick="showLessonDetails(${lesson.id})">
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

async function fetchAndRenderLessons() {
    const firstDay = new Date(currentYear, currentMonth, 1);
    const lastDay = new Date(currentYear, currentMonth + 1, 0);
    
    try {
        lessons = await services.lesson.getLessonsBetweenDates(
            formatDateForApi(firstDay),
            formatDateForApi(lastDay)
        );
        renderCalendar();
    } catch (error) {
        showAlert('Failed to fetch lessons: ' + error.message, false);
    }
}

function renderTopicLists(availableTopics, selectedTopics) {
    const availableList = document.getElementById('availableTopicsList');
    const selectedList = document.getElementById('selectedTopicsList');
    
    availableList.innerHTML = availableTopics
        .filter(topic => !selectedTopics.find(st => st.id === topic.id))
        .map(topic => createTopicItem(topic))
        .join('');
        
    selectedList.innerHTML = selectedTopics
        .map(topic => createTopicItem(topic))
        .join('');
        
    setupTopicDragAndDrop();
}

function renderTrackableLists(availableTrackables, selectedTrackables) {
    const availableList = document.getElementById('availableTrackablesList');
    const selectedList = document.getElementById('selectedTrackablesList');
    
    availableList.innerHTML = availableTrackables
        .filter(trackable => !selectedTrackables.includes(trackable.name))
        .map(trackable => createTrackableItem(trackable))
        .join('');
        
    selectedList.innerHTML = selectedTrackables
        .map(trackableName => {
            const trackable = availableTrackables.find(t => t.name === trackableName);
            return createTrackableItem(trackable);
        })
        .join('');
        
    setupTrackableDragAndDrop();
}

function renderTrackingTable() {
    const table = document.getElementById('trackingTable');
    const thead = table.querySelector('thead tr');
    const tbody = table.querySelector('tbody');
    
    thead.innerHTML = `
        <th>Student</th>
        <th>Attendance</th>
        ${currentLesson.trackables.map(trackable => 
            `<th>${trackable}</th>`
        ).join('')}
    `;
    
    tbody.innerHTML = currentLesson.students.map(student => `
        <tr data-student-id="${student.id}">
            <td>${student.username}</td>
            <td>
                <input type="checkbox" 
                       class="attendance-checkbox" 
                       ${student.attendance ? 'checked' : ''}>
            </td>
            ${currentLesson.trackables.map(trackable => `
                <td>
                    <input type="checkbox" 
                           class="trackable-checkbox"
                           data-trackable="${trackable}"
                           ${student.trackables?.[trackable] ? 'checked' : ''}>
                </td>
            `).join('')}
        </tr>
    `).join('');
}

// Helper Functions
function createTopicItem(topic) {
    return `
        <div class="topic-item" data-id="${topic.id}" draggable="true">
            <span class="topic-name">${topic.name}</span>
        </div>
    `;
}

function createTrackableItem(trackable) {
    return `
        <div class="trackable-item" data-name="${trackable.name}" draggable="true">
            <span class="trackable-name">${trackable.name}</span>
        </div>
    `;
}

function setupTopicDragAndDrop() {
    const items = document.querySelectorAll('.topic-item');
    const lists = document.querySelectorAll('.topics-list-inline');

    items.forEach(item => {
        item.addEventListener('dragstart', handleDragStart);
        item.addEventListener('dragend', handleDragEnd);
    });

    lists.forEach(list => {
        list.addEventListener('dragover', handleDragOver);
        list.addEventListener('drop', handleDrop);
    });
}

function setupTrackableDragAndDrop() {
    const items = document.querySelectorAll('.trackable-item');
    const lists = document.querySelectorAll('.trackable-list');

    items.forEach(item => {
        item.addEventListener('dragstart', handleDragStart);
        item.addEventListener('dragend', handleDragEnd);
    });

    lists.forEach(list => {
        list.addEventListener('dragover', handleDragOver);
        list.addEventListener('drop', handleDrop);
    });
}

// Drag and Drop Event Handlers
function handleDragStart(e) {
    e.target.classList.add('dragging');
    e.dataTransfer.setData('text/plain', e.target.outerHTML);
}

function handleDragEnd(e) {
    e.target.classList.remove('dragging');
}

function handleDragOver(e) {
    e.preventDefault();
    e.currentTarget.classList.add('drag-over');
}

function handleDrop(e) {
    e.preventDefault();
    const data = e.dataTransfer.getData('text/plain');
    const draggingElement = document.querySelector('.dragging');
    
    if (draggingElement) {
        draggingElement.remove();
    }
    
    e.currentTarget.innerHTML += data;
    e.currentTarget.classList.remove('drag-over');
    
    // Re-setup drag and drop
    setupTopicDragAndDrop();
    setupTrackableDragAndDrop();
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

// Utility Functions
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

function hideAllModals() {
    Object.values(modals).forEach(modal => modal.classList.remove('show'));
}

function closeAllModals() {
    hideAllModals();
    currentLesson = null;
    selectedStudents = new Set();
    const searchResults = document.getElementById('searchResults');
    if (searchResults) {
        searchResults.classList.add('hidden');
    }
}


// Student Management
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
                const students = await services.student.getPage(1, 5, "asc", "username", {
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

function toggleStudentSelectionType(e) {
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
}

function showAlert(message, isSuccess) {
    const alertElement = document.getElementById('alertMessage');
    alertElement.textContent = message;
    alertElement.className = `alert alert-${isSuccess ? 'success' : 'error'} show`;
    
    setTimeout(() => {
        alertElement.classList.remove('show');
    }, 3000);
}

// Make functions available globally
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

window.showLessonDetails = async function(lessonId) {
    currentLesson = await services.lesson.getDetails(lessonId);
    console.log(currentLesson)
    const modal = modals.lessonDetails;
    
    const startDate = new Date(currentLesson.startDate);
    const endDate = new Date(currentLesson.endDate);
    
    modal.querySelector('.date').textContent = startDate.toLocaleDateString();
    modal.querySelector('.time').textContent = `${formatTimeFromDate(startDate)} - ${formatTimeFromDate(endDate)}`;
    modal.querySelector('.subject').textContent = currentLesson.subjectName;
    modal.querySelector('.location').textContent = currentLesson.locationName || 'No location';
    
    const locationDetails = [];
    if (currentLesson.locationAddress) locationDetails.push(currentLesson.locationAddress);
    if (currentLesson.locationDescription) locationDetails.push(currentLesson.locationDescription);
    modal.querySelector('.location-details').textContent = locationDetails.join(' | ');
    
    const topicsList = modal.querySelector('.topics-list');
    topicsList.innerHTML = currentLesson.topics ? currentLesson.topics.map(topic => 
        `<span class="topic-tag">${topic.name}</span>`
    ).join('') : 'No topics assigned';
    
    modal.classList.add('show');
};

// Initialize the calendar
// initCalendar();

export {
    initCalendar
}