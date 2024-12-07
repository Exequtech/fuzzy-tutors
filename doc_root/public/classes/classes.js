// classes.js
import { getStudentPage, getClassPage, addNewClassRecord, deleteClassRecord, updateClassRecord, getClassDetails, SessionManager } from '../DataHandler.js';

// DOM Elements
const classesGrid = document.getElementById('classesGrid');
const classModal = document.getElementById('classModal');
const deleteModal = document.getElementById('deleteModal');
const classForm = document.getElementById('classForm');
const searchInput = document.getElementById('searchInput');
const alertMessage = document.getElementById('alertMessage');
const addClassBtn = document.getElementById('addClassBtn');
const availableStudentsList = document.getElementById('availableStudents');
const selectedStudentsList = document.getElementById('selectedStudents');

let currentClassId = null;
let classes = [];
let students = [];

// Initialize
async function init() {
    try {
        // Load initial data
        classes = await getClassPage();
        students = await getStudentPage();
        renderClasses();
    } catch (error) {
        showAlert('Failed to load data: ' + error.message, false);
    }
}

// Add Class Button
addClassBtn.addEventListener('click', () => openModal());

// Search Input
searchInput.addEventListener('input', async (e) => {
    const searchTerm = e.target.value.toLowerCase();
    try {
        // Use the API's filter capability
        const filteredClasses = await getClassPage(1, 10, "asc", "id", {
            name: searchTerm
        });
        renderClasses(filteredClasses);
    } catch (error) {
        showAlert('Search failed: ' + error.message, false);
    }
});

// Form Submit
classForm.addEventListener('submit', (e) => {
    e.preventDefault();
    handleFormSubmit();
});

// Close Modal Buttons
document.querySelectorAll('.close-button').forEach(button => {
    button.addEventListener('click', () => {
        closeModal();
        closeDeleteModal();
    });
});

// Cancel Buttons
document.querySelectorAll('.cancel-button').forEach(button => {
    button.addEventListener('click', () => {
        closeModal();
        closeDeleteModal();
    });
});

// Delete Confirmation
deleteModal.querySelector('.delete-button').addEventListener('click', async () => {
    if (currentClassId) {
        await deleteClass(currentClassId);
        closeDeleteModal();
    }
});

// Student Transfer Buttons
document.getElementById('addStudentBtn').addEventListener('click', () => {
    transferSelectedStudents(availableStudentsList, selectedStudentsList);
});

document.getElementById('removeStudentBtn').addEventListener('click', () => {
    transferSelectedStudents(selectedStudentsList, availableStudentsList);
});

function renderClasses(classesToRender = classes) {
    classesGrid.innerHTML = classesToRender.map(cls => `
        <div class="class-card">
            <div class="class-header">
                <h3 class="class-title">${cls.name}</h3>
                <div class="class-actions">
                    <button onclick="editClass(${cls.id})" class="edit-button">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="confirmDelete(${cls.id})" class="delete-button">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <div class="student-count">
                ${cls.students ? cls.students.length : 0} Students
            </div>
            <div class="student-list">
                ${cls.students ? cls.students.map(student => `
                    <div class="student-item">${student.username}</div>
                `).join('') : ''}
            </div>
        </div>
    `).join('');
}

async function renderStudentLists(selectedStudentIds = []) {
    try {
        // Fetch fresh student data
        const currentStudents = await getStudentPage();
        
        // Render available students
        availableStudentsList.innerHTML = currentStudents
            .filter(student => !selectedStudentIds.includes(student.id))
            .map(student => `
                <div class="student-item" data-id="${student.id}">
                    ${student.username}
                </div>
            `).join('');

        // Render selected students
        selectedStudentsList.innerHTML = currentStudents
            .filter(student => selectedStudentIds.includes(student.id))
            .map(student => `
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
        showAlert('Failed to load student data: ' + error.message, false);
    }
}

function transferSelectedStudents(fromList, toList) {
    const selectedItems = fromList.querySelectorAll('.student-item.selected');
    selectedItems.forEach(item => {
        item.classList.remove('selected');
        toList.appendChild(item);
    });
}

function openModal(classData = null) {
    const modalTitle = document.getElementById('modalTitle');
    const submitButton = classForm.querySelector('.submit-button');
    
    if (classData) {
        modalTitle.textContent = 'Edit Class';
        submitButton.textContent = 'Update Class';
        currentClassId = classData.id;
        
        document.getElementById('className').value = classData.name;
        renderStudentLists(classData.students ? classData.students.map(s => s.id) : []);
    } else {
        modalTitle.textContent = 'Add New Class';
        submitButton.textContent = 'Add Class';
        currentClassId = null;
        classForm.reset();
        renderStudentLists([]);
    }
    
    classModal.classList.add('show');
}

function closeModal() {
    classModal.classList.remove('show');
    currentClassId = null;
    classForm.reset();
}

function closeDeleteModal() {
    deleteModal.classList.remove('show');
    currentClassId = null;
}

async function handleFormSubmit() {
    try {
        const selectedStudentIds = Array.from(selectedStudentsList.children)
            .map(item => parseInt(item.dataset.id));

        const className = document.getElementById('className').value;

        let apiResponse;
        if (currentClassId) {
            // Update existing class
            apiResponse = await updateClassRecord(currentClassId, className, selectedStudentIds);
        } else {
            // Add new class
            apiResponse = await addNewClassRecord(className, selectedStudentIds);
        }

        if (apiResponse.isSuccessful) {
            showAlert(apiResponse.message, true);
            // Refresh the class list
            classes = await getClassPage();
            renderClasses();
            closeModal();
        } else {
            // Handle specific error cases from the API
            if (apiResponse.data && apiResponse.data.invalid_ids) {
                showAlert(`Some student IDs are invalid: ${apiResponse.data.invalid_ids.join(', ')}`, false);
            } else {
                showAlert(apiResponse.message, false);
            }
        }
    } catch (error) {
        showAlert('Failed to save class: ' + error.message, false);
    }
}

async function editClass(id) {
    try {
        // Fetch the latest data for the class
        const classData = classes.find(c => c.id === id);
        if (classData) {
            openModal(classData);
        }
    } catch (error) {
        showAlert('Failed to load class data: ' + error.message, false);
    }
}

function confirmDelete(id) {
    currentClassId = id;
    deleteModal.classList.add('show');
}

async function deleteClass(id) {
    try {
        const apiResponse = await deleteClassRecord(id);
        
        if (apiResponse.isSuccessful) {
            showAlert(apiResponse.message, true);
            // Refresh the class list
            classes = await getClassPage();
            renderClasses();
        } else {
            showAlert(apiResponse.message, false);
        }
    } catch (error) {
        showAlert('Failed to delete class: ' + error.message, false);
    }
}

function showAlert(message, isSuccess) {
    alertMessage.textContent = message;
    alertMessage.className = `alert alert-${isSuccess ? 'success' : 'error'} show`;
    
    setTimeout(() => {
        alertMessage.classList.remove('show');
    }, 3000);
}

// Make functions available globally
window.editClass = editClass;
window.confirmDelete = confirmDelete;

// Initialize the page
init();