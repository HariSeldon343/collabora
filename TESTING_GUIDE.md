# 🧪 Guida Testing - Nexio Collabora

## Panoramica
Il sistema Nexio Collabora include una suite completa di test per verificare tutte le funzionalità. I test sono ora completamente accessibili e organizzati.

## 🚀 Accesso Rapido

### Da Browser (Solo in Development)
```
http://localhost/Nexiosolution/collabora/test_runner.php
```

### Da Command Line
```bash
# Lista tutti i test disponibili
php test_runner.php list

# Esegui tutti i test
php test_runner.php run

# Esegui test per categoria
php test_runner.php auth
php test_runner.php api
php test_runner.php system
```

## 📁 Struttura Test

```
/collabora/
├── test_runner.php           # Test Runner principale con UI
├── config/
│   └── test.config.php      # Configurazione sicurezza test
├── tests/                   # Directory test organizzata
│   ├── unit/               # Test unitari
│   ├── integration/        # Test di integrazione
│   ├── system/            # Test di sistema
│   └── security/          # Test di sicurezza
└── [test_*.php files]     # Test esistenti nella root
```

## 🔍 Test Disponibili (33 totali)

### 🔐 Autenticazione (11 test)
- `test_auth.php` - Test autenticazione base
- `test_auth_direct.php` - Test accesso diretto API
- `test_auth_endpoints.php` - Verifica tutti gli endpoint auth
- `test_auth_final.php` - Test suite completa autenticazione
- `test_auth_status_codes.php` - Verifica codici di stato HTTP
- `test_complete_login.php` - Test flusso login completo
- `test_login.php` - Test login semplice
- `test_login_complete.php` - Test login con redirect
- `test_login_redirect.php` - Test redirect post-login
- `test_session_fix.php` - Verifica gestione sessioni
- `test_tenant_flow.php` - Test flusso tenant

### 🌐 API (5 test)
- `test_api_endpoints.php` - Test tutti gli endpoint API
- `test_api_paths.php` - Verifica path resolution
- `test_api_status.php` - Status codes API
- `test_client_server_alignment.php` - Allineamento client/server
- `api/test_auth_direct.php` - Test auth API diretta

### 🗄️ Database (3 test)
- `test_db_connection.php` - Test connessione database
- `check_database.php` - Verifica struttura database
- `check_db_users.php` - Verifica utenti database

### 🎨 UI/UX (3 test)
- `test_menu_links.php` - Test navigazione menu
- `test_js_php_fixes.php` - Verifica fix JavaScript/PHP
- `test_fixes_complete.php` - Test fix completi UI

### 🔧 Sistema (7 test)
- `test.php` - Test base sistema
- `test_v2.php` - Test versione 2
- `test_error_handling.php` - Gestione errori
- `test_redirect_fix.php` - Fix redirect
- `test_tenant_simple.php` - Test tenant semplificato
- `verify_integrity.php` - Verifica integrità sistema
- `verify_part2.php` - Verifica Part 2

### 📦 Migrazione (4 test)
- `test_migration_part2.php` - Test migrazione Part 2
- `test_part2_system.php` - Sistema Part 2
- `test_part4_chat.php` - Chat module Part 4
- `verify_migration_part2.php` - Verifica migrazione

## 🎯 Come Eseguire i Test

### 1. Test Runner Web UI
Accedi a `http://localhost/Nexiosolution/collabora/test_runner.php` per:
- Dashboard visuale con statistiche
- Esecuzione test con un click
- Report dettagliati con output
- Filtro per categoria
- Progress bar e risultati real-time

### 2. Command Line Interface
```bash
# Test completo del sistema
php test_runner.php run

# Output esempio:
╔══════════════════════════════════════════════════════════════╗
║           NEXIO COLLABORA - TEST REPORT                       ║
╚══════════════════════════════════════════════════════════════╝

📊 STATISTICHE:
├─ Test Totali: 33
├─ Superati: 28
├─ Falliti: 5
├─ Tasso Successo: 84.8%
└─ Durata: 45.3s
```

### 3. Test Singolo
```bash
# Esegui un test specifico
php test_auth.php

# Con output dettagliato
php test_auth.php --verbose
```

### 4. Test per Categoria
```bash
# Solo test di autenticazione
php test_runner.php auth

# Solo test API
php test_runner.php api

# Solo test di sistema
php test_runner.php system
```

## 🔒 Sicurezza

### Controllo Accessi
- **Development**: Accesso libero da localhost
- **Staging**: Richiede autenticazione HTTP Basic
- **Production**: Test disabilitati completamente

### Configurazione Sicurezza
Modifica `/config/test.config.php`:

```php
// Abilita autenticazione in staging
define('TEST_REQUIRE_AUTH', true);

// Whitelist IP
$TEST_ALLOWED_IPS = [
    '192.168.1.0/24',  // Rete locale
    '10.0.0.5'         // IP specifico
];
```

## 📊 Report e Logging

### Report HTML
Accessibile da browser con:
- Statistiche complete
- Dettagli per ogni test
- Output espandibile
- Codice colori per status

### Log Files
Salvati in `/logs/tests/`:
```
test_activity_2025-01-20.log
test_results_2025-01-20.json
```

### Formato Log
```json
{
  "timestamp": "2025-01-20 15:30:45",
  "action": "run_test",
  "test": "test_auth.php",
  "result": "success",
  "duration": 0.234,
  "details": {...}
}
```

## 🛠️ Troubleshooting

### Errore 403 Forbidden
✅ **RISOLTO**: Il file `.htaccess` è stato aggiornato per permettere l'accesso ai test in development.

### Test Non Trovati
Verifica che i file test:
- Inizino con `test_`, `verify_`, `check_`, o `debug_`
- Abbiano estensione `.php`
- Siano nella root o nella directory `/tests/`

### Test Falliti
1. Controlla i log in `/logs/tests/`
2. Esegui il test singolo per output dettagliato
3. Verifica dipendenze database/file

### Performance Issues
- Limita test per categoria
- Aumenta `max_execution_time` in `test.config.php`
- Disabilita test esterni non necessari

## 🔄 Workflow Consigliato

### Pre-Commit
```bash
# Esegui test rapidi
php test_runner.php auth
php test_runner.php api
```

### Pre-Deploy
```bash
# Suite completa
php test_runner.php run

# Verifica integrità
php verify_integrity.php
```

### Post-Deploy
```bash
# Test smoke
php test_db_connection.php
php test_api_status.php
```

## 📈 Best Practices

1. **Isola i Test**: Ogni test deve essere indipendente
2. **Pulizia**: Rimuovi dati test dopo esecuzione
3. **Naming**: Usa nomi descrittivi (test_feature_scenario.php)
4. **Output**: Fornisci messaggi chiari di successo/errore
5. **Timeouts**: Imposta timeout appropriati per test lunghi

## 🆘 Supporto

Per problemi con i test:
1. Controlla questa documentazione
2. Verifica i log in `/logs/tests/`
3. Esegui `php test_runner.php` per diagnostica
4. Contatta il team DevOps

## 📝 Note Importanti

- **Test Database**: Usa `nexio_collabora_test` per evitare conflitti
- **Credenziali Test**: Definite in ogni test o in `test.config.php`
- **Cleanup**: I test devono ripulire i dati creati
- **CI/CD**: Integra `test_runner.php` nella pipeline

---
*Ultimo aggiornamento: 20 Gennaio 2025*
*Versione: 2.0*