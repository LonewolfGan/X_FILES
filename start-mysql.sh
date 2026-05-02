#!/bin/bash
set -e

MARIADB_DIR=/nix/store/4kba2qp4a04j342l372clgm74x6180ix-mariadb-server-11.4.7
MYSQL_DATA=/home/runner/mysql-data
MYSQL_RUN=/home/runner/mysql-run

mkdir -p $MYSQL_DATA $MYSQL_RUN

cleanup() {
    if [ -n "$MPID" ]; then
        kill $MPID 2>/dev/null || true
    fi
}
trap cleanup EXIT

rm -f $MYSQL_RUN/mysqld.sock $MYSQL_RUN/mysqld.pid

# Bootstrap InnoDB if not yet initialized
if [ ! -f $MYSQL_DATA/ib_logfile0 ]; then
    echo "Initializing InnoDB storage..."
    echo "" | $MARIADB_DIR/bin/mariadbd --no-defaults \
        --datadir=$MYSQL_DATA \
        --lc-messages-dir=$MARIADB_DIR/share/mysql \
        --innodb_use_native_aio=0 \
        --bootstrap 2>&1
    echo "InnoDB initialized."
fi

echo "Starting MariaDB 11.4.7..."
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
MPID=$!

echo "MariaDB PID: $MPID"

# Wait for socket
READY=0
for i in $(seq 1 30); do
    if [ -S $MYSQL_RUN/mysqld.sock ]; then
        READY=1
        echo "MariaDB ready after ${i}s"
        break
    fi
    sleep 1
done

if [ "$READY" = "0" ]; then
    echo "ERROR: MariaDB failed to start. Log:"
    cat $MYSQL_DATA/error.log | tail -20
    exit 1
fi

MARIADB_CLI="$MARIADB_DIR/bin/mariadb --socket=$MYSQL_RUN/mysqld.sock -u root"

# Install system tables if not present
TABLES_COUNT=$($MARIADB_CLI -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='mysql';" 2>/dev/null | tail -1)
if [ -z "$TABLES_COUNT" ] || [ "$TABLES_COUNT" = "0" ]; then
    echo "Installing system tables..."
    $MARIADB_CLI mysql < $MARIADB_DIR/share/mysql/mariadb_system_tables.sql 2>&1 | grep -v "^$" | head -10
    $MARIADB_CLI mysql < $MARIADB_DIR/share/mysql/mariadb_system_tables_data.sql 2>&1 | grep -v "^$" | head -10
    echo "System tables installed."
fi

# Create xfiles database and load schema if not present
DB_EXISTS=$($MARIADB_CLI -e "SHOW DATABASES LIKE 'xfiles';" 2>/dev/null | grep -c 'xfiles' || true)
if [ "$DB_EXISTS" = "0" ]; then
    echo "Creating xfiles database..."
    $MARIADB_CLI -e "CREATE DATABASE IF NOT EXISTS xfiles CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    $MARIADB_CLI xfiles < /home/runner/workspace/projet.sql 2>&1 | head -20
    echo "xfiles database ready."
fi

echo "MariaDB setup complete. Keeping alive..."
wait $MPID
