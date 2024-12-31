import { services } from '/dataHandler.js';
import { formatDateForApi } from '/utils/utilityFunctions.js';

// DOM Elements
let configBtn = null;
let configModal = null;
let formModal = null;
let trackableForm = null;
let trackablesList = null;
let alertMessage = null;
let startDate = null;
let endDate = null;
let addTrackableBtn = null;
let subjectSelect = null;
let classSelect = null;
let trackableSelect = null;
let generateReportBtn = null;
let reportTable = null;
let detailModal = null;

let currentTrackableId = null;
let selectedStudents = new Set();
let searchTimeout = null;

// Initialize
async function initTrackables() {
    try {
        // Initialize basic DOM elements
        configBtn = document.getElementById('configTrackablesBtn');
        configModal = document.getElementById('trackableConfigModal');
        formModal = document.getElementById('trackableFormModal');
        trackableForm = document.getElementById('trackableForm');
        trackablesList = document.getElementById('trackablesList');
        alertMessage = document.getElementById('alertMessage');
        startDate = document.getElementById('startDate');
        endDate = document.getElementById('endDate');
        addTrackableBtn = document.getElementById('addTrackableBtn');

        // Initialize report-related DOM elements
        subjectSelect = document.getElementById('subjectSelect');
        classSelect = document.getElementById('classSelect');
        trackableSelect = document.getElementById('trackableSelect');
        generateReportBtn = document.getElementById('generateReport');
        reportTable = document.getElementById('reportTable');
        detailModal = document.getElementById('detailModal');

        await loadTrackables();
        await loadFormData();
        setupEventListeners();
        setupReportEventListeners();
    } catch (error) {
        showAlert('Failed to initialize: ' + error.message, false);
    }
}

function setupEventListeners() {
    // Configuration button
    configBtn.addEventListener('click', () => {
        configModal.classList.add('show');
    });

    // Add trackable button
    addTrackableBtn.addEventListener('click', () => {
        openFormModal();
    });

    // Form submission
    trackableForm.addEventListener('submit', handleFormSubmit);

    // Close buttons
    document.querySelectorAll('.close-button, .cancel-button').forEach(button => {
        button.addEventListener('click', () => {
            configModal.classList.remove('show');
            formModal.classList.remove('show');
        });
    });
}

function setupReportEventListeners() {
    // Student type selection
    document.querySelectorAll('input[name="studentType"]').forEach(radio => {
        radio.addEventListener('change', toggleStudentSelectionType);
    });

    // Generate report button
    generateReportBtn.addEventListener('click', generateReport);

    // Student search
    const searchInput = document.getElementById('studentSearchInput');
    searchInput.addEventListener('input', handleStudentSearch);

    // Report table cell click
    reportTable.addEventListener('click', handleCellClick);

    // Detail modal close
    document.querySelector('#detailModal .close-button').addEventListener('click', () => {
        detailModal.classList.remove('show');
    });
}

async function loadTrackables() {
    try {
        const trackables = await services.trackable.getAll();
        renderTrackablesList(trackables);
    } catch (error) {
        showAlert('Failed to load trackables: ' + error.message, false);
    }
}

async function loadFormData() {
    try {
        const subjects = await services.subject.getPage();
        const classes = await services.class.getPage();
        const trackables = await services.trackable.getAll();

        // Populate subject select
        subjectSelect.innerHTML = subjects.map(subject =>
            `<option value="${subject.id}">${subject.name}</option>`
        ).join('');

        // Populate class select
        classSelect.innerHTML = classes.map(cls =>
            `<option value="${cls.id}">${cls.name}</option>`
        ).join('');

        // Populate trackable select
        trackableSelect.innerHTML = trackables.map(trackable =>
            `<option value="${trackable.name}">${trackable.name}</option>`
        ).join('');

    } catch (error) {
        showAlert('Failed to load form data: ' + error.message, false);
    }
}

function renderTrackablesList(trackables) {
    trackablesList.innerHTML = trackables.map(trackable => `
        <div class="config-item">
            <div class="config-item-content">
                <h3>${trackable.name}</h3>
                <p>${trackable.description || ''}</p>
            </div>
            <div class="config-item-actions">
                <button onclick="editTrackable('${trackable.name}')" class="edit-button">
                    <i class="fas fa-edit"></i>
                </button>
                <button onclick="deleteTrackable('${trackable.name}')" class="delete-button">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `).join('');
}

function openFormModal(trackableData = null) {
    const modalTitle = document.getElementById('trackableFormTitle');
    const submitButton = trackableForm.querySelector('.submit-button');
    
    if (trackableData) {
        modalTitle.textContent = 'Edit Trackable';
        submitButton.textContent = 'Update Trackable';
        currentTrackableId = trackableData.name;
        document.getElementById('trackableName').value = trackableData.name;
        document.getElementById('trackableDescription').value = trackableData.description || '';
    } else {
        modalTitle.textContent = 'Add New Trackable';
        submitButton.textContent = 'Add Trackable';
        currentTrackableId = null;
        trackableForm.reset();
    }
    
    formModal.classList.add('show');
}

async function handleFormSubmit(e) {
    e.preventDefault();
    
    const formData = {
        name: document.getElementById('trackableName').value,
        description: document.getElementById('trackableDescription').value
    };

    try {
        let apiResponse;

        if (currentTrackableId) {
            apiResponse = await services.trackable.update(currentTrackableId, formData);
        } else {
            apiResponse = await services.trackable.create(formData);
        }

        showAlert(apiResponse.message, apiResponse.isSuccessful);

        if (apiResponse.isSuccessful) {
            await loadTrackables();
            formModal.classList.remove('show');
        }
    } catch (error) {
        showAlert('Failed to save trackable: ' + error.message, false);
    }
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
    }
}

async function handleStudentSearch(e) {
    clearTimeout(searchTimeout);
    const searchTerm = e.target.value.trim();
    const searchResults = document.getElementById('searchResults');
    
    if (searchTerm.length === 0) {
        searchResults.classList.add('hidden');
        return;
    }

    searchTimeout = setTimeout(async () => {
        try {
            const students = await services.student.getPage(1, 5, "asc", "username", {
                username: searchTerm
            });
            renderSearchResults(students);
        } catch (error) {
            showAlert('Search failed: ' + error.message, false);
        }
    }, 300);
}

function renderSearchResults(students) {
    const searchResults = document.getElementById('searchResults');
    const existingIds = new Set(Array.from(selectedStudents).map(student => student.id));
    
    searchResults.innerHTML = students
        .filter(student => !existingIds.has(student.id))
        .map(student => `
            <div class="search-result-item">
                <div class="student-info">
                    <div class="student-name">${student.username}</div>
                    <div class="student-email">${student.email}</div>
                </div>
                <button onclick="addSelectedStudent(${student.id}, '${student.username}')"
                        class="action-button add">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        `).join('');
    
    searchResults.classList.remove('hidden');
}

function addSelectedStudent(id, username) {
    selectedStudents.add({ id, username });
    renderSelectedStudents();
    document.getElementById('searchResults').classList.add('hidden');
    document.getElementById('studentSearchInput').value = '';
}

function removeSelectedStudent(id) {
    selectedStudents.delete(Array.from(selectedStudents).find(student => student.id === id));
    renderSelectedStudents();
}

function renderSelectedStudents() {
    const studentsList = document.getElementById('selectedStudentsList');
    studentsList.innerHTML = Array.from(selectedStudents).map(student => `
        <div class="member-item">
            <div class="student-info">
                <div class="student-name">${student.username}</div>
            </div>
            <button onclick="removeSelectedStudent(${student.id})" 
                    class="action-button" title="Remove">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `).join('');
}

async function generateReport() {
    try {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        const subjects = Array.from(subjectSelect.selectedOptions).map(option => option.value);
        const trackables = Array.from(trackableSelect.selectedOptions).map(option => option.value);
        console.log(trackables)
        let classId;
        
        if (!startDate || !endDate) {
            showAlert('Please select both start and end dates', false);
            return;
        }

        if (trackables.length === 0) {
            showAlert('Please select at least one trackable', false);
            return;
        }

        let students;
        if (document.querySelector('input[name="studentType"]:checked').value === 'class') {
            classId = classSelect.value;
            if (!classId) {
                showAlert('Please select a class', false);
                students = null;
                return;
            }
        } else {
            if (selectedStudents.size === 0) {
                showAlert('Please select at least one student', false);
                classId = null;
                return;
            }
            students = Array.from(selectedStudents).map(s => s.id);
        }

        console.log(trackables)
        const reportData = await fetchReportData(startDate, endDate, subjects, students, classId, trackables);
        renderReport(reportData, trackables);
        
    } catch (error) {
        showAlert('Failed to generate report: ' + error.message, false);
    }
}

async function fetchReportData(startDateN, endDateN, subjects, students, trackables) {
    // TODO: Connect to your API endpoint
    startDate = formatDateForApi(new Date(startDateN));
    endDate = formatDateForApi(new Date(endDateN));
    
    try {
        const response = await services.trackable.getReport(startDate, endDate, subjects, students, trackables);
        return response.data;
    } catch (error) {
        throw new Error('Failed to fetch report data: ' + error.message);
    }
}

function renderReport(data, selectedTrackables) {
    // Set up table header
    const headerRow = reportTable.querySelector('thead tr');
    headerRow.innerHTML = `
        <th>Student Name</th>
        ${selectedTrackables.map(trackable => 
            `<th>${trackable}</th>`
        ).join('')}
    `;

    // Populate table body
    const tbody = reportTable.querySelector('tbody');
    tbody.innerHTML = data.students.map(student => `
        <tr>
            <td>${student.username}</td>
            ${selectedTrackables.map(trackableName => {
                const trackableData = student.trackables.find(t => t.name === trackableName);
                if (!trackableData) return '<td>Not Tracked</td>';
                return `
                    <td class="trackable-cell" 
                        data-student="${student.username}"
                        data-trackable="${trackableName}">
                        ${trackableData.isTrue}/${trackableData.total}
                    </td>`;
            }).join('')}
        </tr>
    `).join('');
}

async function handleCellClick(e) {
    const cell = e.target.closest('.trackable-cell');
    if (!cell) return;

    const studentName = cell.dataset.student;
    const trackableName = cell.dataset.trackable;
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    
    try {
        const detailData = await fetchDetailData(studentName, trackableName, startDate, endDate);
        renderDetailModal(detailData, trackableName);
        detailModal.classList.add('show');
    } catch (error) {
        showAlert('Failed to load details: ' + error.message, false);
    }
}

async function fetchDetailData(studentName, trackableName, startDate, endDate) {
    // TODO: Connect to your API endpoint
    const params = {
        studentName,
        trackableName,
        startDate,
        endDate
    };
    
    try {
        const response = await services.trackable.getDetails(params);
        return response.data;
    } catch (error) {
        throw new Error('Failed to fetch detail data: ' + error.message);
    }
}

function renderDetailModal(data, trackableName) {
    const modalTitle = document.querySelector('#detailModal .modal-header h2');
    modalTitle.textContent = `${trackableName} Details`;

    const tbody = document.querySelector('#detailTable tbody');
    tbody.innerHTML = data.map(entry => {
        const date = new Date(entry.DateTime);
        return `
            <tr>
                <td>${date.toLocaleDateString()}</td>
                <td>${date.toLocaleTimeString()}</td>
                <td>${entry.subjectName}</td>
                <td>${entry.isTrue === null ? 'Not Tracked' : entry.isTrue ? 'Yes' : 'No'}</td>
            </tr>
        `;
    }).join('');
}

function showAlert(message, isSuccess) {
    alertMessage.textContent = message;
    alertMessage.className = `alert alert-${isSuccess ? 'success' : 'error'} show`;
    
    setTimeout(() => {
        alertMessage.classList.remove('show');
    }, 3000);
}

// Make functions available globally
window.editTrackable = async function(name) {
    try {
        const trackableData = await services.trackable.getDetails(name);
        openFormModal(trackableData);
    } catch (error) {
        showAlert('Failed to load trackable details: ' + error.message, false);
    }
};

window.deleteTrackable = async function(name) {
    if (confirm('Are you sure you want to delete this trackable?')) {
        try {
            let apiResponse = await services.trackable.delete(name);
            showAlert(apiResponse.message, apiResponse.isSuccessful);
            await loadTrackables();
        } catch (error) {
            showAlert('Failed to delete trackable: ' + error.message, false);
        }
    }
};

// Make functions available globally
window.addSelectedStudent = addSelectedStudent;
window.removeSelectedStudent = removeSelectedStudent;

// Initialize the page
// initTrackables();

export { initTrackables };