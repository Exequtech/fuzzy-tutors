import { services } from '/dataHandler.js';

// DOM Elements
const configBtn = document.getElementById('configTrackablesBtn');
const configModal = document.getElementById('trackableConfigModal');
const formModal = document.getElementById('trackableFormModal');
const trackableForm = document.getElementById('trackableForm');
const trackablesList = document.getElementById('trackablesList');
const alertMessage = document.getElementById('alertMessage');
const startDate = document.getElementById('startDate');
const endDate = document.getElementById('endDate');
const trackableSelect = document.getElementById('trackableSelect');
const addTrackableBtn = document.getElementById('addTrackableBtn');

let currentTrackableId = null;

// Initialize
async function initTrackables() {
    try {
        await loadTrackables();
        setupEventListeners();
        setupChart();
        await updateReport();
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

    // Report filters
    startDate.addEventListener('change', updateReport);
    endDate.addEventListener('change', updateReport);
    trackableSelect.addEventListener('change', updateReport);
}

async function loadTrackables() {
    try {
        // TODO: Replace with actual API call
        // const trackables = [
        //     {name: 'Homework', description: 'Homework completion tracking' },
        //     {name: 'Participation', description: 'Class participation tracking' }
        // ];

        const trackables = await services.trackable.getAll();
        renderTrackablesList(trackables);
        populateTrackableSelect(trackables);
    } catch (error) {
        showAlert('Failed to load trackables: ' + error.message, false);
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

function populateTrackableSelect(trackables) {
    trackableSelect.innerHTML = trackables.map(trackable => 
        `<option value=${trackable.name}>${trackable.name}</option>`
    ).join('');
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

    let apiResponse;

    try {

        if (currentTrackableId) {
            const data = {
                name: formData.name,
                description: formData.description
            }

            apiResponse = await services.trackable.update(currentTrackableId, data);
            showAlert(apiResponse.message, apiResponse.isSuccessful);
        } else {
            // Add new trackable
            const data = {
                name: formData.name,
                description: formData.description
            }

            apiResponse = await services.trackable.create(data);
            showAlert(apiResponse.message, apiResponse.isSuccessful);
        }

        await loadTrackables();
        formModal.classList.remove('show');
    } catch (error) {
        showAlert('Failed to save trackable: ' + error.message, false);
    }
}

async function updateReport() {
    try {
        // TODO: Replace with actual API call to get report data
        const data = {
            labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
            values: [75, 82, 90, 85]
        };

        updateChart(data);
        updateStats(data);
    } catch (error) {
        showAlert('Failed to update report: ' + error.message, false);
    }
}

let chart = null;

function setupChart() {
    const ctx = document.getElementById('trackableChart').getContext('2d');
    chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Completion Rate (%)',
                data: [],
                borderColor: '#57cc02',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
}

function updateChart(data) {
    chart.data.labels = data.labels;
    chart.data.datasets[0].data = data.values;
    chart.update();
}

function updateStats(data) {
    const average = data.values.reduce((a, b) => a + b, 0) / data.values.length;
    const max = Math.max(...data.values);
    const min = Math.min(...data.values);

    document.querySelector('.stats-container').innerHTML = `
        <div class="stat-card">
            <h3>Average</h3>
            <p>${average.toFixed(1)}%</p>
        </div>
        <div class="stat-card">
            <h3>Highest</h3>
            <p>${max}%</p>
        </div>
        <div class="stat-card">
            <h3>Lowest</h3>
            <p>${min}%</p>
        </div>
    `;
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
    // Fetch trackable data and open form modal
    const trackableData = await services.trackable.getDetails(name);
    console.log(trackableData);
    openFormModal(trackableData);
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

// Initialize the page
// initTrackables();

export {initTrackables}