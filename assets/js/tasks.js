/**
 * Task Management Module
 * Handles Kanban board, task creation, drag-drop functionality
 */

// Task state
let currentView = 'kanban';
let taskLists = [];
let currentListId = null;
let tasks = [];
let users = [];
let draggedTask = null;
let selectedTask = null;
let filters = {
    status: '',
    priority: '',
    assignee: ''
};
let searchQuery = '';

// API endpoints
const API_BASE = window.location.pathname.split('/').slice(0, -1).join('/') + '/api';

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', function() {
    initializeTasks();
    setupEventListeners();
    loadTaskLists();
    loadUsers();
});

// Initialize tasks
function initializeTasks() {
    // Load initial data
    loadTaskLists();
}

// Setup event listeners
function setupEventListeners() {
    // View switcher
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentView = this.dataset.view;
            switchView();
        });
    });

    // Board selector
    document.getElementById('boardSelector').addEventListener('change', function() {
        currentListId = this.value;
        loadTasks();
    });

    // New board button
    document.getElementById('newBoardBtn').addEventListener('click', createNewBoard);

    // Create task button
    document.getElementById('createTaskBtn').addEventListener('click', openCreateTaskModal);

    // Task form submission
    document.getElementById('taskForm').addEventListener('submit', handleTaskSubmit);

    // Search input
    document.getElementById('taskSearch').addEventListener('input', function() {
        searchQuery = this.value.toLowerCase();
        renderTasks();
    });

    // Filter button
    document.getElementById('filterBtn').addEventListener('click', toggleFilterDropdown);

    // Sort button
    document.getElementById('sortBtn').addEventListener('click', showSortOptions);

    // Priority selector
    document.getElementById('taskPriority').addEventListener('change', function() {
        updatePriorityColor(this);
    });

    // Recurring checkbox
    document.getElementById('taskRecurring').addEventListener('change', function() {
        document.getElementById('recurringOptions').style.display = this.checked ? 'block' : 'none';
    });

    // Tag input
    document.getElementById('taskTags').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            addTag(this.value);
            this.value = '';
        }
    });

    // Assignee search
    setupAssigneeSearch();

    // Comment form
    document.getElementById('addCommentBtn')?.addEventListener('click', addComment);

    // Time tracking
    document.getElementById('startTimerBtn')?.addEventListener('click', startTimer);

    // Close modals on outside click
    window.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            e.target.style.display = 'none';
        }
    });
}

// Load task lists
async function loadTaskLists() {
    try {
        const response = await fetch(`${API_BASE}/task-lists.php`, {
            credentials: 'include'
        });
        const data = await response.json();

        if (data.success && data.data) {
            taskLists = data.data;
            updateBoardSelector();

            if (taskLists.length > 0 && !currentListId) {
                currentListId = taskLists[0].id;
                loadTasks();
            }
        }
    } catch (error) {
        console.error('Error loading task lists:', error);
        showNotification('Errore nel caricamento delle liste', 'error');
    }
}

// Load tasks
async function loadTasks() {
    if (!currentListId) return;

    try {
        const response = await fetch(`${API_BASE}/task-lists.php/${currentListId}/tasks?group_by_status=true`, {
            credentials: 'include'
        });
        const data = await response.json();

        if (data.success && data.data) {
            tasks = data.data;
            updateStatistics();
            renderTasks();
        }
    } catch (error) {
        console.error('Error loading tasks:', error);
        showNotification('Errore nel caricamento delle attività', 'error');
    }
}

// Load users for assignment
async function loadUsers() {
    try {
        const response = await fetch(`${API_BASE}/users.php`, {
            credentials: 'include'
        });
        const data = await response.json();

        if (data.success && data.data) {
            users = data.data;
            updateAssigneeOptions();
        }
    } catch (error) {
        console.error('Error loading users:', error);
    }
}

// Render tasks based on current view
function renderTasks() {
    if (currentView === 'kanban') {
        renderKanbanBoard();
    } else {
        renderListView();
    }
}

// Render Kanban board
function renderKanbanBoard() {
    const container = document.getElementById('boardContainer');
    container.className = 'board-container kanban-view';

    // Get current list configuration
    const currentList = taskLists.find(l => l.id == currentListId);
    if (!currentList) {
        container.innerHTML = '<div class="empty-state">Seleziona o crea un board</div>';
        return;
    }

    // Default workflow states if not configured
    const workflowStates = currentList.workflow_states || [
        { id: 'backlog', name: 'Backlog', color: '#6B7280' },
        { id: 'todo', name: 'Da Fare', color: '#3B82F6' },
        { id: 'in_progress', name: 'In Corso', color: '#F59E0B' },
        { id: 'review', name: 'In Revisione', color: '#8B5CF6' },
        { id: 'done', name: 'Completato', color: '#10B981' }
    ];

    let html = '<div class="kanban-board">';

    workflowStates.forEach(state => {
        const columnTasks = getTasksByStatus(state.id);

        html += `
            <div class="kanban-column" data-status="${state.id}">
                <div class="column-header" style="border-color: ${state.color};">
                    <h3 class="column-title">${escapeHtml(state.name)}</h3>
                    <span class="task-count">${columnTasks.length}</span>
                    <button class="btn-icon" onclick="quickAddTask('${state.id}')">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="16" height="16">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                    </button>
                </div>
                <div class="column-content"
                     ondrop="handleTaskDrop(event)"
                     ondragover="handleTaskDragOver(event)"
                     data-status="${state.id}">
        `;

        columnTasks.forEach(task => {
            html += renderTaskCard(task);
        });

        html += `
                </div>
            </div>
        `;
    });

    html += '</div>';
    container.innerHTML = html;

    // Make cards draggable
    document.querySelectorAll('.task-card').forEach(card => {
        card.draggable = true;
        card.addEventListener('dragstart', handleTaskDragStart);
        card.addEventListener('dragend', handleTaskDragEnd);
    });
}

// Render list view
function renderListView() {
    const container = document.getElementById('listContainer');
    container.style.display = 'block';
    document.getElementById('boardContainer').style.display = 'none';

    let html = '<div class="task-list-view">';
    html += '<table class="task-table">';
    html += `
        <thead>
            <tr>
                <th>Titolo</th>
                <th>Stato</th>
                <th>Priorità</th>
                <th>Assegnatari</th>
                <th>Scadenza</th>
                <th>Azioni</th>
            </tr>
        </thead>
        <tbody>
    `;

    const allTasks = getAllTasks();
    allTasks.forEach(task => {
        const priorityClass = `priority-${task.priority}`;
        const statusClass = `status-${task.status}`;

        html += `
            <tr class="task-row" onclick="showTaskDetails(${task.id})">
                <td class="task-title-cell">
                    <div class="task-title">${escapeHtml(task.title)}</div>
                    ${task.tags ? `<div class="task-tags">${renderTags(task.tags)}</div>` : ''}
                </td>
                <td><span class="status-badge ${statusClass}">${getStatusName(task.status)}</span></td>
                <td><span class="priority-badge ${priorityClass}">${getPriorityName(task.priority)}</span></td>
                <td>${renderAssigneeAvatars(task.assignees)}</td>
                <td>${task.due_date ? formatDate(new Date(task.due_date)) : '-'}</td>
                <td class="task-actions">
                    <button class="btn-icon" onclick="editTask(${task.id}); event.stopPropagation();">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="16" height="16">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                        </svg>
                    </button>
                    <button class="btn-icon" onclick="deleteTask(${task.id}); event.stopPropagation();">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="16" height="16">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                        </svg>
                    </button>
                </td>
            </tr>
        `;
    });

    html += '</tbody></table></div>';
    container.innerHTML = html;
}

// Render task card
function renderTaskCard(task) {
    const priorityClass = `priority-${task.priority}`;
    const isOverdue = task.due_date && new Date(task.due_date) < new Date() && task.status !== 'done';

    return `
        <div class="task-card ${priorityClass} ${isOverdue ? 'overdue' : ''}"
             data-task-id="${task.id}"
             onclick="showTaskDetails(${task.id})">
            <div class="task-card-header">
                <span class="task-priority-indicator"></span>
                <div class="task-card-actions">
                    <button class="btn-icon" onclick="quickEditTask(${task.id}); event.stopPropagation();">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="14" height="14">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                        </svg>
                    </button>
                </div>
            </div>
            <div class="task-card-title">${escapeHtml(task.title)}</div>
            ${task.description ? `<div class="task-card-description">${escapeHtml(task.description.substring(0, 100))}...</div>` : ''}

            <div class="task-card-meta">
                ${task.due_date ? `
                    <div class="task-due-date ${isOverdue ? 'overdue' : ''}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="14" height="14">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                        </svg>
                        ${formatDate(new Date(task.due_date))}
                    </div>
                ` : ''}

                ${task.estimated_hours ? `
                    <div class="task-hours">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="14" height="14">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        ${task.estimated_hours}h
                    </div>
                ` : ''}
            </div>

            ${task.tags && task.tags.length > 0 ? `
                <div class="task-card-tags">
                    ${task.tags.map(tag => `<span class="task-tag">${escapeHtml(tag)}</span>`).join('')}
                </div>
            ` : ''}

            ${task.assignees && task.assignees.length > 0 ? `
                <div class="task-card-assignees">
                    ${renderAssigneeAvatars(task.assignees)}
                </div>
            ` : ''}

            ${task.subtask_count > 0 ? `
                <div class="task-subtasks">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="14" height="14">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    ${task.completed_subtasks || 0}/${task.subtask_count}
                </div>
            ` : ''}

            ${task.comment_count > 0 ? `
                <div class="task-comments">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="14" height="14">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                    </svg>
                    ${task.comment_count}
                </div>
            ` : ''}
        </div>
    `;
}

// Task form handling
function handleTaskSubmit(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const taskData = Object.fromEntries(formData);

    // Add tags
    taskData.tags = Array.from(document.querySelectorAll('#tagList .tag')).map(tag => tag.textContent.replace('×', '').trim());

    // Add assignees
    taskData.assignees = Array.from(document.querySelectorAll('#assigneeList .assignee-chip')).map(chip => chip.dataset.userId);

    if (selectedTask) {
        updateTask(selectedTask.id, taskData);
    } else {
        createTask(taskData);
    }
}

async function createTask(taskData) {
    try {
        const response = await fetch(`${API_BASE}/tasks.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({
                ...taskData,
                task_list_id: currentListId
            })
        });

        const data = await response.json();

        if (data.success) {
            closeTaskModal();
            loadTasks();
            showNotification('Attività creata con successo', 'success');
        } else {
            showNotification(data.message || 'Errore nella creazione dell\'attività', 'error');
        }
    } catch (error) {
        console.error('Error creating task:', error);
        showNotification('Errore nella creazione dell\'attività', 'error');
    }
}

async function updateTask(taskId, taskData) {
    try {
        const response = await fetch(`${API_BASE}/tasks.php/${taskId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify(taskData)
        });

        const data = await response.json();

        if (data.success) {
            closeTaskModal();
            loadTasks();
            showNotification('Attività aggiornata con successo', 'success');
        } else {
            showNotification(data.message || 'Errore nell\'aggiornamento dell\'attività', 'error');
        }
    } catch (error) {
        console.error('Error updating task:', error);
        showNotification('Errore nell\'aggiornamento dell\'attività', 'error');
    }
}

async function deleteTask(taskId) {
    if (!confirm('Sei sicuro di voler eliminare questa attività?')) return;

    try {
        const response = await fetch(`${API_BASE}/tasks.php/${taskId}`, {
            method: 'DELETE',
            credentials: 'include'
        });

        const data = await response.json();

        if (data.success) {
            loadTasks();
            showNotification('Attività eliminata con successo', 'success');
        } else {
            showNotification(data.message || 'Errore nell\'eliminazione dell\'attività', 'error');
        }
    } catch (error) {
        console.error('Error deleting task:', error);
        showNotification('Errore nell\'eliminazione dell\'attività', 'error');
    }
}

// Drag and drop handlers
function handleTaskDragStart(e) {
    draggedTask = tasks[e.target.dataset.taskId];
    e.target.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
}

function handleTaskDragEnd(e) {
    e.target.classList.remove('dragging');
    draggedTask = null;
}

function handleTaskDragOver(e) {
    if (e.preventDefault) {
        e.preventDefault();
    }
    e.dataTransfer.dropEffect = 'move';

    // Visual feedback
    const column = e.target.closest('.column-content');
    if (column) {
        column.classList.add('drag-over');
    }

    return false;
}

async function handleTaskDrop(e) {
    if (e.stopPropagation) {
        e.stopPropagation();
    }

    const column = e.target.closest('.column-content');
    if (!column) return;

    column.classList.remove('drag-over');

    const newStatus = column.dataset.status;
    if (!draggedTask || draggedTask.status === newStatus) return;

    // Find position in column
    const afterElement = getDragAfterElement(column, e.clientY);
    let newPosition = 0;

    if (afterElement) {
        const afterTask = tasks[afterElement.dataset.taskId];
        newPosition = afterTask.position;
    } else {
        // Get last position in column
        const columnTasks = getTasksByStatus(newStatus);
        newPosition = columnTasks.length > 0 ? Math.max(...columnTasks.map(t => t.position)) + 1 : 0;
    }

    // Update task via API
    try {
        const response = await fetch(`${API_BASE}/tasks.php/${draggedTask.id}/move`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({
                status: newStatus,
                position: newPosition
            })
        });

        const data = await response.json();

        if (data.success) {
            loadTasks();
            showNotification('Attività spostata con successo', 'success');
        } else {
            showNotification(data.message || 'Errore nello spostamento dell\'attività', 'error');
        }
    } catch (error) {
        console.error('Error moving task:', error);
        showNotification('Errore nello spostamento dell\'attività', 'error');
    }

    return false;
}

function getDragAfterElement(container, y) {
    const draggableElements = [...container.querySelectorAll('.task-card:not(.dragging)')];

    return draggableElements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;

        if (offset < 0 && offset > closest.offset) {
            return { offset: offset, element: child };
        } else {
            return closest;
        }
    }, { offset: Number.NEGATIVE_INFINITY }).element;
}

// Modal functions
function openCreateTaskModal() {
    selectedTask = null;
    document.getElementById('modalTitle').textContent = 'Nuova Attività';
    document.getElementById('taskForm').reset();
    document.getElementById('tagList').innerHTML = '';
    document.getElementById('assigneeList').innerHTML = '';
    document.getElementById('taskModal').style.display = 'block';
}

function closeTaskModal() {
    document.getElementById('taskModal').style.display = 'none';
    selectedTask = null;
}

function showTaskDetails(taskId) {
    const task = findTaskById(taskId);
    if (!task) return;

    selectedTask = task;

    // Populate detail modal
    document.getElementById('detailTaskTitle').textContent = task.title;
    document.getElementById('detailTaskDescription').textContent = task.description || 'Nessuna descrizione';
    document.getElementById('detailTaskStatus').textContent = getStatusName(task.status);
    document.getElementById('detailTaskStatus').className = `status-badge status-${task.status}`;
    document.getElementById('detailTaskPriority').textContent = getPriorityName(task.priority);
    document.getElementById('detailTaskPriority').className = `priority-badge priority-${task.priority}`;

    document.getElementById('detailDueDate').textContent = task.due_date ? formatDate(new Date(task.due_date)) : 'Non impostata';
    document.getElementById('detailEstimatedHours').textContent = task.estimated_hours ? `${task.estimated_hours}h` : 'Non stimate';
    document.getElementById('detailLoggedHours').textContent = task.logged_hours ? `${task.logged_hours}h` : '0h';

    // Assignees
    const assigneesContainer = document.getElementById('detailAssignees');
    assigneesContainer.innerHTML = task.assignees ? renderAssigneeAvatars(task.assignees) : 'Nessuno';

    // Tags
    const tagsContainer = document.getElementById('detailTags');
    tagsContainer.innerHTML = task.tags ? renderTags(task.tags) : 'Nessuno';

    // Load comments
    loadTaskComments(taskId);

    // Load time entries
    loadTimeEntries(taskId);

    document.getElementById('taskDetailModal').style.display = 'block';
}

function closeTaskDetailModal() {
    document.getElementById('taskDetailModal').style.display = 'none';
    selectedTask = null;
}

async function loadTaskComments(taskId) {
    try {
        const response = await fetch(`${API_BASE}/tasks.php/${taskId}/comments`, {
            credentials: 'include'
        });
        const data = await response.json();

        if (data.success && data.data) {
            renderComments(data.data);
        }
    } catch (error) {
        console.error('Error loading comments:', error);
    }
}

function renderComments(comments) {
    const container = document.getElementById('taskComments');
    if (!comments || comments.length === 0) {
        container.innerHTML = '<p class="no-comments">Nessun commento</p>';
        return;
    }

    let html = '';
    comments.forEach(comment => {
        html += `
            <div class="comment">
                <div class="comment-header">
                    <span class="comment-author">${escapeHtml(comment.user_name)}</span>
                    <span class="comment-date">${formatDateTime(new Date(comment.created_at))}</span>
                </div>
                <div class="comment-body">${escapeHtml(comment.comment)}</div>
            </div>
        `;
    });

    container.innerHTML = html;
}

async function addComment() {
    const commentText = document.getElementById('newComment').value.trim();
    if (!commentText || !selectedTask) return;

    try {
        const response = await fetch(`${API_BASE}/tasks.php/${selectedTask.id}/comments`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({
                comment: commentText
            })
        });

        const data = await response.json();

        if (data.success) {
            document.getElementById('newComment').value = '';
            loadTaskComments(selectedTask.id);
            showNotification('Commento aggiunto', 'success');
        } else {
            showNotification(data.message || 'Errore nell\'aggiunta del commento', 'error');
        }
    } catch (error) {
        console.error('Error adding comment:', error);
        showNotification('Errore nell\'aggiunta del commento', 'error');
    }
}

async function loadTimeEntries(taskId) {
    try {
        const response = await fetch(`${API_BASE}/tasks.php/${taskId}/time-logs`, {
            credentials: 'include'
        });
        const data = await response.json();

        if (data.success && data.data) {
            renderTimeEntries(data.data);
        }
    } catch (error) {
        console.error('Error loading time entries:', error);
    }
}

function renderTimeEntries(entries) {
    const container = document.getElementById('timeEntries');
    if (!entries || entries.length === 0) {
        container.innerHTML = '<p class="no-entries">Nessuna registrazione</p>';
        return;
    }

    let html = '<div class="time-entry-list">';
    entries.forEach(entry => {
        const duration = calculateDuration(new Date(entry.started_at), new Date(entry.ended_at));
        html += `
            <div class="time-entry">
                <span class="entry-date">${formatDate(new Date(entry.started_at))}</span>
                <span class="entry-duration">${duration}</span>
                ${entry.description ? `<span class="entry-description">${escapeHtml(entry.description)}</span>` : ''}
            </div>
        `;
    });
    html += '</div>';

    container.innerHTML = html;
}

// Helper functions
function switchView() {
    if (currentView === 'kanban') {
        document.getElementById('boardContainer').style.display = 'block';
        document.getElementById('listContainer').style.display = 'none';
        renderKanbanBoard();
    } else {
        document.getElementById('boardContainer').style.display = 'none';
        document.getElementById('listContainer').style.display = 'block';
        renderListView();
    }
}

function updateBoardSelector() {
    const selector = document.getElementById('boardSelector');
    selector.innerHTML = '';

    taskLists.forEach(list => {
        const option = document.createElement('option');
        option.value = list.id;
        option.textContent = list.name;
        selector.appendChild(option);
    });

    if (currentListId) {
        selector.value = currentListId;
    }

    // Update task list dropdown in form
    const taskListSelect = document.getElementById('taskList');
    taskListSelect.innerHTML = '';

    taskLists.forEach(list => {
        const option = document.createElement('option');
        option.value = list.id;
        option.textContent = list.name;
        taskListSelect.appendChild(option);
    });
}

function updateStatistics() {
    let total = 0;
    let inProgress = 0;
    let completed = 0;
    let overdue = 0;

    const allTasks = getAllTasks();
    allTasks.forEach(task => {
        total++;
        if (task.status === 'in_progress') inProgress++;
        if (task.status === 'done') completed++;
        if (task.due_date && new Date(task.due_date) < new Date() && task.status !== 'done') {
            overdue++;
        }
    });

    document.getElementById('totalTasks').textContent = total;
    document.getElementById('inProgressTasks').textContent = inProgress;
    document.getElementById('completedTasks').textContent = completed;
    document.getElementById('overdueTasks').textContent = overdue;
}

function getTasksByStatus(status) {
    if (!tasks || !tasks[status]) return [];

    let statusTasks = tasks[status] || [];

    // Apply filters
    if (filters.priority) {
        statusTasks = statusTasks.filter(t => t.priority === filters.priority);
    }
    if (filters.assignee) {
        statusTasks = statusTasks.filter(t => t.assignees && t.assignees.includes(filters.assignee));
    }
    if (searchQuery) {
        statusTasks = statusTasks.filter(t =>
            t.title.toLowerCase().includes(searchQuery) ||
            (t.description && t.description.toLowerCase().includes(searchQuery))
        );
    }

    return statusTasks;
}

function getAllTasks() {
    let allTasks = [];

    if (tasks) {
        Object.values(tasks).forEach(statusTasks => {
            if (Array.isArray(statusTasks)) {
                allTasks = allTasks.concat(statusTasks);
            }
        });
    }

    // Apply filters
    if (filters.status) {
        allTasks = allTasks.filter(t => t.status === filters.status);
    }
    if (filters.priority) {
        allTasks = allTasks.filter(t => t.priority === filters.priority);
    }
    if (filters.assignee) {
        allTasks = allTasks.filter(t => t.assignees && t.assignees.includes(filters.assignee));
    }
    if (searchQuery) {
        allTasks = allTasks.filter(t =>
            t.title.toLowerCase().includes(searchQuery) ||
            (t.description && t.description.toLowerCase().includes(searchQuery))
        );
    }

    return allTasks;
}

function findTaskById(taskId) {
    const allTasks = getAllTasks();
    return allTasks.find(t => t.id == taskId);
}

function renderAssigneeAvatars(assignees) {
    if (!assignees || assignees.length === 0) return '';

    return assignees.map(assignee => {
        const initials = getInitials(assignee.name || assignee);
        return `<span class="assignee-avatar" title="${escapeHtml(assignee.name || assignee)}">${initials}</span>`;
    }).join('');
}

function renderTags(tags) {
    if (!tags || tags.length === 0) return '';

    return tags.map(tag => `<span class="task-tag">${escapeHtml(tag)}</span>`).join('');
}

function getInitials(name) {
    const parts = name.split(' ');
    if (parts.length >= 2) {
        return parts[0][0] + parts[1][0];
    }
    return name.substring(0, 2).toUpperCase();
}

function getStatusName(status) {
    const statusNames = {
        'backlog': 'Backlog',
        'todo': 'Da Fare',
        'in_progress': 'In Corso',
        'review': 'In Revisione',
        'done': 'Completato'
    };
    return statusNames[status] || status;
}

function getPriorityName(priority) {
    const priorityNames = {
        'low': 'Bassa',
        'medium': 'Media',
        'high': 'Alta',
        'urgent': 'Urgente'
    };
    return priorityNames[priority] || priority;
}

function updatePriorityColor(select) {
    select.className = `priority-${select.value}`;
}

function addTag(tagText) {
    if (!tagText.trim()) return;

    const tagList = document.getElementById('tagList');
    const tag = document.createElement('span');
    tag.className = 'tag';
    tag.innerHTML = `${escapeHtml(tagText)} <button onclick="this.parentElement.remove()">×</button>`;
    tagList.appendChild(tag);
}

function setupAssigneeSearch() {
    const input = document.getElementById('taskAssignees');
    const list = document.getElementById('assigneeList');

    input.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        if (query.length < 2) return;

        const matches = users.filter(u =>
            u.name.toLowerCase().includes(query) ||
            u.email.toLowerCase().includes(query)
        );

        showAssigneeSuggestions(matches);
    });
}

function showAssigneeSuggestions(users) {
    // Implementation for showing assignee suggestions
    // Would create a dropdown with user options
}

function quickAddTask(status) {
    openCreateTaskModal();
    document.getElementById('taskStatus').value = status;
}

function quickEditTask(taskId) {
    const task = findTaskById(taskId);
    if (!task) return;

    selectedTask = task;
    document.getElementById('modalTitle').textContent = 'Modifica Attività';

    // Populate form
    document.getElementById('taskTitle').value = task.title;
    document.getElementById('taskDescription').value = task.description || '';
    document.getElementById('taskStatus').value = task.status;
    document.getElementById('taskPriority').value = task.priority;
    document.getElementById('taskDueDate').value = task.due_date || '';
    document.getElementById('taskEstimatedHours').value = task.estimated_hours || '';
    document.getElementById('taskList').value = task.task_list_id;

    // Populate tags
    const tagList = document.getElementById('tagList');
    tagList.innerHTML = '';
    if (task.tags) {
        task.tags.forEach(tag => addTag(tag));
    }

    document.getElementById('taskModal').style.display = 'block';
}

async function createNewBoard() {
    const name = prompt('Nome del nuovo board:');
    if (!name) return;

    try {
        const response = await fetch(`${API_BASE}/task-lists.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({
                name: name,
                board_type: 'kanban'
            })
        });

        const data = await response.json();

        if (data.success) {
            loadTaskLists();
            showNotification('Board creato con successo', 'success');
        } else {
            showNotification(data.message || 'Errore nella creazione del board', 'error');
        }
    } catch (error) {
        console.error('Error creating board:', error);
        showNotification('Errore nella creazione del board', 'error');
    }
}

function toggleFilterDropdown() {
    const dropdown = document.getElementById('filterDropdown');
    const btn = document.getElementById('filterBtn');

    if (dropdown.style.display === 'block') {
        dropdown.style.display = 'none';
    } else {
        const rect = btn.getBoundingClientRect();
        dropdown.style.display = 'block';
        dropdown.style.top = rect.bottom + 'px';
        dropdown.style.right = (window.innerWidth - rect.right) + 'px';
    }
}

function applyFilters() {
    filters.status = document.getElementById('filterStatus').value;
    filters.priority = document.getElementById('filterPriority').value;
    filters.assignee = document.getElementById('filterAssignee').value;

    renderTasks();
    document.getElementById('filterDropdown').style.display = 'none';
    showNotification('Filtri applicati', 'success');
}

function clearFilters() {
    filters = {
        status: '',
        priority: '',
        assignee: ''
    };

    document.getElementById('filterStatus').value = '';
    document.getElementById('filterPriority').value = '';
    document.getElementById('filterAssignee').value = '';

    renderTasks();
    document.getElementById('filterDropdown').style.display = 'none';
    showNotification('Filtri rimossi', 'success');
}

function showSortOptions() {
    // Implementation for sorting options
    // Would show a dropdown with sort options
}

function updateAssigneeOptions() {
    const select = document.getElementById('filterAssignee');
    select.innerHTML = '<option value="">Tutti</option>';

    users.forEach(user => {
        const option = document.createElement('option');
        option.value = user.id;
        option.textContent = user.name;
        select.appendChild(option);
    });
}

function startTimer() {
    // Implementation for time tracking
    showNotification('Timer avviato', 'success');
}

function editTask() {
    if (selectedTask) {
        quickEditTask(selectedTask.id);
        closeTaskDetailModal();
    }
}

function calculateDuration(start, end) {
    const diff = end - start;
    const hours = Math.floor(diff / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    return `${hours}h ${minutes}m`;
}

// Utility functions
function formatDate(date) {
    return date.toLocaleDateString('it-IT', {
        day: 'numeric',
        month: 'short'
    });
}

function formatDateTime(date) {
    return date.toLocaleDateString('it-IT', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, m => map[m]);
}

function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;

    // Add to page
    document.body.appendChild(notification);

    // Animate in
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);

    // Remove after 3 seconds
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}