#!/bin/bash

# =========================================
# NEXIO COLLABORA - API ERROR FIX SCRIPT
# Applies missing migrations to fix all 500 errors
# =========================================

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

echo -e "${BLUE}=====================================${NC}"
echo -e "${BLUE}NEXIO COLLABORA API ERROR FIX${NC}"
echo -e "${BLUE}=====================================${NC}"
echo

# Configuration
DB_NAME="nexio_collabora_v2"
DB_USER="root"
DB_PASS=""
PROJECT_PATH="/mnt/c/xampp/htdocs/Nexiosolution/collabora"

# Function to run MySQL command
run_mysql() {
    mysql -u"$DB_USER" "$@" 2>/dev/null
}

# Function to check if table exists
table_exists() {
    local table=$1
    result=$(run_mysql "$DB_NAME" -e "SHOW TABLES LIKE '$table';" | grep -c "$table")
    [ "$result" -gt 0 ]
}

echo -e "${YELLOW}[1/5] Checking MySQL availability...${NC}"
if ! run_mysql -e "SELECT VERSION();" > /dev/null; then
    echo -e "${RED}ERROR: MySQL is not running or not accessible${NC}"
    echo "Please ensure MySQL/MariaDB service is running"
    exit 1
fi
echo -e "${GREEN}✓ MySQL is running${NC}"

echo
echo -e "${YELLOW}[2/5] Creating backup...${NC}"
BACKUP_FILE="${PROJECT_PATH}/database/backup_before_fix_$(date +%Y%m%d_%H%M%S).sql"
if mysqldump -u"$DB_USER" "$DB_NAME" > "$BACKUP_FILE" 2>/dev/null; then
    echo -e "${GREEN}✓ Backup created: $BACKUP_FILE${NC}"
else
    echo -e "${YELLOW}Warning: Could not create backup${NC}"
fi

echo
echo -e "${YELLOW}[3/5] Applying Part 2 migrations (Calendars and Tasks)...${NC}"
if run_mysql "$DB_NAME" < "${PROJECT_PATH}/database/migrations_part2.sql"; then
    echo -e "${GREEN}✓ Part 2 migrations applied successfully${NC}"
else
    echo -e "${YELLOW}! Part 2 migrations may have already been applied or had warnings${NC}"
fi

echo
echo -e "${YELLOW}[4/5] Applying Part 4 migrations (Chat System)...${NC}"
if run_mysql "$DB_NAME" < "${PROJECT_PATH}/database/migrations_part4_chat.sql"; then
    echo -e "${GREEN}✓ Part 4 migrations applied successfully${NC}"
else
    echo -e "${YELLOW}! Part 4 migrations may have already been applied or had warnings${NC}"
fi

echo
echo -e "${YELLOW}[5/5] Verifying tables...${NC}"
echo

# Check required tables
declare -a required_tables=(
    "calendars"
    "events"
    "task_lists"
    "tasks"
    "chat_channels"
    "chat_channel_members"
    "chat_messages"
    "chat_presence"
)

all_good=true
for table in "${required_tables[@]}"; do
    if table_exists "$table"; then
        echo -e "${GREEN}✓${NC} $table - OK"
    else
        echo -e "${RED}✗${NC} $table - MISSING"
        all_good=false
    fi
done

echo
echo -e "${BLUE}=====================================${NC}"
if $all_good; then
    echo -e "${GREEN}FIX COMPLETE - ALL TABLES EXIST!${NC}"
else
    echo -e "${YELLOW}FIX PARTIALLY COMPLETE - Some tables are still missing${NC}"
fi
echo -e "${BLUE}=====================================${NC}"
echo

echo "You can now test the APIs:"
echo "- http://localhost/Nexiosolution/collabora/api/calendars.php"
echo "- http://localhost/Nexiosolution/collabora/api/events.php"
echo "- http://localhost/Nexiosolution/collabora/api/task-lists.php"
echo "- http://localhost/Nexiosolution/collabora/api/channels.php"
echo "- http://localhost/Nexiosolution/collabora/api/chat-poll.php"
echo "- http://localhost/Nexiosolution/collabora/api/messages.php"
echo
echo "To run diagnostics: php diagnose-api-errors.php"
echo