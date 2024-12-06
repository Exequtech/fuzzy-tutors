// classes.js
import { getStudentPage, getClassPage, SessionManager } from '../DataHandler.js';

// Simulated class data storage (replace with actual API calls)
let classes = [];
let students = [];

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

// Initialize
async function init() {
    students = await getStudentPage();
    renderClasses();
}

// Add Class Button
addClassBtn.addEventListener('click', () => openModal());

// Search Input
searchInput.addEventListener('input', (e) => {
    const searchTerm = e.target.value.toLowerCase();
    const filteredClasses = classes.filter(cls => 
        cls.name.toLowerCase().includes(searchTerm) 
    );
    renderClasses(filteredClasses);
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
deleteModal.querySelector('.delete-button').addEventListener('click', () => {
    if (currentClassId) {
        deleteClass(currentClassId);
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
                ${cls.students.length} Students
            </div>
            <div class="student-list">
                ${cls.students.map(student => `
                    <div class="student-item">${student.username}</div>
                `).join('')}
            </div>
        </div>
    `).join('');
}

function renderStudentLists(selectedStudentIds = []) {
    // Render available students
    availableStudentsList.innerHTML = students
        .filter(student => !selectedStudentIds.includes(student.id))
        .map(student => `
            <div class="student-item" data-id="${student.id}">
                ${student.username}
            </div>
        `).join('');

    // Render selected students
    selectedStudentsList.innerHTML = students
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
        renderStudentLists(classData.students.map(s => s.id));
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
    const selectedStudentIds = Array.from(selectedStudentsList.children)
        .map(item => parseInt(item.dataset.id));

    const classData = {
        id: currentClassId || Date.now(), // Replace with actual API-generated ID
        name: document.getElementById('className').value,
        students: students.filter(student => selectedStudentIds.includes(student.id))
    };

    if (currentClassId) {
        // Update existing class
        const index = classes.findIndex(c => c.id === currentClassId);
        classes[index] = classData;
        showAlert('Class updated successfully', true);
    } else {
        // Add new class
        classes.push(classData);
        showAlert('Class added successfully', true);
    }

    renderClasses();
    closeModal();
}

function editClass(id) {
    const classData = classes.find(c => c.id === id);
    if (classData) {
        openModal(classData);
    }
}

function confirmDelete(id) {
    currentClassId = id;
    deleteModal.classList.add('show');
}

function deleteClass(id) {
    classes = classes.filter(c => c.id !== id);
    showAlert('Class deleted successfully', true);
    renderClasses();
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