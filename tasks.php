<?php
session_start();
require_once 'config_v2.php';
require_once 'includes/SimpleAuth.php';

// Check if user is logged in
$auth = new SimpleAuth();
if (!$auth->isAuthenticated()) {
    header('Location: index_v2.php?next=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$user = $auth->getCurrentUser();
$currentTenant = $auth->getCurrentTenant();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Attività - Nexio Solution</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/tasks.css">
</head>
<body>
    <div class="app-layout">
        <?php include 'components/sidebar.php'; ?>

        <div class="main-wrapper">
            <?php include 'components/header.php'; ?>

            <main class="main-content">
                <!-- Page Header -->
                <div class="page-header">
                    <h1 class="page-title">Gestione Attività</h1>
                </div>

                <!-- Tasks Header -->
                <div class="tasks-header">
                    <div class="header-left">
                        <div class="board-selector">
                            <select id="boardSelector" class="board-select">
                                <!-- Boards will be loaded dynamically -->
                            </select>
                            <button id="newBoardBtn" class="btn-icon" title="Crea nuovo board">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="header-center">
                        <div class="search-box">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                            <input type="text" id="taskSearch" placeholder="Cerca attività...">
                        </div>
                    </div>

                    <div class="header-right">
                        <div class="view-options">
                            <button class="view-btn active" data-view="kanban">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                                </svg>
                                Kanban
                            </button>
                            <button class="view-btn" data-view="list">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                                </svg>
                                Lista
                            </button>
                        </div>

                        <div class="filter-controls">
                            <button id="filterBtn" class="btn-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />
                                </svg>
                            </button>
                            <button id="sortBtn" class="btn-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5L7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5" />
                                </svg>
                            </button>
                        </div>

                        <button id="createTaskBtn" class="btn btn-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            <span>Nuova Attività</span>
                        </button>
                    </div>
                </div>

                <!-- Board Stats -->
                <div class="board-stats">
                    <div class="stat-item">
                        <div class="stat-icon" style="background-color: #EBF5FF;">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#2563EB" width="20" height="20">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="stat-content">
                            <span class="stat-value" id="totalTasks">0</span>
                            <span class="stat-label">Totale Attività</span>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon" style="background-color: #FEF3C7;">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#F59E0B" width="20" height="20">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="stat-content">
                            <span class="stat-value" id="inProgressTasks">0</span>
                            <span class="stat-label">In Corso</span>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon" style="background-color: #D1FAE5;">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#10B981" width="20" height="20">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="stat-content">
                            <span class="stat-value" id="completedTasks">0</span>
                            <span class="stat-label">Completate</span>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon" style="background-color: #FEE2E2;">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#EF4444" width="20" height="20">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            </svg>
                        </div>
                        <div class="stat-content">
                            <span class="stat-value" id="overdueTasks">0</span>
                            <span class="stat-label">In Ritardo</span>
                        </div>
                    </div>
                </div>

                <!-- Kanban Board Container -->
                <div id="boardContainer" class="board-container kanban-view">
                    <!-- Board will be rendered here by JavaScript -->
                    <div class="board-loading">
                        <div class="spinner"></div>
                        <p>Caricamento board...</p>
                    </div>
                </div>

                <!-- List View Container (hidden by default) -->
                <div id="listContainer" class="list-container" style="display: none;">
                    <!-- List will be rendered here -->
                </div>
            </main>
        </div>
    </div>

    <!-- Task Creation/Edit Modal -->
    <div id="taskModal" class="modal" style="display: none;">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2 id="modalTitle">Nuova Attività</h2>
                <button class="modal-close" onclick="closeTaskModal()">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="24" height="24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form id="taskForm" class="modal-body">
                <div class="form-group">
                    <label for="taskTitle">Titolo *</label>
                    <input type="text" id="taskTitle" name="title" required placeholder="Inserisci titolo attività">
                </div>

                <div class="form-group">
                    <label for="taskDescription">Descrizione</label>
                    <textarea id="taskDescription" name="description" rows="4" placeholder="Aggiungi una descrizione dettagliata..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="taskStatus">Stato</label>
                        <select id="taskStatus" name="status">
                            <option value="backlog">Backlog</option>
                            <option value="todo">Da Fare</option>
                            <option value="in_progress">In Corso</option>
                            <option value="review">In Revisione</option>
                            <option value="done">Completato</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="taskPriority">Priorità</label>
                        <select id="taskPriority" name="priority">
                            <option value="low">Bassa</option>
                            <option value="medium" selected>Media</option>
                            <option value="high">Alta</option>
                            <option value="urgent">Urgente</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="taskDueDate">Scadenza</label>
                        <input type="date" id="taskDueDate" name="due_date">
                    </div>
                    <div class="form-group">
                        <label for="taskEstimatedHours">Ore Stimate</label>
                        <input type="number" id="taskEstimatedHours" name="estimated_hours" min="0" step="0.5" placeholder="0">
                    </div>
                </div>

                <div class="form-group">
                    <label for="taskAssignees">Assegnatari</label>
                    <div class="assignee-selector">
                        <input type="text" id="taskAssignees" placeholder="Cerca utenti..." autocomplete="off">
                        <div id="assigneeList" class="assignee-list">
                            <!-- Selected assignees will appear here -->
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="taskTags">Tags</label>
                    <div class="tag-input">
                        <input type="text" id="taskTags" placeholder="Aggiungi tags (premi Enter)">
                        <div id="tagList" class="tag-list">
                            <!-- Tags will appear here -->
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="taskList">Lista/Board</label>
                    <select id="taskList" name="task_list_id">
                        <!-- Options will be loaded dynamically -->
                    </select>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" id="taskRecurring" name="is_recurring">
                        Attività ricorrente
                    </label>
                    <div id="recurringOptions" style="display: none;">
                        <select name="recurrence_pattern">
                            <option value="daily">Giornaliera</option>
                            <option value="weekly">Settimanale</option>
                            <option value="monthly">Mensile</option>
                        </select>
                    </div>
                </div>
            </form>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeTaskModal()">Annulla</button>
                <button type="submit" form="taskForm" class="btn btn-primary">Salva Attività</button>
            </div>
        </div>
    </div>

    <!-- Task Detail Modal -->
    <div id="taskDetailModal" class="modal" style="display: none;">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <div class="task-header-info">
                    <h2 id="detailTaskTitle"></h2>
                    <span id="detailTaskStatus" class="status-badge"></span>
                    <span id="detailTaskPriority" class="priority-badge"></span>
                </div>
                <button class="modal-close" onclick="closeTaskDetailModal()">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="24" height="24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="modal-body task-detail-body">
                <div class="task-detail-main">
                    <div class="task-description">
                        <h3>Descrizione</h3>
                        <p id="detailTaskDescription"></p>
                    </div>

                    <div class="task-activity">
                        <h3>Attività e Commenti</h3>
                        <div class="comment-form">
                            <textarea id="newComment" placeholder="Scrivi un commento..."></textarea>
                            <button id="addCommentBtn" class="btn btn-primary btn-sm">Commenta</button>
                        </div>
                        <div id="taskComments" class="comments-list">
                            <!-- Comments will be loaded here -->
                        </div>
                    </div>
                </div>

                <div class="task-detail-sidebar">
                    <div class="detail-section">
                        <h4>Dettagli</h4>
                        <div class="detail-item">
                            <span class="detail-label">Assegnatari:</span>
                            <div id="detailAssignees" class="assignee-avatars"></div>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Scadenza:</span>
                            <span id="detailDueDate"></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Ore Stimate:</span>
                            <span id="detailEstimatedHours"></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Ore Registrate:</span>
                            <span id="detailLoggedHours"></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Tags:</span>
                            <div id="detailTags" class="tag-list"></div>
                        </div>
                    </div>

                    <div class="detail-section">
                        <h4>Time Tracking</h4>
                        <button id="startTimerBtn" class="btn btn-success btn-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="16" height="16">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z" />
                            </svg>
                            Inizia Timer
                        </button>
                        <div id="timeEntries" class="time-entries">
                            <!-- Time entries will be loaded here -->
                        </div>
                    </div>

                    <div class="detail-section">
                        <h4>Azioni</h4>
                        <button class="btn btn-secondary btn-sm" onclick="editTask()">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="16" height="16">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                            </svg>
                            Modifica
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="deleteTask()">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="16" height="16">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
                            Elimina
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Dropdown -->
    <div id="filterDropdown" class="dropdown-menu" style="display: none;">
        <div class="dropdown-header">Filtri</div>
        <div class="filter-section">
            <label>Stato</label>
            <select id="filterStatus">
                <option value="">Tutti</option>
                <option value="todo">Da Fare</option>
                <option value="in_progress">In Corso</option>
                <option value="review">In Revisione</option>
                <option value="done">Completato</option>
            </select>
        </div>
        <div class="filter-section">
            <label>Priorità</label>
            <select id="filterPriority">
                <option value="">Tutte</option>
                <option value="urgent">Urgente</option>
                <option value="high">Alta</option>
                <option value="medium">Media</option>
                <option value="low">Bassa</option>
            </select>
        </div>
        <div class="filter-section">
            <label>Assegnatario</label>
            <select id="filterAssignee">
                <option value="">Tutti</option>
                <!-- Options will be loaded dynamically -->
            </select>
        </div>
        <button class="btn btn-primary btn-sm" onclick="applyFilters()">Applica</button>
        <button class="btn btn-secondary btn-sm" onclick="clearFilters()">Pulisci</button>
    </div>

    <script src="assets/js/navigation-helper.js"></script>
    <script src="assets/js/tasks.js"></script>
</body>
</html>