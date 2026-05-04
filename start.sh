#!/bin/bash
set -e

MARIADB_DIR=/nix/store/a4jsa8kjdn3wlccj2wkvhxqza38rpxzf-mariadb-server-10.11.13
MYSQL_DATA=/home/runner/mysql-data
MYSQL_RUN=/home/runner/mysql-run
MARIADB_CLI="$MARIADB_DIR/bin/mariadb --socket=$MYSQL_RUN/mysqld.sock -u root"

mkdir -p $MYSQL_DATA $MYSQL_RUN
rm -f $MYSQL_RUN/mysqld.sock $MYSQL_RUN/mysqld.pid

# Step 1: Bootstrap InnoDB if not yet initialized
if [ ! -f $MYSQL_DATA/ib_logfile0 ]; then
    echo "[MySQL] Initializing InnoDB storage..."
    echo "" | $MARIADB_DIR/bin/mariadbd --no-defaults \
        --datadir=$MYSQL_DATA \
        --lc-messages-dir=$MARIADB_DIR/share/mysql \
        --innodb_use_native_aio=0 \
        --bootstrap 2>/dev/null
    echo "[MySQL] InnoDB initialized."
fi

# Step 2: Start MariaDB in foreground (this is the main process)
echo "[MySQL] Starting MariaDB 10.11.13..."
$MARIADB_DIR/bin/mariadbd --no-defaults \
    --datadir=$MYSQL_DATA \
    --socket=$MYSQL_RUN/mysqld.sock \
    --port=3306 \
    --lc-messages-dir=$MARIADB_DIR/share/mysql \
    --innodb_use_native_aio=0 \
    --skip-name-resolve \
    --bind-address=127.0.0.1 \
    --log-error=$MYSQL_DATA/error.log \
    --skip-grant-tables < /dev/null &
MARIADB_PID=$!

# Step 3: Wait for socket
echo "[MySQL] Waiting for socket..."
for i in $(seq 1 30); do
    if [ -S $MYSQL_RUN/mysqld.sock ]; then
        echo "[MySQL] Ready after ${i}s"
        break
    fi
    sleep 1
done

if [ ! -S $MYSQL_RUN/mysqld.sock ]; then
    echo "[MySQL] ERROR: Failed to start. Check $MYSQL_DATA/error.log"
    exit 1
fi

# Step 4: Install system tables if missing
TABLES_COUNT=$($MARIADB_CLI -se "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='mysql';" 2>/dev/null || echo "0")
if [ "$TABLES_COUNT" = "0" ]; then
    echo "[MySQL] Installing system tables..."
    $MARIADB_CLI -e "CREATE DATABASE IF NOT EXISTS mysql;" 2>/dev/null || true
    $MARIADB_CLI mysql < $MARIADB_DIR/share/mysql/mysql_system_tables.sql 2>/dev/null || true
    $MARIADB_CLI mysql < $MARIADB_DIR/share/mysql/mysql_system_tables_data.sql 2>/dev/null || true
    echo "[MySQL] System tables installed."
fi

# Step 5: Create xfiles database if missing
DB_EXISTS=$($MARIADB_CLI -se "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='xfiles';" 2>/dev/null || echo "0")
if [ "$DB_EXISTS" = "0" ]; then
    echo "[MySQL] Creating xfiles database..."
    $MARIADB_CLI -e "CREATE DATABASE IF NOT EXISTS xfiles CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null
    $MARIADB_CLI xfiles < /home/runner/workspace/projet.sql 2>/dev/null
    echo "[MySQL] xfiles database ready."
fi

echo "[MySQL] Setup complete."

# Step 6: Start PHP built-in server on port 5000
echo "[PHP] Starting PHP server on port 5000..."
exec php -S 0.0.0.0:5000 -t /home/runner/workspace /home/runner/workspace/router.php
