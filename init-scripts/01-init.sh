#!/bin/bash
# This script will run when the PostgreSQL container starts

# The database schema is defined in init-db.sql and will be executed automatically
# by PostgreSQL when the container starts for the first time

echo "PostgreSQL initialization script running..."
echo "Database schema will be created automatically from init-db.sql"