/**
 * Calendar Management Module
 * Handles calendar view, event creation, drag-drop functionality
 */

// Calendar state
let currentView = 'month';
let currentDate = new Date();
let calendars = [];
let events = [];
let selectedCalendarIds = [];
let draggedEvent = null;
let selectedEvent = null;

// API endpoints
const API_BASE = window.location.pathname.split('/').slice(0, -1).join('/') + '/api';

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', function() {
    initializeCalendar();
    setupEventListeners();
    loadCalendars();
    loadEvents();
});

// Initialize calendar
function initializeCalendar() {
    // Set current date display
    updatePeriodDisplay();

    // Initialize view
    renderCalendarView();
}

// Setup event listeners
function setupEventListeners() {
    // View switcher
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentView = this.dataset.view;
            renderCalendarView();
        });
    });

    // Navigation buttons
    document.getElementById('prevBtn').addEventListener('click', navigatePrevious);
    document.getElementById('nextBtn').addEventListener('click', navigateNext);
    document.getElementById('todayBtn').addEventListener('click', navigateToday);

    // Create event button
    document.getElementById('createEventBtn').addEventListener('click', openCreateEventModal);

    // Event form submission
    document.getElementById('eventForm').addEventListener('submit', handleEventSubmit);

    // Color picker
    document.querySelectorAll('.color-option').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.color-option').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('eventColor').value = this.dataset.color;
        });
    });

    // All day checkbox
    document.getElementById('eventAllDay').addEventListener('change', function() {
        const startInput = document.getElementById('eventStart');
        const endInput = document.getElementById('eventEnd');
        if (this.checked) {
            startInput.type = 'date';
            endInput.type = 'date';
        } else {
            startInput.type = 'datetime-local';
            endInput.type = 'datetime-local';
        }
    });

    // Calendar filter button
    document.getElementById('calendarFilterBtn').addEventListener('click', toggleCalendarFilter);

    // Close modals on outside click
    window.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            e.target.style.display = 'none';
        }
    });

    // Context menu
    document.addEventListener('contextmenu', function(e) {
        const eventElement = e.target.closest('.calendar-event');
        if (eventElement) {
            e.preventDefault();
            showEventContextMenu(e, eventElement.dataset.eventId);
        }
    });
}

// Load calendars
async function loadCalendars() {
    try {
        const response = await fetch(`${API_BASE}/calendars.php`, {
            credentials: 'include'
        });
        const data = await response.json();

        if (data.success && data.data) {
            calendars = data.data;
            updateCalendarSelectors();

            // Select all calendars by default
            selectedCalendarIds = calendars.map(c => c.id);
        }
    } catch (error) {
        console.error('Error loading calendars:', error);
        showNotification('Errore nel caricamento dei calendari', 'error');
    }
}

// Load events
async function loadEvents() {
    try {
        const startDate = getViewStartDate();
        const endDate = getViewEndDate();

        const response = await fetch(`${API_BASE}/events.php?start_date=${formatDate(startDate)}&end_date=${formatDate(endDate)}`, {
            credentials: 'include'
        });
        const data = await response.json();

        if (data.success && data.data) {
            events = data.data;
            renderCalendarView();
        }
    } catch (error) {
        console.error('Error loading events:', error);
        showNotification('Errore nel caricamento degli eventi', 'error');
    }
}

// Render calendar view
function renderCalendarView() {
    const container = document.getElementById('calendarView');
    container.innerHTML = '';

    switch(currentView) {
        case 'month':
            renderMonthView(container);
            break;
        case 'week':
            renderWeekView(container);
            break;
        case 'day':
            renderDayView(container);
            break;
        case 'list':
            renderListView(container);
            break;
    }

    // Hide/show mini calendar
    const miniCalendar = document.getElementById('miniCalendarSidebar');
    if (currentView === 'week' || currentView === 'day') {
        miniCalendar.style.display = 'block';
        renderMiniCalendar();
    } else {
        miniCalendar.style.display = 'none';
    }
}

// Render month view
function renderMonthView(container) {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const startDate = new Date(firstDay);
    startDate.setDate(startDate.getDate() - firstDay.getDay());

    let html = '<div class="calendar-grid month-grid">';

    // Weekday headers
    html += '<div class="weekday-header">';
    const weekdays = ['Dom', 'Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab'];
    weekdays.forEach(day => {
        html += `<div class="weekday">${day}</div>`;
    });
    html += '</div>';

    // Calendar days
    html += '<div class="calendar-days">';
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    for (let i = 0; i < 42; i++) {
        const currentDay = new Date(startDate);
        currentDay.setDate(startDate.getDate() + i);

        const isCurrentMonth = currentDay.getMonth() === month;
        const isToday = currentDay.getTime() === today.getTime();
        const dayEvents = getEventsForDate(currentDay);

        let dayClass = 'calendar-day';
        if (!isCurrentMonth) dayClass += ' other-month';
        if (isToday) dayClass += ' today';

        html += `<div class="${dayClass}" data-date="${formatDate(currentDay)}" ondrop="handleDrop(event)" ondragover="handleDragOver(event)">`;
        html += `<div class="day-number">${currentDay.getDate()}</div>`;

        if (dayEvents.length > 0) {
            html += '<div class="day-events">';
            dayEvents.slice(0, 3).forEach(event => {
                const eventTime = new Date(event.start_datetime).toLocaleTimeString('it-IT', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
                html += `
                    <div class="calendar-event"
                         data-event-id="${event.id}"
                         style="background-color: ${event.color || '#2563EB'};"
                         draggable="true"
                         ondragstart="handleDragStart(event)"
                         onclick="showEventDetails(${event.id})">
                        ${!event.all_day ? `<span class="event-time">${eventTime}</span>` : ''}
                        <span class="event-title">${escapeHtml(event.title)}</span>
                    </div>
                `;
            });

            if (dayEvents.length > 3) {
                html += `<div class="more-events">+${dayEvents.length - 3} altri</div>`;
            }
            html += '</div>';
        }

        html += '</div>';
    }
    html += '</div></div>';

    container.innerHTML = html;
}

// Render week view
function renderWeekView(container) {
    const startOfWeek = new Date(currentDate);
    startOfWeek.setDate(currentDate.getDate() - currentDate.getDay());

    let html = '<div class="calendar-grid week-grid">';

    // Time column
    html += '<div class="time-column">';
    html += '<div class="time-header"></div>';
    for (let hour = 0; hour < 24; hour++) {
        html += `<div class="time-slot">${hour.toString().padStart(2, '0')}:00</div>`;
    }
    html += '</div>';

    // Day columns
    const weekdays = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
    for (let day = 0; day < 7; day++) {
        const currentDay = new Date(startOfWeek);
        currentDay.setDate(startOfWeek.getDate() + day);
        const isToday = isDateToday(currentDay);
        const dayEvents = getEventsForDate(currentDay);

        html += `<div class="day-column ${isToday ? 'today' : ''}">`;
        html += `<div class="day-header">
                    <div class="day-name">${weekdays[day]}</div>
                    <div class="day-date">${currentDay.getDate()}</div>
                 </div>`;

        html += '<div class="day-content" data-date="' + formatDate(currentDay) + '" ondrop="handleDrop(event)" ondragover="handleDragOver(event)">';

        // Hour slots
        for (let hour = 0; hour < 24; hour++) {
            html += `<div class="hour-slot" data-hour="${hour}"></div>`;
        }

        // Events
        dayEvents.forEach(event => {
            const startTime = new Date(event.start_datetime);
            const endTime = new Date(event.end_datetime);
            const startHour = startTime.getHours() + startTime.getMinutes() / 60;
            const duration = (endTime - startTime) / (1000 * 60 * 60);

            const top = startHour * 60;
            const height = duration * 60;

            html += `
                <div class="calendar-event week-event"
                     data-event-id="${event.id}"
                     style="top: ${top}px; height: ${height}px; background-color: ${event.color || '#2563EB'};"
                     draggable="true"
                     ondragstart="handleDragStart(event)"
                     onclick="showEventDetails(${event.id})">
                    <div class="event-time">${formatTime(startTime)} - ${formatTime(endTime)}</div>
                    <div class="event-title">${escapeHtml(event.title)}</div>
                    ${event.location ? `<div class="event-location">${escapeHtml(event.location)}</div>` : ''}
                </div>
            `;
        });

        html += '</div></div>';
    }

    html += '</div>';
    container.innerHTML = html;
}

// Render day view
function renderDayView(container) {
    let html = '<div class="calendar-grid day-grid">';

    // Time column
    html += '<div class="time-column">';
    for (let hour = 0; hour < 24; hour++) {
        html += `<div class="time-slot">${hour.toString().padStart(2, '0')}:00</div>`;
    }
    html += '</div>';

    // Day content
    const dayEvents = getEventsForDate(currentDate);

    html += '<div class="day-detail" data-date="' + formatDate(currentDate) + '" ondrop="handleDrop(event)" ondragover="handleDragOver(event)">';

    // Hour slots
    for (let hour = 0; hour < 24; hour++) {
        html += `<div class="hour-slot" data-hour="${hour}"></div>`;
    }

    // Events
    dayEvents.forEach(event => {
        const startTime = new Date(event.start_datetime);
        const endTime = new Date(event.end_datetime);
        const startHour = startTime.getHours() + startTime.getMinutes() / 60;
        const duration = (endTime - startTime) / (1000 * 60 * 60);

        const top = startHour * 80;
        const height = duration * 80;

        html += `
            <div class="calendar-event day-event"
                 data-event-id="${event.id}"
                 style="top: ${top}px; height: ${height}px; background-color: ${event.color || '#2563EB'};"
                 draggable="true"
                 ondragstart="handleDragStart(event)"
                 onclick="showEventDetails(${event.id})">
                <div class="event-time">${formatTime(startTime)} - ${formatTime(endTime)}</div>
                <div class="event-title">${escapeHtml(event.title)}</div>
                ${event.location ? `<div class="event-location">${escapeHtml(event.location)}</div>` : ''}
                ${event.description ? `<div class="event-description">${escapeHtml(event.description)}</div>` : ''}
            </div>
        `;
    });

    html += '</div></div>';
    container.innerHTML = html;
}

// Render list view
function renderListView(container) {
    const groupedEvents = groupEventsByDate();

    let html = '<div class="event-list">';

    Object.keys(groupedEvents).forEach(date => {
        const dateObj = new Date(date);
        const dayEvents = groupedEvents[date];

        html += `<div class="event-date-group">`;
        html += `<h3 class="date-header">${formatDateLong(dateObj)}</h3>`;

        dayEvents.forEach(event => {
            const startTime = new Date(event.start_datetime);
            const endTime = new Date(event.end_datetime);

            html += `
                <div class="list-event" onclick="showEventDetails(${event.id})">
                    <div class="event-color" style="background-color: ${event.color || '#2563EB'};"></div>
                    <div class="event-info">
                        <div class="event-header">
                            <span class="event-title">${escapeHtml(event.title)}</span>
                            <span class="event-time">
                                ${event.all_day ? 'Tutto il giorno' : `${formatTime(startTime)} - ${formatTime(endTime)}`}
                            </span>
                        </div>
                        ${event.location ? `<div class="event-location">${escapeHtml(event.location)}</div>` : ''}
                        ${event.description ? `<div class="event-description">${escapeHtml(event.description)}</div>` : ''}
                    </div>
                    <div class="event-actions">
                        <button class="btn-icon" onclick="editEvent(${event.id}); event.stopPropagation();">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="16" height="16">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                            </svg>
                        </button>
                        <button class="btn-icon" onclick="deleteEvent(${event.id}); event.stopPropagation();">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="16" height="16">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
                        </button>
                    </div>
                </div>
            `;
        });

        html += '</div>';
    });

    if (Object.keys(groupedEvents).length === 0) {
        html += '<div class="empty-state">Nessun evento per questo periodo</div>';
    }

    html += '</div>';
    container.innerHTML = html;
}

// Render mini calendar
function renderMiniCalendar() {
    // Implementation for mini calendar in sidebar
    // This would be a compact month view
}

// Event handlers
function handleEventSubmit(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const eventData = Object.fromEntries(formData);

    if (selectedEvent) {
        updateEvent(selectedEvent.id, eventData);
    } else {
        createEvent(eventData);
    }
}

async function createEvent(eventData) {
    try {
        const response = await fetch(`${API_BASE}/events.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify(eventData)
        });

        const data = await response.json();

        if (data.success) {
            closeEventModal();
            loadEvents();
            showNotification('Evento creato con successo', 'success');
        } else {
            showNotification(data.message || 'Errore nella creazione dell\'evento', 'error');
        }
    } catch (error) {
        console.error('Error creating event:', error);
        showNotification('Errore nella creazione dell\'evento', 'error');
    }
}

async function updateEvent(eventId, eventData) {
    try {
        const response = await fetch(`${API_BASE}/events.php/${eventId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify(eventData)
        });

        const data = await response.json();

        if (data.success) {
            closeEventModal();
            loadEvents();
            showNotification('Evento aggiornato con successo', 'success');
        } else {
            showNotification(data.message || 'Errore nell\'aggiornamento dell\'evento', 'error');
        }
    } catch (error) {
        console.error('Error updating event:', error);
        showNotification('Errore nell\'aggiornamento dell\'evento', 'error');
    }
}

async function deleteEvent(eventId) {
    if (!confirm('Sei sicuro di voler eliminare questo evento?')) return;

    try {
        const response = await fetch(`${API_BASE}/events.php/${eventId}`, {
            method: 'DELETE',
            credentials: 'include'
        });

        const data = await response.json();

        if (data.success) {
            loadEvents();
            showNotification('Evento eliminato con successo', 'success');
        } else {
            showNotification(data.message || 'Errore nell\'eliminazione dell\'evento', 'error');
        }
    } catch (error) {
        console.error('Error deleting event:', error);
        showNotification('Errore nell\'eliminazione dell\'evento', 'error');
    }
}

// Drag and drop handlers
function handleDragStart(e) {
    draggedEvent = events.find(ev => ev.id == e.target.dataset.eventId);
    e.dataTransfer.effectAllowed = 'move';
    e.target.style.opacity = '0.5';
}

function handleDragOver(e) {
    if (e.preventDefault) {
        e.preventDefault();
    }
    e.dataTransfer.dropEffect = 'move';
    return false;
}

async function handleDrop(e) {
    if (e.stopPropagation) {
        e.stopPropagation();
    }

    const dropTarget = e.target.closest('[data-date]');
    if (!dropTarget || !draggedEvent) return;

    const newDate = dropTarget.dataset.date;
    const hourSlot = e.target.closest('[data-hour]');

    // Calculate new datetime
    let newStartDateTime = new Date(newDate);
    if (hourSlot) {
        newStartDateTime.setHours(parseInt(hourSlot.dataset.hour));
    }

    // Calculate duration and new end time
    const originalStart = new Date(draggedEvent.start_datetime);
    const originalEnd = new Date(draggedEvent.end_datetime);
    const duration = originalEnd - originalStart;
    const newEndDateTime = new Date(newStartDateTime.getTime() + duration);

    // Update event via API
    try {
        const response = await fetch(`${API_BASE}/events.php/${draggedEvent.id}/move`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({
                start_datetime: formatDateTime(newStartDateTime),
                end_datetime: formatDateTime(newEndDateTime)
            })
        });

        const data = await response.json();

        if (data.success) {
            loadEvents();
            showNotification('Evento spostato con successo', 'success');
        } else {
            showNotification(data.message || 'Errore nello spostamento dell\'evento', 'error');
        }
    } catch (error) {
        console.error('Error moving event:', error);
        showNotification('Errore nello spostamento dell\'evento', 'error');
    }

    // Reset drag state
    draggedEvent = null;
    document.querySelectorAll('.calendar-event').forEach(el => {
        el.style.opacity = '';
    });

    return false;
}

// Navigation functions
function navigatePrevious() {
    switch(currentView) {
        case 'month':
            currentDate.setMonth(currentDate.getMonth() - 1);
            break;
        case 'week':
            currentDate.setDate(currentDate.getDate() - 7);
            break;
        case 'day':
            currentDate.setDate(currentDate.getDate() - 1);
            break;
    }
    updatePeriodDisplay();
    loadEvents();
}

function navigateNext() {
    switch(currentView) {
        case 'month':
            currentDate.setMonth(currentDate.getMonth() + 1);
            break;
        case 'week':
            currentDate.setDate(currentDate.getDate() + 7);
            break;
        case 'day':
            currentDate.setDate(currentDate.getDate() + 1);
            break;
    }
    updatePeriodDisplay();
    loadEvents();
}

function navigateToday() {
    currentDate = new Date();
    updatePeriodDisplay();
    loadEvents();
}

// Helper functions
function updatePeriodDisplay() {
    const display = document.getElementById('currentPeriodDisplay');
    const options = { year: 'numeric', month: 'long' };

    switch(currentView) {
        case 'month':
            display.textContent = currentDate.toLocaleDateString('it-IT', options);
            break;
        case 'week':
            const startOfWeek = new Date(currentDate);
            startOfWeek.setDate(currentDate.getDate() - currentDate.getDay());
            const endOfWeek = new Date(startOfWeek);
            endOfWeek.setDate(startOfWeek.getDate() + 6);
            display.textContent = `${formatDateShort(startOfWeek)} - ${formatDateShort(endOfWeek)}`;
            break;
        case 'day':
            display.textContent = formatDateLong(currentDate);
            break;
        case 'list':
            display.textContent = currentDate.toLocaleDateString('it-IT', options);
            break;
    }
}

function updateCalendarSelectors() {
    // Update calendar dropdown in event form
    const select = document.getElementById('eventCalendar');
    select.innerHTML = '';
    calendars.forEach(calendar => {
        const option = document.createElement('option');
        option.value = calendar.id;
        option.textContent = calendar.name;
        select.appendChild(option);
    });

    // Update calendar filter list
    const filterList = document.getElementById('calendarFilterList');
    if (filterList) {
        filterList.innerHTML = '';
        calendars.forEach(calendar => {
            const item = document.createElement('div');
            item.className = 'filter-item';
            item.innerHTML = `
                <label>
                    <input type="checkbox" value="${calendar.id}" checked>
                    <span class="calendar-color" style="background-color: ${calendar.color};"></span>
                    ${escapeHtml(calendar.name)}
                </label>
            `;
            filterList.appendChild(item);
        });
    }

    // Update calendar list in sidebar
    const calendarList = document.getElementById('calendarList');
    if (calendarList) {
        calendarList.innerHTML = '';
        calendars.forEach(calendar => {
            const item = document.createElement('div');
            item.className = 'calendar-item';
            item.innerHTML = `
                <span class="calendar-color" style="background-color: ${calendar.color};"></span>
                <span>${escapeHtml(calendar.name)}</span>
            `;
            calendarList.appendChild(item);
        });
    }
}

function getEventsForDate(date) {
    const dateStr = formatDate(date);
    return events.filter(event => {
        const eventStart = new Date(event.start_datetime);
        const eventEnd = new Date(event.end_datetime);
        const eventStartStr = formatDate(eventStart);
        const eventEndStr = formatDate(eventEnd);

        return eventStartStr <= dateStr && eventEndStr >= dateStr;
    }).filter(event => {
        // Filter by selected calendars
        return selectedCalendarIds.length === 0 || selectedCalendarIds.includes(event.calendar_id);
    });
}

function groupEventsByDate() {
    const grouped = {};

    events.filter(event => {
        return selectedCalendarIds.length === 0 || selectedCalendarIds.includes(event.calendar_id);
    }).forEach(event => {
        const date = formatDate(new Date(event.start_datetime));
        if (!grouped[date]) {
            grouped[date] = [];
        }
        grouped[date].push(event);
    });

    // Sort dates
    const sortedDates = Object.keys(grouped).sort();
    const sortedGrouped = {};
    sortedDates.forEach(date => {
        sortedGrouped[date] = grouped[date].sort((a, b) => {
            return new Date(a.start_datetime) - new Date(b.start_datetime);
        });
    });

    return sortedGrouped;
}

function getViewStartDate() {
    switch(currentView) {
        case 'month':
            const firstDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
            const startDate = new Date(firstDay);
            startDate.setDate(startDate.getDate() - firstDay.getDay());
            return startDate;
        case 'week':
            const weekStart = new Date(currentDate);
            weekStart.setDate(currentDate.getDate() - currentDate.getDay());
            return weekStart;
        case 'day':
            return new Date(currentDate);
        default:
            return new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
    }
}

function getViewEndDate() {
    switch(currentView) {
        case 'month':
            const lastDay = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
            const endDate = new Date(lastDay);
            endDate.setDate(endDate.getDate() + (6 - lastDay.getDay()));
            return endDate;
        case 'week':
            const weekEnd = new Date(currentDate);
            weekEnd.setDate(currentDate.getDate() + (6 - currentDate.getDay()));
            return weekEnd;
        case 'day':
            return new Date(currentDate);
        default:
            return new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
    }
}

// Modal functions
function openCreateEventModal() {
    selectedEvent = null;
    document.getElementById('modalTitle').textContent = 'Nuovo Evento';
    document.getElementById('eventForm').reset();
    document.getElementById('eventModal').style.display = 'block';

    // Set default datetime to current hour
    const now = new Date();
    now.setMinutes(0);
    const endTime = new Date(now);
    endTime.setHours(now.getHours() + 1);

    document.getElementById('eventStart').value = formatDateTimeLocal(now);
    document.getElementById('eventEnd').value = formatDateTimeLocal(endTime);
}

function closeEventModal() {
    document.getElementById('eventModal').style.display = 'none';
    selectedEvent = null;
}

function showEventDetails(eventId) {
    const event = events.find(e => e.id == eventId);
    if (!event) return;

    selectedEvent = event;
    document.getElementById('modalTitle').textContent = 'Modifica Evento';

    // Populate form
    document.getElementById('eventTitle').value = event.title;
    document.getElementById('eventStart').value = formatDateTimeLocal(new Date(event.start_datetime));
    document.getElementById('eventEnd').value = formatDateTimeLocal(new Date(event.end_datetime));
    document.getElementById('eventCalendar').value = event.calendar_id;
    document.getElementById('eventLocation').value = event.location || '';
    document.getElementById('eventDescription').value = event.description || '';
    document.getElementById('eventColor').value = event.color || '#2563EB';
    document.getElementById('eventAllDay').checked = event.all_day;
    document.getElementById('eventRecurrence').value = event.recurrence_rule || '';

    // Set color picker
    document.querySelectorAll('.color-option').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.color === event.color);
    });

    document.getElementById('eventModal').style.display = 'block';
}

function showEventContextMenu(e, eventId) {
    const menu = document.getElementById('eventContextMenu');
    menu.style.display = 'block';
    menu.style.left = e.pageX + 'px';
    menu.style.top = e.pageY + 'px';

    // Setup menu actions
    menu.querySelectorAll('[data-action]').forEach(item => {
        item.onclick = function() {
            const action = this.dataset.action;
            menu.style.display = 'none';

            switch(action) {
                case 'edit':
                    showEventDetails(eventId);
                    break;
                case 'duplicate':
                    duplicateEvent(eventId);
                    break;
                case 'delete':
                    deleteEvent(eventId);
                    break;
            }
        };
    });

    // Hide on outside click
    setTimeout(() => {
        document.addEventListener('click', hideContextMenu);
    }, 0);
}

function hideContextMenu() {
    document.getElementById('eventContextMenu').style.display = 'none';
    document.removeEventListener('click', hideContextMenu);
}

function toggleCalendarFilter() {
    const dropdown = document.getElementById('calendarFilterDropdown');
    const btn = document.getElementById('calendarFilterBtn');

    if (dropdown.style.display === 'block') {
        dropdown.style.display = 'none';
    } else {
        const rect = btn.getBoundingClientRect();
        dropdown.style.display = 'block';
        dropdown.style.top = rect.bottom + 'px';
        dropdown.style.right = (window.innerWidth - rect.right) + 'px';
    }
}

async function duplicateEvent(eventId) {
    const event = events.find(e => e.id == eventId);
    if (!event) return;

    const newEvent = {...event};
    delete newEvent.id;
    newEvent.title = `${event.title} (Copia)`;

    await createEvent(newEvent);
}

// Utility functions
function formatDate(date) {
    return date.toISOString().split('T')[0];
}

function formatDateTime(date) {
    return date.toISOString().slice(0, 19).replace('T', ' ');
}

function formatDateTimeLocal(date) {
    return date.toISOString().slice(0, 16);
}

function formatTime(date) {
    return date.toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' });
}

function formatDateShort(date) {
    return date.toLocaleDateString('it-IT', { day: 'numeric', month: 'short' });
}

function formatDateLong(date) {
    return date.toLocaleDateString('it-IT', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

function isDateToday(date) {
    const today = new Date();
    return date.getDate() === today.getDate() &&
           date.getMonth() === today.getMonth() &&
           date.getFullYear() === today.getFullYear();
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
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