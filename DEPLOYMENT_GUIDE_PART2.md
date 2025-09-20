# DEPLOYMENT GUIDE - PARTE 2
## Calendar & Task Management Modules

**Versione:** 2.1.0
**Data:** 2025-01-19
**Moduli:** Calendar/Events e Task Management

---

## 📋 Prerequisiti

### Software Richiesti
- **XAMPP** 8.2+ con:
  - Apache 2.4+
  - MariaDB 10.6+ o MySQL 8.0+
  - PHP 8.2+
- **Windows** 10/11 (64-bit)
- **Browser moderni**: Chrome 90+, Firefox 88+, Safari 14+, Edge 90+

### Sistema Esistente
- Nexiosolution Parte 1 già installato e funzionante
- Database `nexio_collabora_v2` esistente
- Utente admin configurato (`asamodeo@fortibyte.it`)

---

## 🚀 Deployment Rapido (5 minuti)

### Metodo Automatico (Raccomandato)

1. **Avvia XAMPP Control Panel**
   - Assicurati che Apache e MySQL siano in esecuzione

2. **Esegui lo script di deployment**
   ```batch
   cd C:\xampp\htdocs\Nexiosolution\collabora
   deploy_part2.bat
   ```

3. **Verifica l'installazione**
   - Apri browser: http://localhost/Nexiosolution/collabora/verify_part2.php
   - Tutti i componenti dovrebbero mostrare il segno verde ✓

4. **Login e test**
   - Vai a: http://localhost/Nexiosolution/collabora
   - Login: `asamodeo@fortibyte.it` / `Ricord@1991`
   - Naviga a "Calendario" e "Attività" dal menu laterale

---

## 🔧 Deployment Manuale (Dettagliato)

### Step 1: Backup Database

```sql
-- Esegui da phpMyAdmin o console MySQL
mysqldump -u root nexio_collabora_v2 > backup_before_part2.sql
```

### Step 2: Applicazione Migrazione Database

```sql
-- Connetti a MySQL
mysql -u root

-- Seleziona database
USE nexio_collabora_v2;

-- Applica migrazione
SOURCE C:/xampp/htdocs/Nexiosolution/collabora/database/migrations_part2.sql;

-- Verifica tabelle create
SHOW TABLES LIKE '%calendar%';
SHOW TABLES LIKE '%event%';
SHOW TABLES LIKE '%task%';
```

### Step 3: Verifica File Componenti

Assicurati che i seguenti file esistano:

#### API Endpoints
- `/api/calendars.php`
- `/api/events.php`
- `/api/tasks.php`
- `/api/task-lists.php`

#### PHP Classes
- `/includes/CalendarManager.php`
- `/includes/TaskManager.php`

#### Frontend Pages
- `/calendar.php`
- `/tasks.php`

#### JavaScript Modules
- `/assets/js/calendar.js`
- `/assets/js/tasks.js`

#### CSS Stylesheets
- `/assets/css/calendar.css`
- `/assets/css/tasks.css`

### Step 4: Configurazione Permessi (Windows/XAMPP)

In XAMPP su Windows i permessi sono gestiti automaticamente. Se necessario:

1. Click destro sulla cartella `collabora`
2. Proprietà → Sicurezza
3. Assicurati che "Users" abbia permessi di lettura/scrittura

### Step 5: Test Funzionalità

Esegui il test completo del sistema:

```bash
php C:\xampp\htdocs\Nexiosolution\collabora\test_part2_system.php
```

---

## 🧪 Testing Post-Deployment

### Test 1: API Authentication
```bash
# Test login
curl -c cookies.txt -X POST http://localhost/Nexiosolution/collabora/api/auth_simple.php \
  -H "Content-Type: application/json" \
  -d '{"action":"login","email":"asamodeo@fortibyte.it","password":"Ricord@1991"}'
```

### Test 2: Calendar API
```bash
# Get calendars
curl -b cookies.txt http://localhost/Nexiosolution/collabora/api/calendars.php

# Create calendar
curl -b cookies.txt -X POST http://localhost/Nexiosolution/collabora/api/calendars.php \
  -H "Content-Type: application/json" \
  -d '{"name":"Test Calendar","color":"#4F46E5"}'
```

### Test 3: Task API
```bash
# Get task lists
curl -b cookies.txt http://localhost/Nexiosolution/collabora/api/task-lists.php

# Create task list
curl -b cookies.txt -X POST http://localhost/Nexiosolution/collabora/api/task-lists.php \
  -H "Content-Type: application/json" \
  -d '{"name":"My Tasks","board_type":"kanban"}'
```

### Test 4: UI Components
1. **Calendar View**
   - Naviga a: http://localhost/Nexiosolution/collabora/calendar.php
   - Verifica: Vista mensile, creazione eventi, drag & drop

2. **Task Board**
   - Naviga a: http://localhost/Nexiosolution/collabora/tasks.php
   - Verifica: Kanban board, creazione task, spostamento colonne

---

## 📊 Verifica Performance

### Query di Test
```sql
-- Test performance query calendari
EXPLAIN SELECT c.*, COUNT(e.id) as event_count
FROM calendars c
LEFT JOIN events e ON c.id = e.calendar_id
WHERE c.tenant_id = 1
GROUP BY c.id;

-- Test performance query task
EXPLAIN SELECT t.*, COUNT(tc.id) as comment_count
FROM tasks t
LEFT JOIN task_comments tc ON t.id = tc.task_id
WHERE t.task_list_id = 1
GROUP BY t.id;
```

### Benchmark Attesi
- **API Response Time**: < 500ms per chiamata standard
- **Page Load Time**: < 2 secondi per pagina completa
- **Database Queries**: < 100ms per query standard
- **Concurrent Users**: Supporta 50+ utenti simultanei

---

## 🔒 Configurazione Sicurezza

### 1. Multi-Tenant Isolation
```php
// Verifica in ogni query
$stmt = $pdo->prepare("SELECT * FROM calendars WHERE tenant_id = ? AND id = ?");
$stmt->execute([$_SESSION['tenant_id'], $calendar_id]);
```

### 2. Session Security
```php
// In config_v2.php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_only_cookies', 1);
```

### 3. Input Validation
- Tutti gli input utente sono validati
- Prepared statements per tutte le query
- Escape HTML output per prevenire XSS

---

## 🐛 Troubleshooting

### Problema: "Table doesn't exist"
**Soluzione:**
```sql
-- Riapplica migrazione
mysql -u root nexio_collabora_v2 < database/migrations_part2.sql
```

### Problema: "404 on API calls"
**Soluzione:**
1. Verifica che i file API esistano in `/api/`
2. Controlla `.htaccess` non blocchi le richieste
3. Verifica sessione attiva

### Problema: "Permission denied"
**Soluzione:**
1. Verifica sessione utente attiva
2. Controlla `$_SESSION['tenant_id']` sia impostato
3. Verifica ruolo utente nel database

### Problema: "JavaScript errors"
**Soluzione:**
1. Apri Console Browser (F12)
2. Verifica percorsi file JS corretti
3. Controlla conflitti con altre librerie

---

## 📈 Monitoring & Maintenance

### Log Files
- **PHP Errors**: `C:\xampp\php\logs\php_error_log`
- **Apache Access**: `C:\xampp\apache\logs\access.log`
- **Apache Errors**: `C:\xampp\apache\logs\error.log`
- **MySQL Errors**: `C:\xampp\mysql\data\*.err`

### Backup Routine
```batch
REM Crea backup giornaliero
@echo off
set BACKUP_DIR=C:\xampp\backups
set TIMESTAMP=%date:~-4%%date:~3,2%%date:~0,2%
mysqldump -u root nexio_collabora_v2 > %BACKUP_DIR%\nexio_%TIMESTAMP%.sql
```

### Performance Monitoring
```sql
-- Monitora query lente
SET GLOBAL slow_query_log = 1;
SET GLOBAL slow_query_log_file = 'C:/xampp/mysql/data/slow_query.log';
SET GLOBAL long_query_time = 2;
```

---

## 🔄 Rollback Procedure

Se necessario tornare alla versione precedente:

1. **Ripristina database**
   ```sql
   mysql -u root nexio_collabora_v2 < backup_before_part2.sql
   ```

2. **Rimuovi nuove tabelle** (se non si usa backup)
   ```sql
   DROP TABLE IF EXISTS calendars, events, event_participants, event_reminders,
   calendar_shares, event_attachments, task_lists, tasks, task_assignments,
   task_comments, task_time_logs, task_attachments;
   ```

3. **Disabilita nuove funzionalità**
   - Rimuovi link menu da `/components/sidebar.php`
   - Rinomina file API con `.backup`

---

## 📞 Supporto & Risorse

### Documentazione
- **Progress Report**: `/PARTE2-PROGRESS.md`
- **API Documentation**: Inclusa nei file PHP di ogni endpoint
- **System Guide**: `/CLAUDE.md`

### File di Test
- **System Test**: `test_part2_system.php`
- **Component Verify**: `verify_part2.php`
- **Deployment Script**: `deploy_part2.bat`

### Contatti Sviluppo
- **Database Architect**: Team Database
- **Backend Architect**: Team Backend Systems
- **Frontend Architect**: Team UI/UX Development
- **DevOps Engineer**: Team Platform Engineering

---

## ✅ Checklist Finale

Prima di considerare il deployment completo, verifica:

- [ ] XAMPP Apache e MySQL in esecuzione
- [ ] Database migration applicata con successo
- [ ] Tutti i file API presenti e accessibili
- [ ] Frontend pages caricano correttamente
- [ ] JavaScript console senza errori
- [ ] Test di login riuscito
- [ ] Creazione calendario di test riuscita
- [ ] Creazione task di test riuscita
- [ ] Multi-tenant isolation verificato
- [ ] Performance entro parametri accettabili

---

## 🎉 Deployment Completato!

Se tutti i test sono passati, il sistema è pronto per l'uso.

**URL Sistema**: http://localhost/Nexiosolution/collabora
**Login Admin**: asamodeo@fortibyte.it / Ricord@1991

Buon lavoro con Nexiosolution Calendar & Task Management! 🚀