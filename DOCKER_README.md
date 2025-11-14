# Docker Setup for Mobility Inventory Management System

This document provides instructions on how to run the Mobility Inventory Management System using Docker.

## Prerequisites

- Docker installed on your system
- Docker Compose installed on your system

## Quick Start

1. Clone or download the repository
2. Navigate to the project root directory
3. Run the following command to start the application:

```bash
docker-compose up -d
```

4. Access the application at `http://localhost:8000`

## Services

The Docker Compose setup includes two services:

### Web Service
- PHP 8.1 with Apache
- All required PHP extensions for PostgreSQL
- Application code mounted as a volume for development

### Database Service
- PostgreSQL 13
- Pre-configured with the correct database name, user, and password
- Data persisted in a Docker volume

## Environment Variables

The application uses the following environment variables (configured in docker-compose.yml):

- Database URL: `postgresql://mobility_db_user:243j1kW3g4rlkksNDdMehLHpplQVRJTa@db:5432/mobility_db`

## Database Initialization

The database schema is automatically created when the PostgreSQL container starts for the first time. The schema is defined in `init-scripts/init-db.sql`.

## Accessing the Application

- Web Interface: http://localhost:8000
- PostgreSQL Database: localhost:5432

## Development

For development, the application code is mounted as a volume, so changes to the code will be reflected immediately without rebuilding the container.

To rebuild the containers:

```bash
docker-compose build
```

To stop the containers:

```bash
docker-compose down
```

To view logs:

```bash
docker-compose logs
```

## Makefile Commands

The project includes a Makefile with common commands:

```bash
make up          # Start all services in detached mode
make down        # Stop all services
make build       # Build all services
make rebuild     # Rebuild all services without using cache
make logs        # View logs for all services
make logs-web    # View logs for web service
make logs-db     # View logs for database service
make shell-web   # Open shell in web container
make shell-db    # Open shell in database container
make test        # Run tests
make clean       # Remove all containers, networks, and volumes
```

## Health Check

The application includes a health check endpoint at `http://localhost:8000/health-check.php` that verifies:
- PHP version and required extensions
- Database connectivity
- File system permissions

## Troubleshooting

If you encounter any issues:

1. Make sure Docker and Docker Compose are properly installed
2. Check that ports 8000 and 5432 are not being used by other applications
3. View the container logs for error messages:

```bash
docker-compose logs web
docker-compose logs db
```

## Security Note

The default database credentials are for development purposes only. In a production environment, you should change these credentials and use more secure passwords.