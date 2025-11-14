#!/bin/bash

# Render startup script

# Install PHP dependencies
echo "Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader

# Run database migrations if needed
echo "Running database migrations..."
# Check if database is already initialized by checking if users table exists
# This is a simplified check - in production, you might want a more robust solution
php init-db-render.php

# Start the application
echo "Starting the application..."
php -S 0.0.0.0:$PORT