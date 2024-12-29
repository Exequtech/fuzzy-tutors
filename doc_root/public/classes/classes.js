// classes.js
import { services } from '../dataHandler.js';

// DOM Elements
let classesGrid = null;
let classModal  = null;
let deleteModal = null;
let classForm   = null;
let searchInput = null;
let alertMessage= null;
let addClassBtn = null;

let currentClassId = null;
let classes = [];
let selectedStudents = new Set();
let searchTimeout = null;

// Initialize
async function initClasses() {
    try {
        classesGrid = document.getElementById('classesGrid');
        classModal  = document.getElementById('classModal');
        deleteModal = document.getElementById('deleteModal');
        classForm   = document.getElementById('classForm');
        searchInput = document.getElementById('searchInput');
        alertMessage= document.getElementById('alertMessage');
        addClassBtn = document.getElementById('addClassBtn');
        // Load initial data
        classes = await services.class.getPage();
        initEventListeners();
        renderClasses();
    } catch (error) {
        showAlert('Failed to load data: ' + error.message, false);
    }
}

function initEventListeners() {
    // Add Class Button
    addClassBtn.addEventListener('click', () => openModal());

    // Search Input
    searchInput.addEventListener('input', async (e) => {
        const searchTerm = e.target.value.toLowerCase();
        try {
            // Use the API's filter capability
            const filteredClasses = await services.class.getPage(1, 10, "asc", "id", {
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

}

function setupMemberManagement() {
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
                <button onclick="addClassMember(${student.id}, '${student.username}', '${student.email}')" 
                        class="action-button add" title="Add to class" type="button">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        `).join('');
    }
    
    searchResults.classList.remove('hidden');
}

function renderClassMembers() {
    const membersList = document.getElementById('classMembersList');
    const members = Array.from(selectedStudents.values());
    
    membersList.innerHTML = members.map(member => `
        <div class="member-item">
            <div class="student-info">
                <div class="student-name">${member.username}</div>
            </div>
            <button onclick="removeClassMember(${member.id})" 
                    class="action-button" title="Remove from class">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `).join('');
}

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
                    <div class="student-item">
                        <div class="student-name">${student.name}</div>
                    </div>
                `).join('') : ''}
            </div>
        </div>
    `).join('');
}

function openModal(classData = null) {
    const modalTitle = document.getElementById('modalTitle');
    const submitButton = classForm.querySelector('.submit-button');
    
    if (classData) {
        modalTitle.textContent = 'Edit Class';
        submitButton.textContent = 'Update Class';
        currentClassId = classData.id;
        document.getElementById('className').value = classData.name;
        selectedStudents = new Set(classData.students.map(student => ({
            id: student.id,
            username: student.name
        })));
    } else {
        modalTitle.textContent = 'Add New Class';
        submitButton.textContent = 'Add Class';
        currentClassId = null;
        classForm.reset();
        selectedStudents = new Set();
    }
    
    renderClassMembers();
    setupMemberManagement();
    classModal.classList.add('show');
}

function addClassMember(id, username, email) {
    selectedStudents.add({ id, username, email });
    renderClassMembers();
    document.getElementById('searchResults').classList.add('hidden');
    document.getElementById('studentSearchInput').value = '';
}

function removeClassMember(id) {
    selectedStudents.delete(Array.from(selectedStudents).find(student => student.id === id));
    renderClassMembers();
}

function closeModal() {
    classModal.classList.remove('show');
    currentClassId = null;
    classForm.reset();
    selectedStudents = new Set();
}

function closeDeleteModal() {
    deleteModal.classList.remove('show');
    currentClassId = null;
}

async function handleFormSubmit() {
    try {
        const formData = {
            students: Array.from(selectedStudents).map(student => student.id),
            name: document.getElementById('className').value
        }

        let apiResponse;
        if (currentClassId) {
            apiResponse = await services.class.update(currentClassId, formData);
        } else {
            apiResponse = await services.class.create(formData);
        }

        if (apiResponse.isSuccessful) {
            showAlert(apiResponse.message, true);
            // Refresh the class list
            classes = await services.class.getPage();
            renderClasses();
            closeModal();
        } else {
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
        const classData = await services.class.getDetails(id);
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
        const apiResponse = await services.class.delete(id);
        
        if (apiResponse.isSuccessful) {
            showAlert(apiResponse.message, true);
            // Refresh the class list
            classes = await services.class.getPage();
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
window.addClassMember = addClassMember;
window.removeClassMember = removeClassMember;

// Initialize the page
// initClasses();

export {initClasses}