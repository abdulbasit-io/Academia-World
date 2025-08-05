# Academia World

A comprehensive academic event management and collaboration platform built with Laravel 12, designed to facilitate scholarly networking, event organization, and academic resource sharing.

[![Laravel Version](https://img.shields.io/badge/Laravel-12.x-red.svg)](https://laravel.com)
[![PHP Version](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![API Documentation](https://img.shields.io/badge/API-Documentation-orange.svg)](#api-documentation)

## 🎯 Overview

Academia World is a platform that empowers academic institutions, researchers, and educators to:

- **Event Management**: Create, manage, and participate in academic conferences, workshops, and seminars with poster upload
- **Academic Networking**: Connect with fellow researchers and build professional relationships
- **Resource Sharing**: Upload, share, and access academic resources and materials with direct download links
- **Discussion Forums**: Engage in academic discourse through structured discussion forums
- **User Management**: Comprehensive user profiles with institutional affiliations and academic credentials

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
# Application
APP_NAME="Academia World"
APP_ENV=production
APP_KEY=base64:your-generated-key
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=academia_world
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

# Redis (Recommended)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="${APP_NAME}"

# File Storage
FILESYSTEM_DISK=local
# For S3 storage (optional)
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket-name

# API Documentation
L5_SWAGGER_GENERATE_ALWAYS=false
L5_SWAGGER_CONST_HOST=https://your-domain.com

# Sanctum
SANCTUM_STATEFUL_DOMAINS=your-frontend-domain.com
SESSION_DOMAIN=.your-domain.com

# Queue Worker (Production)
QUEUE_CONNECTION=redis
QUEUE_FAILED_DRIVER=database-uuids
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

## 🔧 Configuration

### Queue Workers (Production)
For production environments, set up queue workers to handle background jobs:

```bash
# Create supervisor configuration
sudo nano /etc/supervisor/conf.d/academia-world-worker.conf
```

```ini
[program:academia-world-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/academia-world/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=8
redirect_stderr=true
stdout_logfile=/path/to/academia-world/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
# Update supervisor and start workers
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start academia-world-worker:*
```

### Scheduled Tasks
Add the Laravel scheduler to your crontab:

```bash
# Edit crontab
crontab -e

# Add this line
* * * * * cd /path/to/academia-world && php artisan schedule:run >> /dev/null 2>&1
```

### Web Server Configuration

#### Nginx Configuration
```nginx
server {
    listen 80;
    listen [::]:80;
    server_name your-domain.com;
    root /path/to/academia-world/public;
    
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    
    index index.php;
    
    charset utf-8;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
    
    error_page 404 /index.php;
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

## 📚 API Documentation

Academia World provides comprehensive API documentation through OpenAPI 3.0 specification.

### Documentation Access
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

#### Event Poster Management
- `POST /api/v1/events/{event}/poster` - Upload event poster
- `PUT /api/v1/events/{event}/poster` - Update event poster
- `DELETE /api/v1/events/{event}/poster` - Delete event poster

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
```bash
# Start development server
php artisan serve

# Watch for file changes
npm run dev

# Run queue worker in development
php artisan queue:work

# Generate API documentation
php artisan l5-swagger:generate
```

### Debugging and Monitoring

#### Laravel Telescope
This project uses Laravel Telescope for debugging and insight into requests, exceptions, database queries, and more during local development.

You can access the Telescope dashboard at:
`http://localhost:8000/telescope`

**Note:** Telescope is only enabled in the local development environment for security reasons.

### IDE Support & Static Analysis

Academia World includes comprehensive IDE support to minimize false positives and improve developer experience:

```bash
# Generate IDE helper files (run after model changes)
composer ide-helper

# Or run individual commands:
php artisan ide-helper:generate
php artisan ide-helper:models --write
php artisan ide-helper:meta

# Use the development setup script for comprehensive setup
./scripts/dev-setup.sh all

# Or run specific tasks:
./scripts/dev-setup.sh ide        # Regenerate IDE helpers
./scripts/dev-setup.sh permissions # Fix file permissions
./scripts/dev-setup.sh cache      # Clear caches
./scripts/dev-setup.sh optimize   # Development optimization
```

### Avoiding Common Issues

**IDE/Static Analysis Warnings:**
- Laravel IDE Helper package is installed and configured
- PHPStan configuration includes rules to ignore common Eloquent false positives
- IDE helper files are automatically excluded from git tracking

**Read-Only File Conflicts:**
- IDE helper files are excluded from version control
- Development script handles file permissions automatically
- Clear regeneration process prevents stale file issues

### Code Quality Tools
```bash
# Run code analysis with Larastan
./vendor/bin/phpstan analyse

# Format code with Laravel Pint
./vendor/bin/pint

# Run tests
php artisan test
```

## 📖 User Guide

### For Administrators
- Manage users, events, and system settings
- View audit logs and analytics
- Configure platform permissions

### For Event Organizers
- Create and manage academic events
- Upload and share resources
- Moderate discussion forums
- Track event analytics

### For Participants
- Browse and register for events
- Connect with other academics
- Participate in discussions
- Access shared resources


### Development Guidelines
- Follow PSR-12 coding standards
- Write comprehensive tests for new features
- Update API documentation for endpoint changes
- Ensure all tests pass before submitting PR

## 🆘 Support

### Documentation
- [API Documentation](https://your-domain.com/api/documentation)
- [Admin Guide](docs/ADMIN_GUIDE.md)