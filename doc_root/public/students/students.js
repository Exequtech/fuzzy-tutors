import { services  } from '../dataHandler.js';

let students = null;

window.editStudent = editStudent;

// DOM Elements
let studentsTable = document.getElementById('studentsList');
let studentModal = document.getElementById('studentModal');
let deleteModal = document.getElementById('deleteModal');
let studentForm = document.getElementById('studentForm');
let searchInput = document.getElementById('searchInput');
let alertMessage = document.getElementById('alertMessage');
let addStudentBtn = document.getElementById('addStudentBtn');

let currentStudentId = null;

async function initStudents() {
    window.confirmDelete = confirmDeleteStudent;

    students = await services.student.getAllPages();

    studentsTable = document.getElementById('studentsList');
    studentModal = document.getElementById('studentModal');
    deleteModal = document.getElementById('deleteModal');
    studentForm = document.getElementById('studentForm');
    searchInput = document.getElementById('searchInput');
    alertMessage = document.getElementById('alertMessage');
    addStudentBtn = document.getElementById('addStudentBtn');
    initEventListeners()
    renderStudents();
}

function initEventListeners() {
    /* ============================= Setup Event Listeners ================================= */
    // Add Student Button
    addStudentBtn.addEventListener('click', () => {
        openModal();
    });

    // Search Input
    searchInput.addEventListener('input', (e) => {
        const searchTerm = e.target.value.toLowerCase();
        const filteredStudents = students.filter(student => 
            student.username.toLowerCase().includes(searchTerm) ||
            student.email.toLowerCase().includes(searchTerm)
        );
        renderStudents(filteredStudents);
    });

    // Form Submit
    studentForm.addEventListener('submit', (e) => {
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
        if (currentStudentId) {
            deleteStudent(currentStudentId);
            closeDeleteModal();
        }
    });
}


// Render Students Table
function renderStudents(studentsToRender = students) {
    studentsTable.innerHTML = studentsToRender.map(student => `
        <tr>
            <td>${student.username}</td>
            <td>${student.email}</td>
            <td>
                <span class="status-badge status-${student.authorized ? 'authorized' : 'pending'}">
                    ${student.authorized ? 'Authorized' : 'Pending'}
                </span>
            </td>
            <td class="action-buttons">
                <button onclick="editStudent(${student.id})" class="edit-button">
                    <i class="fas fa-edit"></i>
                </button>
                <button onclick="confirmDelete(${student.id})" class="delete-button">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

// Modal Functions
function openModal(student = null) {
    const modalTitle = document.getElementById('modalTitle');
    const submitButton = studentForm.querySelector('.submit-button');
    
    if (student) {
        modalTitle.textContent = 'Edit Student';
        submitButton.textContent = 'Update Student';
        currentStudentId = student.id;
        
        // Fill form with student data
        document.getElementById('username').value = student.username;
        document.getElementById('email').value = student.email;
        document.getElementById('status').value = student.authorized ? 'true' : 'false';
        console.log(student);
    } else {
        modalTitle.textContent = 'Add New Student';
        submitButton.textContent = 'Add Student';
        currentStudentId = null;
        studentForm.reset();
    }
    
    studentModal.classList.add('show');
}

function closeModal() {
    studentModal.classList.remove('show');
    currentStudentId = null;
    studentForm.reset();
}

function openDeleteModal() {
    deleteModal.classList.add('show');
}

function closeDeleteModal() {
    deleteModal.classList.remove('show');
    currentStudentId = null;
}

// CRUD Operations
async function handleFormSubmit() {
    const formData = {
        username: document.getElementById('username').value,
        email: document.getElementById('email').value,
        authorized: document.getElementById('status').value == "true"
    };

    if (currentStudentId) {
        // Update
        let apiResponse = await services.student.update(currentStudentId, formData);
        showAlert(apiResponse.message, apiResponse.isSuccessful);
    } else {
        // Add
        let apiResponse = await services.student.create(formData);
        showAlert(apiResponse.message, apiResponse.isSuccessful);
    }

    renderStudents(await services.student.getAllPages());
    closeModal();
}

function editStudent(id) {
    const student = students.find(s => s.id === id);
    if (student) {
        openModal(student);
    }
}

function confirmDeleteStudent(id) {
    currentStudentId = id;
    openDeleteModal();
}

async function deleteStudent(id) {
    let apiResponse = await services.student.delete(id);
    showAlert(apiResponse.message, apiResponse.isSuccessful);
    renderStudents(await services.student.getAllPages());
}

// Utility Functions
function showAlert(message, isSuccessful) {
    let type = 'error';
    if (isSuccessful) {
        type = 'success';
    }

    alertMessage.textContent = message;
    alertMessage.className = `alert alert-${type} show`;

    
    setTimeout(() => {
        alertMessage.classList.remove('show');
    }, 3000);
}

// initStudents();

export {initStudents}