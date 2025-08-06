# Academia World

A comprehensive academic event management and collaboration platform built with Laravel 12, designed to facilitate scholarly networking, event organization, and academic resource sharing.

[![Laravel Version](https://img.shields.io/badge/Laravel-12.x-red.svg)](https://laravel.com)
[![PHP Version](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![API Documentation](https://img.shields.io/badge/API-Documentation-orange.svg)](#api-documentation)

## 🎯 Overview

Academia World is a platform that empowers academic institutions, researchers, and educators to:


## 📸 Screenshots
![Academia World Dashboard](screenshots/dashboard.png)
![Event Creation Form](screenshots/event-form.png)
## 🏗️ Architecture

### Technology Stack
- **Backend**: Laravel 12 (PHP 8.2+)
- **Authentication**: Laravel Sanctum with API token management
- **Authorization**: Spatie Laravel Permission package
- **Database**: MySQL with UUID-based routing
- **API Documentation**: OpenAPI 3.0 with L5-Swagger
- **File Storage**: Laravel Storage with S3 support
- **Queue Management**: Laravel Queue with multiple drivers
- **Testing**: Pest PHP with comprehensive test coverage

### Key Features
- RESTful API with comprehensive documentation
- UUID-based resource identification for enhanced security
- Role-based access control with granular permissions
- Email verification and account management
- Real-time notifications and event reminders
- Comprehensive admin dashboard with audit logging
- Multi-format file upload and management (images, documents, videos)
- Event poster upload and management with automatic image processing
- Resource sharing with public URL access and download tracking
- Advanced search and filtering capabilities

## 📋 Prerequisites

Before installing Academia World, ensure your system meets the following requirements:

- **PHP**: 8.2 or higher
- **Composer**: 2.0 or higher
- **Node.js**: 18.0 or higher (for asset compilation)
- **Database**: MySQL 8.0+
- **Redis**: 6.0+ (recommended for caching and sessions)
- **Web Server**: Apache 2.4+ or Nginx 1.18+

### Required PHP Extensions
```bash
php -m | grep -E "(bcmath|ctype|fileinfo|json|mbstring|openssl|pdo|tokenizer|xml|gd|intl|zip)"
```

## 🚀 Installation

### 1. Clone the Repository
```bash
git clone https://github.com/abdulbasit-io/academia-world.git
cd academia-world
```

### 2. Install Dependencies
```bash
# Install PHP dependencies
composer install --optimize-autoloader --no-dev

# Install Node.js dependencies
npm install
```

### 3. Environment Configuration
```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 4. Configure Environment Variables
Edit your `.env` file with the following essential configurations:

```env
APP_NAME=Academia-world
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_FRONTEND_URL=http://localhost:3000

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

# APP_PREVIOUS_KEYS=
APP_MAINTENANCE_DRIVER=file
APP_MAINTENANCE_STORE=database

# PHP Server Configuration
PHP_CLI_SERVER_WORKERS=4
BCRYPT_ROUNDS=12

# Logging Configuration
LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_DEPRECATIONS_TRACE=false
LOG_LEVEL=debug
LOG_DAILY_DAYS=14
LOG_SLACK_WEBHOOK_URL=

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=
DB_PORT=
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
DB_URL=
DB_SOCKET=
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
DB_FOREIGN_KEYS=true

# Database Cache Configuration
DB_CACHE_CONNECTION=
DB_CACHE_TABLE=cache
DB_CACHE_LOCK_CONNECTION=
DB_CACHE_LOCK_TABLE=

# Queue Database Configuration
DB_QUEUE_CONNECTION=
DB_QUEUE_TABLE=jobs
DB_QUEUE=default
DB_QUEUE_RETRY_AFTER=90

# MySQL SSL Configuration
MYSQL_ATTR_SSL_CA=

# Session Configuration
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_EXPIRE_ON_CLOSE=false
SESSION_PATH=/
SESSION_DOMAIN=
SESSION_SECURE_COOKIE=
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_PARTITIONED_COOKIE=false
SESSION_CONNECTION=
SESSION_TABLE=sessions
SESSION_STORE=

# Broadcasting Configuration
BROADCAST_CONNECTION=log

# Filesystem Configuration
FILESYSTEM_DISK=local

# Queue Configuration
QUEUE_CONNECTION=database
QUEUE_FAILED_DRIVER=database-uuids


# Cache Configuration
CACHE_STORE=database
CACHE_PREFIX=

# Memcached Configuration
MEMCACHED_HOST=127.0.0.1
MEMCACHED_PORT=11211
MEMCACHED_PERSISTENT_ID=
MEMCACHED_USERNAME=
MEMCACHED_PASSWORD=

# Redis Configuration
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_URL=
REDIS_USERNAME=
REDIS_DB=0
REDIS_CACHE_DB=1
REDIS_CLUSTER=redis
REDIS_PREFIX=
REDIS_PERSISTENT=false

# Redis Cache Configuration
REDIS_CACHE_CONNECTION=cache
REDIS_CACHE_LOCK_CONNECTION=default

# Redis Queue Configuration
REDIS_QUEUE_CONNECTION=default
REDIS_QUEUE=default
REDIS_QUEUE_RETRY_AFTER=90

# Mail Configuration
MAIL_MAILER=
MAIL_HOST=
MAIL_PORT=
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_EHLO_DOMAIN=localhost
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME=
MAIL_LOG_CHANNEL=

# AWS Configuration
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_URL=
AWS_ENDPOINT=
AWS_USE_PATH_STYLE_ENDPOINT=false

# File Storage Configuration
FILE_STORAGE_DRIVER=auto [ auto, local, s3, cloudinary ]

# Cloudinary Configuration
CLOUDINARY_CLOUD_NAME=
CLOUDINARY_API_KEY=
CLOUDINARY_API_SECRET=

# Sanctum Configuration
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1
SANCTUM_TOKEN_PREFIX=


# Authentication Configuration
AUTH_GUARD=web
AUTH_PASSWORD_BROKER=users

# Vite Configuration
VITE_APP_NAME="${APP_NAME}"

# Telescope Configuration
TELESCOPE_ENABLED=true
TELESCOPE_QUEUE_CONNECTION=database
TELESCOPE_QUEUE=telescope
TELESCOPE_QUEUE_DELAY=10

```

### 5. Database Setup
```bash
# Run database migrations
php artisan migrate

# Seed the database with initial data
php artisan db:seed

# Create storage symlink
php artisan storage:link
```

### 6. Build Assets
```bash
# For production
npm run build

# For development
npm run dev
```

### 7. Generate API Documentation
```bash
php artisan l5-swagger:generate
```

### 8. Set Permissions (Linux/macOS)
```bash
# Set proper permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### 9. Start the Application
```bash
./start-dev.sh start
``` 
or
```bash
npm run start:api
```
or
```bash
php artisan serve --host=localhost --port=8000
```
## 📚 API Documentation

Academia World provides comprehensive API documentation through OpenAPI 3.0 specification.

### Documentation Access
- **Root URL**: `http://localhost:8000/`
- **Swagger UI**: `http://localhost:8000/api/documentation` (interactive)
- **API Reference**: [docs/API_REFERENCE.md](docs/API_REFERENCE.md) (detailed guide)
- **Production**: `https://your-domain.com/api/documentation`

> **Note**: The OpenAPI documentation is automatically generated from controller annotations. Run `php artisan l5-swagger:generate` to update after making changes.

### Authentication
The API uses Laravel Sanctum for authentication. Include the token in the Authorization header:

```bash
Authorization: Bearer your-api-token
```

### Key Endpoints

#### Authentication
- `POST /api/v1/auth/register` - User registration
- `POST /api/v1/auth/login` - User login
- `POST /api/v1/auth/logout` - User logout
- `POST /api/v1/auth/verify-email` - Email verification

#### Events
- `GET /api/v1/events` - List events
- `POST /api/v1/events` - Create event
- `GET /api/v1/events/{event}` - Get event details
- `PUT /api/v1/events/{event}` - Update event
- `DELETE /api/v1/events/{event}` - Delete event
- `POST /api/v1/events/{event}/register` - Register for event
- `DELETE /api/v1/events/{event}/unregister` - Unregister from event

#### Resource & File Upload Scenarios
Academia World supports resource/file uploads in three scenarios:
1. **Multipart Form Data**: Standard file upload via form (e.g., images, PDFs, docs)
2. **Base64-encoded JSON**: Upload files as base64 strings in JSON payloads
3. **Remote URL**: Provide a public URL for the platform to fetch and store the file

Endpoints supporting these scenarios:
- `POST /api/v1/events/{event}/poster` - Upload event poster (form or base64)
- `POST /api/v1/events/{event}/resources` - Upload event resource (form, base64, or remote URL)
- `PUT /api/v1/events/{event}/poster` - Update event poster
- `PUT /api/v1/resources/{resource}` - Update resource

#### Event Resources
- `GET /api/v1/events/{event}/resources` - List event resources
- `POST /api/v1/events/{event}/resources` - Upload event resource
- `GET /api/v1/resources/{resource}` - Get resource details
- `PUT /api/v1/resources/{resource}` - Update resource
- `DELETE /api/v1/resources/{resource}` - Delete resource
- `GET /api/v1/resources/{resource}/download` - Download resource

#### Forums
- `GET /api/v1/events/{event}/forums` - List event forums
- `POST /api/v1/events/{event}/forums` - Create forum
- `GET /api/v1/forums/{forum}` - Get forum details

#### User Connections
- `GET /api/v1/connections` - List connections
- `POST /api/v1/connections/request` - Send connection request
- `POST /api/v1/connections/{connection}/accept` - Accept connection

### API Examples

#### Register a New User
```bash
curl -X POST https://your-domain.com/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "first_name": "John",
    "last_name": "Doe",
    "email": "john.doe@university.edu",
    "password": "securepassword123",
    "password_confirmation": "securepassword123",
    "institution": "University of Technology",
    "department": "Computer Science",
    "position": "Professor"
  }'
```

#### Create an Event
```bash
curl -X POST https://your-domain.com/api/v1/events \
  -H "Authorization: Bearer your-api-token" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "title": "AI in Academic Research Conference",
    "description": "Annual conference on artificial intelligence applications in academic research",
    "start_date": "2025-09-15T09:00:00Z",
    "end_date": "2025-09-17T17:00:00Z",
    "location_type": "hybrid",
    "location": "University Conference Center",
    "capacity": 200,
    "status": "published"
  }'
```

#### Upload Event Poster (Multipart Form Data)
```bash
curl -X POST https://your-domain.com/api/v1/events/{event_uuid}/poster \
  -H "Authorization: Bearer your-api-token" \
  -H "Accept: application/json" \
  -F "poster=@/path/to/poster.jpg"
```

#### Upload Event Poster (Base64 JSON)
```bash
curl -X POST https://your-domain.com/api/v1/events/{event_uuid}/poster \
  -H "Authorization: Bearer your-api-token" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "poster": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQ..."
  }'
```

#### Upload Event Resource
```bash
curl -X POST https://your-domain.com/api/v1/events/{event_uuid}/resources \
  -H "Authorization: Bearer your-api-token" \
  -H "Accept: application/json" \
  -F "file=@/path/to/presentation.pdf" \
  -F "title=Conference Presentation" \
  -F "description=Main keynote presentation slides" \
  -F "resource_type=presentation" \
  -F "is_public=true" \
  -F "is_downloadable=true" \
  -F "requires_registration=false"
```

#### Delete Event Poster
```bash
curl -X DELETE https://your-domain.com/api/v1/events/{event_uuid}/poster \
  -H "Authorization: Bearer your-api-token" \
  -H "Accept: application/json"
```

## 🧪 Testing

Academia World includes comprehensive test coverage using Pest PHP.

### Running Tests
```bash
# Run all tests
php artisan test

# Run tests with coverage
php artisan test --coverage

# Run specific test suite
php artisan test --testsuite=Feature

# Run tests in parallel
php artisan test --parallel
```

### Test Categories
- **Unit Tests**: Model logic, services, and utilities
- **Feature Tests**: API endpoints, authentication, and user flows
- **Integration Tests**: Database interactions and external services

## 🚀 Deployment

### Production Deployment Checklist

1. **Environment Setup**
   - [ ] Configure production `.env` file
   - [ ] Set `APP_ENV=production` and `APP_DEBUG=false`
   - [ ] Configure proper database credentials
   - [ ] Set up Redis for caching and sessions

2. **Security**
   - [ ] Generate new `APP_KEY`
   - [ ] Configure HTTPS/SSL certificates
   - [ ] Set up proper file permissions
   - [ ] Configure firewall rules

3. **Performance Optimization**
   - [ ] Enable OPcache
   - [ ] Configure queue workers
   - [ ] Set up cron jobs for scheduled tasks
   - [ ] Optimize database queries

4. **Monitoring**
   - [ ] Configure logging
   - [ ] Set up error monitoring (Sentry, Bugsnag, etc.)
   - [ ] Implement health checks
   - [ ] Configure backup procedures

### Docker Deployment (Optional)
```dockerfile
FROM php:8.2-fpm-alpine

# Install dependencies and PHP extensions
RUN apk add --no-cache nginx supervisor curl zip unzip git

# Copy application files
COPY . /var/www/html
WORKDIR /var/www/html

# Install Composer and dependencies
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --optimize-autoloader --no-dev

# Set permissions
RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
```


## 🔧 Development

### Local Development Setup
Use the provided script for a complete development environment:
```bash
./start-dev.sh
```
This will start the Laravel server, run queue workers, and launch Vite for asset hot-reloading. For manual steps:
```bash
php artisan serve         # Start Laravel server
npm run dev               # Start Vite dev server
php artisan queue:work    # Run queue worker
php artisan l5-swagger:generate # Generate API docs
```

### Debugging and Monitoring
Laravel Telescope is enabled for local development:
`http://localhost:8000/telescope`

### IDE Support & Static Analysis
Generate IDE helper files and run static analysis:
```bash
composer ide-helper
php artisan ide-helper:generate
php artisan ide-helper:models --write
php artisan ide-helper:meta
./vendor/bin/phpstan analyse
./vendor/bin/pint
```

### Common Issues
- IDE helper files are excluded from git
- Use `./scripts/dev-setup.sh` for permissions, cache, and optimization

### Testing
Run all tests:
```bash
php artisan test
```


## 🚀 Production Deployment

### Production Checklist
1. Configure `.env` for production:
   - `APP_ENV=production`, `APP_DEBUG=false`, secure credentials
2. Set up Redis, queue workers, and cron jobs
3. Enable HTTPS/SSL and OPcache
4. Set file permissions and firewall rules
5. Monitor logs and errors (Sentry/Bugsnag recommended)

### Docker Deployment (Optional)
```dockerfile
FROM php:8.2-fpm-alpine
RUN apk add --no-cache nginx supervisor curl zip unzip git
COPY . /var/www/html
WORKDIR /var/www/html
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --optimize-autoloader --no-dev
RUN chown -R www-data:www-data storage bootstrap/cache
EXPOSE 80
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
```