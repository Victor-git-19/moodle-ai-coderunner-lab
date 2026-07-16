#!/usr/bin/env bash
set -euo pipefail

MOODLE_DIR=/var/www/html
MOODLE_DATA=/var/www/moodledata
CONFIG_FILE="$MOODLE_DIR/config.php"

# Без этих значений автоматическая установка была бы неполной.
required_variables=(
    DB_NAME DB_USER DB_PASSWORD MOODLE_URL MOODLE_SITE_NAME AI_SERVICE_URL
    MOODLE_ADMIN_USER MOODLE_ADMIN_PASSWORD MOODLE_ADMIN_EMAIL
)

for variable in "${required_variables[@]}"; do
    if [[ -z "${!variable:-}" ]]; then
        echo "Missing required environment variable: $variable" >&2
        exit 1
    fi
done

echo "Waiting for MariaDB..."
until php -r '
    mysqli_report(MYSQLI_REPORT_OFF);
    $db = @new mysqli("db", getenv("DB_USER"), getenv("DB_PASSWORD"), getenv("DB_NAME"));
    exit($db->connect_errno ? 1 : 0);
'; do
    sleep 3
done

mkdir -p "$MOODLE_DATA"
chown -R www-data:www-data "$MOODLE_DATA"

# config.php создаётся один раз и затем сохраняется в volume moodledata.
if [[ ! -f "$CONFIG_FILE" ]]; then
    cat > "$CONFIG_FILE" <<'PHP'
<?php
unset($CFG);
global $CFG;
$CFG = new stdClass();
$CFG->dbtype = 'mariadb';
$CFG->dblibrary = 'native';
$CFG->dbhost = 'db';
$CFG->dbname = getenv('DB_NAME');
$CFG->dbuser = getenv('DB_USER');
$CFG->dbpass = getenv('DB_PASSWORD');
$CFG->prefix = 'mdl_';
$CFG->dboptions = [
    'dbpersist' => false,
    'dbport' => '',
    'dbsocket' => false,
    'dbcollation' => 'utf8mb4_unicode_ci',
];
$CFG->wwwroot = rtrim(getenv('MOODLE_URL'), '/');
$CFG->dataroot = '/var/www/moodledata';
$CFG->admin = 'admin';
$CFG->directorypermissions = 02777;
require_once(__DIR__ . '/lib/setup.php');
PHP
    chown root:www-data "$CONFIG_FILE"
    chmod 640 "$CONFIG_FILE"
fi

# Наличие таблицы mdl_config отличает новый стенд от уже установленного.
if ! php -r '
    mysqli_report(MYSQLI_REPORT_OFF);
    $db = @new mysqli("db", getenv("DB_USER"), getenv("DB_PASSWORD"), getenv("DB_NAME"));
    $result = $db->query("SELECT 1 FROM mdl_config LIMIT 1");
    exit($result ? 0 : 1);
' >/dev/null 2>&1; then
    echo "Installing Moodle 5.2.1..."
    runuser -u www-data -- php "$MOODLE_DIR/admin/cli/install_database.php" \
        --agree-license \
        --lang=en \
        "--fullname=$MOODLE_SITE_NAME" \
        "--shortname=$MOODLE_SITE_NAME" \
        "--adminuser=$MOODLE_ADMIN_USER" \
        "--adminpass=$MOODLE_ADMIN_PASSWORD" \
        "--adminemail=$MOODLE_ADMIN_EMAIL"
fi

echo "Upgrading Moodle and plugins..."
runuser -u www-data -- php "$MOODLE_DIR/admin/cli/upgrade.php" --non-interactive

# В Moodle 5 CodeRunner добавляет стандартные прототипы отдельной фоновой
# задачей. Выполняем именно эту штатную задачу до установки учебного курса,
# иначе свежая база ещё не знает прототип python3.
runuser -u www-data -- php "$MOODLE_DIR/admin/cli/adhoc_task.php" \
    --execute \
    --classname='\qtype_coderunner\task\qtype_coderunner_setup_question_prototypes' \
    --ignorelimits \
    --force

runuser -u www-data -- php "$MOODLE_DIR/admin/cli/cfg.php" \
    --component=qtype_coderunner --name=jobe_host --set=jobe
runuser -u www-data -- php "$MOODLE_DIR/admin/cli/cfg.php" \
    --component=local_aicodehelper --name=endpoint --set="$AI_SERVICE_URL"
runuser -u www-data -- php "$MOODLE_DIR/admin/cli/cfg.php" \
    --component=local_aicodehelper --name=timeout --set="${AI_TIMEOUT:-60}"

echo "Checking the demo Python course..."
# Установщик находит курс по shortname, добавляет недостающее и не создаёт копию.
runuser -u www-data -- env MOODLE_CONFIG="$CONFIG_FILE" \
    php /opt/python-course/install.php

runuser -u www-data -- php "$MOODLE_DIR/admin/cli/purge_caches.php"

echo "Moodle is ready."
exec "$@"
