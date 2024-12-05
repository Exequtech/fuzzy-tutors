import { getStudentPage, deleteStudentRecord, addNewStudentRecord, updateStudentRecord, SessionManager } from './DataHandler.js';
// // Sample initial data
// let students = [
//     { id: 1, username: 'John Doe', email: 'john@example.com', status: 'Authorized' },
//     { id: 2, username: 'Jane Smith', email: 'jane@example.com', status: 'Authorized' },
//     { id: 3, username: 'Mike Johnson', email: 'mike@example.com', status: 'Pending' }
// ];

let students = await getStudentPage();

window.confirmDelete = confirmDelete;
window.editStudent = editStudent;

// DOM Elements
const studentsTable = document.getElementById('studentsList');
const studentModal = document.getElementById('studentModal');
const deleteModal = document.getElementById('deleteModal');
const studentForm = document.getElementById('studentForm');
const searchInput = document.getElementById('searchInput');
const alertMessage = document.getElementById('alertMessage');
const addStudentBtn = document.getElementById('addStudentBtn');

let currentStudentId = null;

// Initialize the page
function init() {
    renderStudents();
    // setupEventListeners();
}

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
// }

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
        document.getElementById('status').value = student.authorized ? 'Authorized' : 'Pending';
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
        let response = await updateStudentRecord(currentStudentId, formData.username, formData.email, formData.authorized);
        showAlert(response.message, response.isSuccessful);
    } else {
        // Add
        await addNewStudentRecord(formData.username, formData.email, formData.authorized)
        showAlert('Student added successfully!', 'success');
    }

    renderStudents(await getStudentPage());
    closeModal();
}

function editStudent(id) {
    const student = students.find(s => s.id === id);
    if (student) {
        openModal(student);
    }
}

function confirmDelete(id) {
    currentStudentId = id;
    openDeleteModal();
}

async function deleteStudent(id) {
    deleteStudentRecord(id);
    renderStudents(await getStudentPage());
    showAlert('Student deleted successfully!', 'success');
}

// Utility Functions
function showAlert(message, type) {
    alertMessage.textContent = message;
    alertMessage.classusername = `alert alert-${type} show`;
    
    setTimeout(() => {
        alertMessage.classList.remove('show');
    }, 3000);
}

// Initialize the page when DOM is loaded
// document.addEventListener('DOMContentLoaded', init);
init();