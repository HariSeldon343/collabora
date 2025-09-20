<?php
session_start();
require_once 'config_v2.php';
require_once 'includes/SimpleAuth.php';

use Collabora\Auth\SimpleAuth;

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
    <title>Calendario - Nexio Solution</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/calendar.css">
</head>
<body>
    <div class="app-container">
        <?php include 'components/sidebar.php'; ?>

        <div class="main-content">
            <?php include 'components/header.php'; ?>

            <div class="content-area">
                <!-- Calendar Header -->
                <div class="calendar-header">
                    <div class="header-left">
                        <h1 class="page-title">Calendario</h1>
                        <button id="createEventBtn" class="btn btn-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            <span>Nuovo Evento</span>
                        </button>
                    </div>

                    <div class="header-center">
                        <button id="todayBtn" class="btn btn-secondary">Oggi</button>
                        <div class="calendar-navigation">
                            <button id="prevBtn" class="btn-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                                </svg>
                            </button>
                            <h2 id="currentPeriodDisplay" class="period-display">Gennaio 2025</h2>
                            <button id="nextBtn" class="btn-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="header-right">
                        <div class="view-switcher">
                            <button class="view-btn active" data-view="month">Mese</button>
                            <button class="view-btn" data-view="week">Settimana</button>
                            <button class="view-btn" data-view="day">Giorno</button>
                            <button class="view-btn" data-view="list">Lista</button>
                        </div>
                        <div class="calendar-selector">
                            <button id="calendarFilterBtn" class="btn-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Calendar Container -->
                <div class="calendar-container">
                    <div id="calendarView" class="calendar-view month-view">
                        <!-- Calendar will be rendered here by JavaScript -->
                        <div class="calendar-loading">
                            <div class="spinner"></div>
                            <p>Caricamento calendario...</p>
                        </div>
                    </div>

                    <!-- Mini Calendar Sidebar (for week/day views) -->
                    <div id="miniCalendarSidebar" class="mini-calendar-sidebar" style="display: none;">
                        <div class="mini-calendar">
                            <!-- Mini calendar will be rendered here -->
                        </div>
                        <div class="calendar-list">
                            <h3>I tuoi calendari</h3>
                            <div id="calendarList">
                                <!-- Calendar list will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Event Creation/Edit Modal -->
    <div id="eventModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Nuovo Evento</h2>
                <button class="modal-close" onclick="closeEventModal()">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="24" height="24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form id="eventForm" class="modal-body">
                <div class="form-group">
                    <label for="eventTitle">Titolo *</label>
                    <input type="text" id="eventTitle" name="title" required placeholder="Inserisci titolo evento">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="eventStart">Data e ora inizio *</label>
                        <input type="datetime-local" id="eventStart" name="start_datetime" required>
                    </div>
                    <div class="form-group">
                        <label for="eventEnd">Data e ora fine *</label>
                        <input type="datetime-local" id="eventEnd" name="end_datetime" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="eventCalendar">Calendario</label>
                    <select id="eventCalendar" name="calendar_id">
                        <!-- Options will be loaded dynamically -->
                    </select>
                </div>

                <div class="form-group">
                    <label for="eventLocation">Luogo</label>
                    <input type="text" id="eventLocation" name="location" placeholder="Aggiungi luogo">
                </div>

                <div class="form-group">
                    <label for="eventDescription">Descrizione</label>
                    <textarea id="eventDescription" name="description" rows="4" placeholder="Aggiungi descrizione"></textarea>
                </div>

                <div class="form-group">
                    <label for="eventColor">Colore</label>
                    <div class="color-picker">
                        <button type="button" class="color-option active" data-color="#2563EB" style="background-color: #2563EB"></button>
                        <button type="button" class="color-option" data-color="#10B981" style="background-color: #10B981"></button>
                        <button type="button" class="color-option" data-color="#F59E0B" style="background-color: #F59E0B"></button>
                        <button type="button" class="color-option" data-color="#EF4444" style="background-color: #EF4444"></button>
                        <button type="button" class="color-option" data-color="#8B5CF6" style="background-color: #8B5CF6"></button>
                        <button type="button" class="color-option" data-color="#EC4899" style="background-color: #EC4899"></button>
                    </div>
                    <input type="hidden" id="eventColor" name="color" value="#2563EB">
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" id="eventAllDay" name="all_day">
                        Tutto il giorno
                    </label>
                </div>

                <div class="form-group">
                    <label>Ricorrenza</label>
                    <select id="eventRecurrence" name="recurrence_rule">
                        <option value="">Non si ripete</option>
                        <option value="FREQ=DAILY">Ogni giorno</option>
                        <option value="FREQ=WEEKLY">Ogni settimana</option>
                        <option value="FREQ=MONTHLY">Ogni mese</option>
                        <option value="FREQ=YEARLY">Ogni anno</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Promemoria</label>
                    <select id="eventReminder" name="reminder">
                        <option value="">Nessuno</option>
                        <option value="5">5 minuti prima</option>
                        <option value="15">15 minuti prima</option>
                        <option value="30">30 minuti prima</option>
                        <option value="60">1 ora prima</option>
                        <option value="1440">1 giorno prima</option>
                    </select>
                </div>
            </form>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEventModal()">Annulla</button>
                <button type="submit" form="eventForm" class="btn btn-primary">Salva Evento</button>
            </div>
        </div>
    </div>

    <!-- Calendar Filter Dropdown -->
    <div id="calendarFilterDropdown" class="dropdown-menu" style="display: none;">
        <div class="dropdown-header">Calendari</div>
        <div id="calendarFilterList">
            <!-- Calendar filters will be loaded here -->
        </div>
    </div>

    <!-- Context Menu for Events -->
    <div id="eventContextMenu" class="context-menu" style="display: none;">
        <button class="context-menu-item" data-action="edit">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="16" height="16">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
            </svg>
            Modifica
        </button>
        <button class="context-menu-item" data-action="duplicate">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="16" height="16">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75m9.344-3.071l1.688 1.688a1.875 1.875 0 010 2.652l-9.031 9.031a1.875 1.875 0 01-1.326.55H3.75v-3.675c0-.498.198-.974.55-1.326l9.031-9.031a1.875 1.875 0 012.652 0z" />
            </svg>
            Duplica
        </button>
        <button class="context-menu-item text-danger" data-action="delete">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="16" height="16">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
            </svg>
            Elimina
        </button>
    </div>

    <script src="assets/js/calendar.js"></script>
</body>
</html>