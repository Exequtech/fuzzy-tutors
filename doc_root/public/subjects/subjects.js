import { services} from '/dataHandler.js';

// DOM Elements
let subjectsGrid = document.getElementById('subjectsGrid');
let subjectModal = document.getElementById('subjectModal');
let topicModal = document.getElementById('topicModal');
let deleteModal = document.getElementById('deleteModal');
let subjectForm = document.getElementById('subjectForm');
let topicForm = document.getElementById('topicForm');
let searchInput = document.getElementById('searchInput');
let alertMessage = document.getElementById('alertMessage');
let addSubjectBtn = document.getElementById('addSubjectBtn');

let currentSubjectId = null;
let currentTopicId = null;
let subjects = [];

// Initialize
async function initSubjects() {
    try {
        window.confirmDelete = confirmDeleteSubject;

        subjectsGrid = document.getElementById('subjectsGrid');
        subjectModal = document.getElementById('subjectModal');
        topicModal = document.getElementById('topicModal');
        deleteModal = document.getElementById('deleteModal');
        subjectForm = document.getElementById('subjectForm');
        topicForm = document.getElementById('topicForm');
        searchInput = document.getElementById('searchInput');
        alertMessage = document.getElementById('alertMessage');
        addSubjectBtn = document.getElementById('addSubjectBtn');
        initializeEventListeners()
        subjects = await services.subject.getAllPages();
        renderSubjects();
    } catch (error) {
        showAlert('Failed to load data: ' + error.message, false);
    }
}

function initializeEventListeners() {
    addSubjectBtn.addEventListener('click', () => openSubjectModal());

    // Search Input
    searchInput.addEventListener('input', async (e) => {
        const searchTerm = e.target.value.toLowerCase();
        try {
            const filteredSubjects = await services.subject.getAllPages("asc", "id", {
                name: searchTerm
            });
            renderSubjects(filteredSubjects);
        } catch (error) {
            showAlert('Search failed: ' + error.message, false);
        }
    });

    // Form Submissions
    subjectForm.addEventListener('submit', handleSubjectFormSubmit);
    topicForm.addEventListener('submit', handleTopicFormSubmit);

    // Close Modal Buttons
    document.querySelectorAll('.close-button').forEach(button => {
        button.addEventListener('click', () => {
            closeAllModals();
        });
    });

    // Cancel Buttons
    document.querySelectorAll('.cancel-button').forEach(button => {
        button.addEventListener('click', () => {
            closeAllModals();
        });
    });

    // Delete Confirmation
    deleteModal.querySelector('.delete-button').addEventListener('click', async () => {
        if (currentTopicId) {
            await deleteTopic(currentTopicId);
        }
        closeAllModals();
    });
}

function renderSubjects(subjectsToRender = subjects) {
    subjectsGrid.innerHTML = subjectsToRender.map(subject => `
        <div class="subject-card">
            <div class="subject-header">
                <h3 class="subject-title">${subject.name}</h3>
                <div class="subject-actions">
                    <button onclick="addTopicToSubject(${subject.id})" class="edit-button" title="Add Topic">
                        <i class="fas fa-plus"></i>
                    </button>
                    <button onclick="editSubject(${subject.id})" class="edit-button" title="Edit Subject">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="confirmDelete(${subject.id}, true)" class="delete-button" title="Delete Subject">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <p class="topic-description padding">${subject.description ?? ''}</p>
            <div class="topic-count">
                ${subject.topics ? subject.topics.length : 0} Topics
            </div>
            <div class="topics-list">
                ${subject.topics ? subject.topics.map(topic => `
                    <div class="topic-item">
                        <div class="topic-header">
                            <span class="topic-name">${topic.name}</span>
                            <div class="topic-actions">
                                <button onclick="editTopic(${topic.id}, ${subject.id})" class="edit-button" title="Edit Topic">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="confirmDelete(${topic.id}, false)" class="delete-button" title="Delete Topic">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <p class="topic-description">${topic.description}</p>
                    </div>
                `).join('') : ''}
            </div>
        </div>
    `).join('');
}

async function handleSubjectFormSubmit(e) {
    e.preventDefault();
    try {
        const formData = {
            name: document.getElementById('subjectName').value,
            description: document.getElementById('subjectDescription').value || null
        }
        
        // description = description == '' ? null : description;
        
        let apiResponse;
        if (currentSubjectId) {
            apiResponse = await services.topic.update(currentSubjectId, formData);
        } else {
            formData.subjectId = null;
            apiResponse = await services.topic.create(formData); // null parent_id indicates this is a subject
        }

        if (apiResponse.isSuccessful) {
            showAlert(apiResponse.message, true);
            subjects = await services.subject.getAllPages();
            renderSubjects();
            closeAllModals();
        } else {
            showAlert(apiResponse.message, false);
        }
    } catch (error) {
        showAlert('Failed to save subject: ' + error.message, false);
    }
}

async function handleTopicFormSubmit(e) {
    e.preventDefault();
    try {
        const formData = {
            name: document.getElementById('topicName').value,
            description: document.getElementById('topicDescription').value
        }
        
        let apiResponse;
        if (currentTopicId) {
            apiResponse = await services.topic.update(currentTopicId, formData);
        } else {
            formData.subjectId = currentSubjectId;
            apiResponse = await services.topic.create(formData);
        }

        if (apiResponse.isSuccessful) {
            showAlert(apiResponse.message, true);
            subjects = await services.subject.getAllPages();
            renderSubjects();
            closeAllModals();
        } else {
            showAlert(apiResponse.message, false);
        }
    } catch (error) {
        showAlert('Failed to save topic: ' + error.message, false);
    }
}

function openSubjectModal(subjectData = null) {
    const modalTitle = document.getElementById('modalTitle');
    const submitButton = subjectForm.querySelector('.submit-button');
    
    if (subjectData) {
        modalTitle.textContent = 'Edit Subject';
        submitButton.textContent = 'Update Subject';
        currentSubjectId = subjectData.id;
        document.getElementById('subjectName').value = subjectData.name;
        document.getElementById('subjectDescription').value = subjectData.description;
    } else {
        modalTitle.textContent = 'Add New Subject';
        submitButton.textContent = 'Add Subject';
        currentSubjectId = null;
        subjectForm.reset();
    }
    
    subjectModal.classList.add('show');
}

function openTopicModal(topicData = null, subjectId = null) {
    const modalTitle = document.getElementById('topicModalTitle');
    const submitButton = topicForm.querySelector('.submit-button');
    
    currentSubjectId = subjectId;
    
    if (topicData) {
        modalTitle.textContent = 'Edit Topic';
        submitButton.textContent = 'Update Topic';
        currentTopicId = topicData.id;
        document.getElementById('topicName').value = topicData.name;
        document.getElementById('topicDescription').value = topicData.description;
    } else {
        modalTitle.textContent = 'Add New Topic';
        submitButton.textContent = 'Add Topic';
        currentTopicId = null;
        topicForm.reset();
    }
    
    topicModal.classList.add('show');
}

function editSubject(subjectId) {
    const subject = subjects.find(s => s.id === subjectId);
    if (subject) {
        openSubjectModal(subject);
    }
}

function addTopicToSubject(subjectId) {
    openTopicModal(null, subjectId);
}

function editTopic(topicId, subjectId) {
    const subject = subjects.find(s => s.id === subjectId);
    if (subject) {
        const topic = subject.topics.find(t => t.id === topicId);
        if (topic) {
            openTopicModal(topic, subjectId);
        }
    }
}

async function deleteTopic(topicId) {
    try {
        const response = await services.topic.delete(topicId);
        if (response.isSuccessful) {
            showAlert('Topic deleted successfully', true);
            subjects = await services.subject.getAllPages();
            renderSubjects();
        } else {
            showAlert(response.message, false);
        }
    } catch (error) {
        showAlert('Failed to delete topic: ' + error.message, false);
    }
}

function confirmDeleteSubject(id, isSubject) {
    currentTopicId = id;
    const modalMessage = deleteModal.querySelector('p');
    modalMessage.textContent = isSubject 
        ? 'Are you sure you want to delete this subject? All associated topics will also be deleted.'
        : 'Are you sure you want to delete this topic?';
    deleteModal.classList.add('show');
}

function closeAllModals() {
    subjectModal.classList.remove('show');
    topicModal.classList.remove('show');
    deleteModal.classList.remove('show');
    currentSubjectId = null;
    currentTopicId = null;
    subjectForm.reset();
    topicForm.reset();
}

function showAlert(message, isSuccess) {
    alertMessage.textContent = message;
    alertMessage.className = `alert alert-${isSuccess ? 'success' : 'error'} show`;
    
    setTimeout(() => {
        alertMessage.classList.remove('show');
    }, 3000);
}

// Make functions available globally
window.editSubject = editSubject;
window.addTopicToSubject = addTopicToSubject;
// window.confirmDelete = confirmDelete;
window.editTopic = editTopic;
window.deleteTopic = deleteTopic;

// Initialize the page
// initSubjects();

export {
    initSubjects
}