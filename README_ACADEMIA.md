# Academia World - Academic Event Management Platform

A comprehensive Laravel-based platform for managing academic events, conferences, and workshops with advanced user management and administrative controls.

## 🌟 Features

### Core Functionality
- **User Management**: Registration, authentication, profile management
- **Event Management**: Create, update, delete academic events
- **Event Registration**: Users can register/unregister for events
- **Admin Moderation**: Ban/unban events, force delete, comprehensive logging
- **Email Notifications**: Event confirmations, reminders, admin notifications
- **Search & Filtering**: Advanced event discovery capabilities

### Technical Highlights
- **Laravel 12**: Latest Laravel framework
- **API-First Design**: RESTful API with comprehensive endpoints
- **Queue System**: Background job processing for emails and notifications
- **Role-Based Access**: Admin and user role management
- **UUID-Based**: Secure UUID-based entity identification
- **Comprehensive Testing**: 131 passing tests with 551 assertions

## 🧪 Test Coverage

- ✅ **Unit Tests**: 71 tests (Models, Jobs, Mail)
- ✅ **Feature Tests**: 60 tests (API endpoints, authentication, admin)
- ✅ **100% Test Success Rate**: All core functionality verified
- ✅ **Email Testing**: Queue-based email system fully tested
- ✅ **Admin Controls**: All moderation features tested

## 🔧 API Endpoints

### Authentication
- `POST /api/v1/auth/register` - User registration
- `POST /api/v1/auth/login` - User login
- `POST /api/v1/auth/logout` - User logout
- `GET /api/v1/auth/user` - Get current user
- `POST /api/v1/auth/verify-email` - Email verification

### Events
- `GET /api/v1/events` - List events (with search/filter)
- `POST /api/v1/events` - Create event
- `GET /api/v1/events/{uuid}` - Get event details
- `PUT /api/v1/events/{uuid}` - Update event
- `DELETE /api/v1/events/{uuid}` - Delete event
- `POST /api/v1/events/{uuid}/register` - Register for event
- `DELETE /api/v1/events/{uuid}/unregister` - Unregister from event

### User Profile
- `GET /api/v1/profile` - Get user profile
- `PUT /api/v1/profile` - Update profile
- `GET /api/v1/profile/stats` - Get user statistics
- `POST /api/v1/profile/avatar` - Upload avatar

### Admin (Admin only)
- `POST /api/v1/admin/events/{uuid}/ban` - Ban event
- `POST /api/v1/admin/events/{uuid}/unban` - Unban event
- `DELETE /api/v1/admin/events/{uuid}/force-delete` - Force delete event

## 🚀 Getting Started

### Prerequisites
- PHP 8.2+
- Composer
- MySQL/PostgreSQL
- Node.js (for frontend assets)

### Installation
1. Clone the repository
2. `composer install`
3. `cp .env.example .env`
4. Configure database in `.env`
5. `php artisan key:generate`
6. `php artisan migrate --seed`
7. `php artisan serve`

### Running Tests
```bash
# Run all tests
php artisan test

# Run specific test suites
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature

# Run with coverage
php artisan test --coverage
```

## 📁 Project Structure

```
app/
├── Http/Controllers/API/    # API controllers
├── Models/                  # Eloquent models
├── Jobs/                   # Background jobs
├── Mail/                   # Email classes
└── Providers/              # Service providers

tests/
├── Unit/                   # Unit tests
│   ├── Jobs/              # Job testing
│   ├── Mail/              # Email testing
│   └── Models/            # Model testing
└── Feature/               # Integration tests
    ├── Auth/              # Authentication tests
    ├── Events/            # Event management tests
    ├── User/              # User profile tests
    └── Admin/             # Admin functionality tests
```

## 🛡️ Security Features
- Token-based authentication (Laravel Sanctum)
- Rate limiting on API endpoints
- Input validation and sanitization
- CSRF protection
- SQL injection prevention
- XSS protection

## 📊 Quality Metrics
- **131 passing tests** with **551 assertions**
- **0 failing tests**
- **Full CRUD coverage** for all entities
- **Email system testing** with queue verification
- **Admin workflow testing** with proper authorization
- **API endpoint testing** with authentication

## 🎯 Next Steps
- Frontend development (React/Vue.js)
- Real-time notifications
- Calendar integration
- File upload optimization
- Performance monitoring
- Deployment automation

---

Built with ❤️ using Laravel 12
