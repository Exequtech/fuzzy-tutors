// locations.js
import { services } from '/dataHandler.js';
import { formatDateForApi } from '/utils/utilityFunctions.js';

// DOM Elements
let configBtn = null;
let configModal = null;
let formModal = null;
let locationForm = null;
let locationsList = null;
let alertMessage = null;
let startDate = null;
let endDate = null;
let addLocationBtn = null;
let subjectSelect = null;
let locationSelect = null;
let generateReportBtn = null;
let reportTable = null;

let currentLocationId = null;

// Initialize
async function initLocations() {
    try {
        // Initialize basic DOM elements
        configBtn = document.getElementById('configLocationsBtn');
        configModal = document.getElementById('locationConfigModal');
        formModal = document.getElementById('locationFormModal');
        locationForm = document.getElementById('locationForm');
        locationsList = document.getElementById('locationsList');
        alertMessage = document.getElementById('alertMessage');
        startDate = document.getElementById('startDate');
        endDate = document.getElementById('endDate');
        addLocationBtn = document.getElementById('addLocationBtn');

        // Initialize report-related DOM elements
        subjectSelect = document.getElementById('subjectSelect');
        locationSelect = document.getElementById('locationSelect');
        generateReportBtn = document.getElementById('generateReport');
        reportTable = document.getElementById('reportTable');

        await loadLocations();
        await loadFormData();
        setupEventListeners();
        setupFilterEventListeners();
    } catch (error) {
        showAlert('Failed to initialize: ' + error.message, false);
    }
}

function setupEventListeners() {
    // Configuration button
    configBtn.addEventListener('click', () => {
        configModal.classList.add('show');
    });

    // Add location button
    addLocationBtn.addEventListener('click', () => {
        openFormModal();
    });

    // Form submission
    locationForm.addEventListener('submit', handleFormSubmit);

    // Close buttons
    document.querySelectorAll('.close-button, .cancel-button').forEach(button => {
        button.addEventListener('click', () => {
            configModal.classList.remove('show');
            formModal.classList.remove('show');
            document.getElementById('filtersModal').classList.remove('show');
        });
    });
}

function setupFilterEventListeners() {
    // Date change events
    startDate.addEventListener('change', handleDateChange);
    endDate.addEventListener('change', handleDateChange);

    // Filter modal open button
    const openFiltersBtn = document.getElementById('openFiltersBtn');
    const filtersModal = document.getElementById('filtersModal');
    
    openFiltersBtn.addEventListener('click', () => {
        filtersModal.classList.add('show');
    });

    // Generate report button
    generateReportBtn.addEventListener('click', () => {
        generateReport();
        filtersModal.classList.remove('show');
    });
}

async function handleDateChange() {
    const startDateValue = startDate.value;
    const endDateValue = endDate.value;

    if (startDateValue && endDateValue) {
        // Clear the table
        const tbody = reportTable.querySelector('tbody');
        tbody.innerHTML = '';
        
        // Show message that filters need to be applied
        tbody.innerHTML = `
            <tr>
                <td colspan="100%" style="text-align: center; padding: 20px;">
                    Click the Filters button to set additional filters and generate the report
                </td>
            </tr>
        `;
    }
}

async function loadLocations() {
    try {
        const locations = await services.location.getAllPages();
        renderLocationsList(locations);
    } catch (error) {
        showAlert('Failed to load locations: ' + error.message, false);
    }
}

async function loadFormData() {
    try {
        const subjects = await services.subject.getAllPages();
        const locations = await services.location.getAllPages();

        // Populate subject select
        subjectSelect.innerHTML = subjects.map(subject =>
            `<option value="${subject.id}">${subject.name}</option>`
        ).join('');

        // Populate location select
        locationSelect.innerHTML = locations.map(location =>
            `<option value="${location.id}">${location.name}</option>`
        ).join('');

    } catch (error) {
        showAlert('Failed to load form data: ' + error.message, false);
    }
}

function renderLocationsList(locations) {
    locationsList.innerHTML = locations.map(location => `
        <div class="config-item">
            <div class="config-item-content">
                <h3>${location.name}</h3>
                <p class="address">${location.address}</p>
                <p>${location.description || ''}</p>
            </div>
            <div class="config-item-actions">
                <button onclick="editLocation(${location.id})" class="edit-button">
                    <i class="fas fa-edit"></i>
                </button>
                <button onclick="deleteLocation(${location.id})" class="delete-button">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `).join('');
}

function openFormModal(locationData = null) {
    const modalTitle = document.getElementById('locationFormTitle');
    const submitButton = locationForm.querySelector('.submit-button');
    
    if (locationData) {
        modalTitle.textContent = 'Edit Location';
        submitButton.textContent = 'Update Location';
        currentLocationId = locationData.id;
        document.getElementById('locationName').value = locationData.name;
        document.getElementById('locationAddress').value = locationData.address;
        document.getElementById('locationDescription').value = locationData.description || '';
    } else {
        modalTitle.textContent = 'Add New Location';
        submitButton.textContent = 'Add Location';
        currentLocationId = null;
        locationForm.reset();
    }
    
    formModal.classList.add('show');
}

async function handleFormSubmit(e) {
    e.preventDefault();
    
    const formData = {
        name: document.getElementById('locationName').value,
        address: document.getElementById('locationAddress').value,
        description: document.getElementById('locationDescription').value
    };

    try {
        let apiResponse;

        if (currentLocationId) {
            apiResponse = await services.location.update(currentLocationId, formData);
        } else {
            apiResponse = await services.location.create(formData);
        }

        showAlert(apiResponse.message, apiResponse.isSuccessful);

        if (apiResponse.isSuccessful) {
            await loadLocations();
            formModal.classList.remove('show');
        }
    } catch (error) {
        showAlert('Failed to save location: ' + error.message, false);
    }
}

async function generateReport() {
    try {
        const startDateValue = document.getElementById('startDate').value;
        const endDateValue = document.getElementById('endDate').value;
        const locations = Array.from(locationSelect.selectedOptions).map(option => option.value);
        const subjects = Array.from(subjectSelect.selectedOptions).map(option => option.value);

        if (!startDateValue || !endDateValue) {
            showAlert('Please select both start and end dates', false);
            return;
        }

        if (locations.length === 0) {
            showAlert('Please select at least one location', false);
            return;
        }

        const reportData = await fetchReportData(startDateValue, endDateValue, locations, subjects);
        renderReport(reportData);
        
    } catch (error) {
        showAlert('Failed to generate report: ' + error.message, false);
    }
}

async function fetchReportData(startDateStr, endDateStr, locations, subjects) {
    const startDate = formatDateForApi(new Date(startDateStr));
    const endDate = formatDateForApi(new Date(endDateStr));
    
    try {
        const response = await services.location.getReport(startDate, endDate, locations, subjects);

        return response;
    } catch (error) {
        throw new Error('Failed to fetch report data: ' + error.message);
    }
}

function renderReport(data) {
    // Set up table header
    const headerRow = reportTable.querySelector('thead tr');
    headerRow.innerHTML = `
        <th>Date</th>
        <th>Time</th>
        <th>Duration</th>
        <th>Location</th>
        <th>Subject</th>
        <th>Total Students</th>
    `;

    // Calculate and format data for display
    const formattedData = data.map(lesson => {
        const startDate = new Date(lesson.startDate);
        const endDate = new Date(lesson.endDate);
        const duration = (endDate - startDate) / (1000 * 60); // Duration in minutes
        
        return {
            date: startDate.toLocaleDateString(),
            time: `${startDate.toLocaleTimeString()} - ${endDate.toLocaleTimeString()}`,
            duration: `${duration} minutes`,
            location: lesson.locationName,
            subject: lesson.subjectName,
            totalStudents: lesson.students ? lesson.students.length : 0
        };
    });

    // Populate table body
    const tbody = reportTable.querySelector('tbody');
    tbody.innerHTML = formattedData.map(row => `
        <tr>
            <td>${row.date}</td>
            <td>${row.time}</td>
            <td>${row.duration}</td>
            <td>${row.location}</td>
            <td>${row.subject}</td>
            <td>${row.totalStudents}</td>
        </tr>
    `).join('');
}

function showAlert(message, isSuccess) {
    alertMessage.textContent = message;
    alertMessage.className = `alert alert-${isSuccess ? 'success' : 'error'} show`;
    
    setTimeout(() => {
        alertMessage.classList.remove('show');
    }, 3000);
}

// Make functions available globally
window.editLocation = async function(id) {
    try {
        const locationData = await services.location.getDetails(id);
        openFormModal(locationData);
    } catch (error) {
        showAlert('Failed to load location details: ' + error.message, false);
    }
};

window.deleteLocation = async function(id) {
    if (confirm('Are you sure you want to delete this location?')) {
        try {
            let apiResponse = await services.location.delete(id);
            showAlert(apiResponse.message, apiResponse.isSuccessful);
            await loadLocations();
        } catch (error) {
            showAlert('Failed to delete location: ' + error.message, false);
        }
    }
};

export { initLocations };