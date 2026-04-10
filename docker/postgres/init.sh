#!/bin/bash
set -e

# Parameterized PostgreSQL initialization script
# Uses psql --variable for safe parameter passing (prevents SQL injection)

DB_NAME="${POSTGRES_DB}"
DB_USER="${POSTGRES_USER}"

if [ -z "${DB_NAME}" ] || [ -z "${DB_USER}" ]; then
  echo "ERROR: POSTGRES_DB and POSTGRES_USER must be set"
  exit 1
fi

echo "Running parameterized init.sql for database '${DB_NAME}' and user '${DB_USER}'..."

# Use psql --variable to safely pass parameters (prevents SQL injection)
psql -v ON_ERROR_STOP=1 \
  --username "${POSTGRES_USER}" \
  --dbname "${DB_NAME}" \
  --variable "db_name=${DB_NAME}" \
  --variable "db_user=${DB_USER}" \
  -f /docker-entrypoint-initdb.d/init.sql.template

echo "Parameterized init completed successfully."
