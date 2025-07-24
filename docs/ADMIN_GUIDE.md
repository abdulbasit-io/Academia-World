# Academia World - Administrator Guide

## Table of Contents

1. [Overview](#overview)
2. [Getting Started](#getting-started)
3. [Admin Dashboard](#admin-dashboard)
4. [User Management](#user-management)
5. [Event Management](#event-management)
6. [Content Moderation](#content-moderation)
7. [Analytics & Monitoring](#analytics--monitoring)
8. [Platform Health](#platform-health)
9. [Audit Logs](#audit-logs)
10. [API Access](#api-access)

---

## Overview

The Academia World platform provides comprehensive administrative tools for managing academic events, users, and platform operations. This guide covers all administrative functions available to platform administrators.

### Admin Access Levels

- **Super Admin**: Full platform access including user promotion/demotion
- **Admin**: Standard administrative access for content moderation and user management
- **Event Moderator**: Limited access for event-specific moderation

### Key Administrative Areas

- User account management and moderation
- Event oversight and content control
- Forum and discussion moderation
- Platform analytics and health monitoring
- System configuration and maintenance

---

## Getting Started

### Prerequisites

1. **Admin Account**: You must have an account with `is_admin = true` in the database
2. **API Access**: Admin functions are accessible via REST API endpoints
3. **Authentication**: All admin endpoints require Bearer token authentication

### Initial Setup

1. **Verify Admin Status**:
   ```bash
   GET /api/v1/auth/user
   # Verify "is_admin": true in response
   ```

2. **Access Dashboard**:
   ```bash
   GET /api/v1/admin/dashboard
   # Initial overview of platform statistics
   ```

### Authentication Headers

All admin API requests must include:
```bash
Authorization: Bearer {your_admin_token}
Content-Type: application/json
```

---

## Admin Dashboard

The admin dashboard provides a comprehensive overview of platform activity and health metrics.

### Dashboard Components

#### Platform Overview
```bash
GET /api/v1/admin/dashboard
```

**Response includes**:
- **User Statistics**: Total users, new registrations, active users
- **Event Metrics**: Total events, upcoming events, registration counts
- **Content Overview**: Forum posts, discussions, resource uploads
- **Recent Activity**: Latest user registrations, events, admin actions

#### Real-time Metrics
- **Active Sessions**: Current logged-in users
- **Live Events**: Events currently in progress
- **Recent Activity**: User actions in last 24 hours
- **System Performance**: Response times, error rates

#### Quick Actions Panel
- **User Management**: Ban/unban users, promote to admin
- **Event Moderation**: Suspend events, manage reports
- **Content Review**: Review flagged posts and discussions

---

## User Management

Comprehensive tools for managing user accounts, permissions, and security.

### User Listing and Search

#### Get All Users
```bash
GET /api/v1/admin/users
```

**Query Parameters**:
- `search`: Search by name or email
- `is_admin`: Filter by admin status (true/false)
- `is_banned`: Filter by ban status (true/false)
- `per_page`: Results per page (default: 15)
- `page`: Page number

#### Search Users
```bash
GET /api/v1/admin/users?search=john&is_banned=false
```

### User Moderation Actions

#### Ban/Unban User
```bash
PUT /api/v1/admin/users/{user_uuid}/ban
Content-Type: application/json

{
    "reason": "Violating community guidelines"
}
```

**Ban Features**:
- **Automatic Logging**: All ban actions are logged with admin ID and reason
- **Reason Required**: Must provide reason for all ban/unban actions
- **Admin Protection**: Cannot ban other admin users
- **Reversible**: Unban by calling same endpoint with unbanned user

#### Promote User to Admin
```bash
POST /api/v1/admin/users/{user_uuid}/promote
```

**Promotion Process**:
- Sets `is_admin = true` for the user
- Creates audit log entry with severity: critical
- Grants full admin privileges immediately
- Cannot promote already admin users

#### Demote Admin to User
```bash
POST /api/v1/admin/users/{user_uuid}/demote
```

**Demotion Restrictions**:
- Cannot demote yourself
- Requires admin privileges to execute
- Logs action with severity: warning
- Immediately revokes admin access

### User Account Security

#### Account Status Monitoring
- **Active**: Normal user account
- **Banned**: Suspended account with restricted access
- **Pending**: Awaiting email verification

#### Bulk User Operations
- **Mass Email**: Send notifications to user groups
- **Batch Actions**: Apply actions to multiple users
- **Export Data**: Download user lists and statistics

---

## Event Management

Administrative oversight for all platform events and content.

### Event Oversight

#### List All Events
```bash
GET /api/v1/admin/events
```

**Filtering Options**:
- `search`: Search event titles and descriptions
- `status`: Filter by event status (published, cancelled, suspended)
- `host_id`: Filter by event host
- `per_page`: Results per page

#### Event Status Management
```bash
PUT /api/v1/admin/events/{event_uuid}/status
Content-Type: application/json

{
    "status": "suspended",
    "reason": "Content review required"
}
```

**Available Statuses**:
- `published`: Active and visible to users
- `cancelled`: Event cancelled by host or admin
- `suspended`: Temporarily hidden for review

### Event Moderation

#### Ban Event
```bash
POST /api/v1/admin/events/{event_uuid}/ban
Content-Type: application/json

{
    "reason": "Inappropriate content detected"
}
```

**Ban Effects**:
- Event becomes invisible to non-admin users
- Existing registrations are preserved
- Host receives notification of ban
- Detailed logging for audit trail

#### Unban Event
```bash
POST /api/v1/admin/events/{event_uuid}/unban
```

**Unban Process**:
- Restores event to previous status
- Notifies host of reinstatement
- Logs admin action and reason
- Resumes normal event operations

#### Force Delete Event
```bash
DELETE /api/v1/admin/events/{event_uuid}/force-delete
```

**⚠️ WARNING**: This is a destructive operation that:
- Permanently deletes event and all data
- Removes all user registrations
- Deletes associated resources and files
- Cannot be undone

**Use Cases**:
- Illegal or harmful content
- Copyright violations
- Legal compliance requirements

### Event Analytics

#### Event Performance Metrics
- **Registration Rates**: Signup trends and conversion
- **Attendance Tracking**: Show rates and engagement
- **User Feedback**: Ratings and reviews analysis
- **Host Performance**: Event quality metrics
---

## Content Moderation

Tools for managing forum discussions, posts, and user-generated content.

### Forum Management

#### List Forum Posts
```bash
GET /api/v1/admin/forum-posts
```

**Filtering Options**:
- `forum_id`: Specific discussion forum
- `reported`: Only flagged posts
- `user_id`: Posts by specific user
- `date_range`: Posts within time period

#### Delete Forum Post
```bash
DELETE /api/v1/admin/forum-posts/{post_id}
Content-Type: application/json

{
    "reason": "Spam content removed"
}
```

**Deletion Features**:
- **Soft Delete**: Post marked as deleted but data preserved
- **Notification**: Author receives deletion notice
- **Audit Trail**: Full deletion history maintained
- **Restore Option**: Can be restored if needed


## Analytics & Monitoring

Comprehensive platform analytics and performance monitoring tools.

### Platform Analytics

#### Overview Analytics
```bash
GET /api/v1/admin/analytics/overview?days=30
```

**Key Metrics**:
- **User Growth**: Registration trends and retention
- **Event Activity**: Creation and participation rates
- **Content Generation**: Posts, discussions, resources
- **Platform Engagement**: User activity patterns

#### User Engagement Analytics
```bash
GET /api/v1/admin/analytics/user-engagement?start_date=2024-01-01&end_date=2024-01-31
```

**Engagement Metrics**:
- **Daily Active Users**: Login and activity tracking
- **Session Duration**: Time spent on platform
- **Feature Usage**: Most used platform features
- **Retention Rates**: User return patterns

#### Event Analytics
```bash
GET /api/v1/admin/analytics/event-engagement?days=7
```

**Event Metrics**:
- **Registration Conversion**: View-to-registration rates
- **Event Success**: Completion and satisfaction rates
- **Popular Categories**: Most active event types
- **Geographic Distribution**: Event locations and attendance

### Forum Activity Analytics
```bash
GET /api/v1/admin/analytics/forum-activity?days=14
```

**Discussion Metrics**:
- **Post Volume**: Daily posting rates
- **Active Discussions**: Engaging conversation threads
- **User Participation**: Forum engagement levels
- **Moderation Activity**: Content review statistics

### Real-time Monitoring

#### Active User Tracking
- **Current Sessions**: Live user count
- **Active Events**: Ongoing events and participation
- **System Load**: Server performance metrics
- **Error Monitoring**: Real-time error tracking

#### Custom Analytics

#### Generate Daily Metrics
```bash
POST /api/v1/admin/analytics/generate-daily?date=2024-01-15
```

**Manual Metric Generation**:
- **User Engagement**: Daily active user calculations
- **Event Activity**: Event creation and participation
- **Forum Metrics**: Discussion and post analytics
- **System Performance**: Response time and availability

---

## Platform Health

Monitor system health, performance, and operational metrics.

### Health Monitoring

#### Platform Health Check
```bash
GET /api/v1/admin/platform-health
```

**Health Metrics**:
- **Database Status**: Connection and performance
- **Active Users (24h)**: Recent user activity
- **System Resources**: Memory, storage, CPU usage
- **Event Statistics**: Active and upcoming events
- **User Metrics**: Total, banned, and pending users

#### System Performance Indicators

**Database Health**:
- **Connection Status**: Database availability
- **Query Performance**: Average response times
- **Storage Usage**: Disk space utilization
- **Backup Status**: Last backup timestamp

**Application Health**:
- **Memory Usage**: Current memory consumption
- **CPU Load**: System processing load
- **Queue Status**: Background job processing
- **Error Rates**: Application error frequency

### Monitoring Alerts

#### Automated Alerts
- **High Error Rates**: Spike in application errors
- **Database Issues**: Connection or performance problems
- **Security Incidents**: Suspicious login attempts
- **Resource Limits**: Server capacity warnings

#### Alert Configuration
- **Thresholds**: Set custom alert triggers
- **Notification Channels**: Email, SMS, webhook alerts
- **Escalation Rules**: Alert escalation procedures
- **Response Procedures**: Automated response actions

### Maintenance Windows

#### Scheduled Maintenance
- **Database Optimization**: Query optimization and indexing
- **Cache Clearing**: Memory and file cache maintenance
- **Log Rotation**: System log management
- **Security Updates**: System and dependency updates

#### Emergency Maintenance
- **Immediate Response**: Critical issue resolution
- **User Communication**: Maintenance notifications
- **Service Recovery**: System restoration procedures
- **Post-Incident Review**: Improvement identification

---

## Audit Logs

Comprehensive logging and audit trail management for all administrative actions.

### Admin Activity Logs

#### View Admin Logs
```bash
GET /api/v1/admin/logs
```

**Query Parameters**:
- `action`: Filter by specific action type
- `severity`: Filter by severity level
- `admin_id`: Filter by specific administrator
- `days`: Limit to recent days
- `per_page`: Results per page

#### Log Filtering Examples
```bash
# User management actions
GET /api/v1/admin/logs?action=user_ban

# Critical severity events
GET /api/v1/admin/logs?severity=critical

# Specific admin activity
GET /api/v1/admin/logs?admin_id={admin_uuid}&days=7
```

### Log Categories

#### User Management Logs
- **user_ban**: User account suspension
- **user_unban**: User account reinstatement
- **user_promote**: User promotion to admin
- **user_demote**: Admin demotion to user
- **admin_create**: New administrator creation

#### Event Management Logs
- **event_ban**: Event suspension/ban
- **event_unban**: Event reinstatement
- **event_status_change**: Event status modification
- **event_delete**: Event deletion
- **event_force_delete**: Permanent event removal

#### Content Moderation Logs
- **content_delete**: Forum post deletion
- **post_delete**: Specific post removal
- **content_review**: Content review actions
- **content_restore**: Content restoration

### Log Data Structure

Each log entry contains:
```json
{
    "id": "log_uuid",
    "admin_id": "admin_uuid",
    "action": "user_ban",
    "target_type": "user",
    "target_id": "target_uuid",
    "description": "Banned user: John Doe",
    "changes": {
        "before": {"is_banned": false},
        "after": {"is_banned": true}
    },
    "metadata": {
        "reason": "Violating community guidelines",
        "ip_address": "192.168.1.1"
    },
    "severity": "warning",
    "created_at": "2024-01-15T10:30:00Z"
}
```

### Log Retention

#### Retention Policies
- **Critical Logs**: Permanent retention
- **Warning Logs**: 2 years retention
- **Info Logs**: 1 year retention
- **Debug Logs**: 90 days retention

#### Archive Management
- **Export Functionality**: Download log archives
- **Compression**: Automatic log compression
- **External Storage**: Cloud backup integration
- **Legal Compliance**: Regulatory requirement adherence

---

## API Access

Direct API access for administrative operations and integration.

### Authentication

#### Admin API Token
```bash
POST /api/v1/auth/login
Content-Type: application/json

{
    "email": "admin@university.edu",
    "password": "secure_password"
}
```

**Response includes**:
- `access_token`: Bearer token for API requests
- `token_type`: "Bearer"
- `expires_in`: Token expiration time
- `user`: User object with admin status

#### Token Management
- **Expiration**: Tokens expire for security
- **Refresh**: Use refresh endpoint to extend
- **Revocation**: Logout to invalidate tokens
- **Multiple Sessions**: Support for multiple active tokens

### API Rate Limiting

#### Admin Endpoints
- **Higher Limits**: Increased rate limits for admin users
- **Burst Handling**: Temporary rate limit increases
- **Priority Queue**: Admin requests processed first
- **Monitoring**: Rate limit usage tracking

#### Rate Limit Headers
```bash
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1609459200
```

### API Documentation

#### Swagger Documentation
Access the interactive API documentation at:
```
{base_url}/api/documentation
```

**Features**:
- **Interactive Testing**: Test API endpoints directly
- **Schema Validation**: Request/response validation
- **Authentication**: Built-in token management
- **Code Examples**: Sample requests in multiple languages

#### Admin-Specific Endpoints

**User Management**:
- `GET /api/v1/admin/users` - List users with filtering
- `PUT /api/v1/admin/users/{uuid}/ban` - Ban/unban user
- `POST /api/v1/admin/users/{uuid}/promote` - Promote to admin

**Event Management**:
- `GET /api/v1/admin/events` - List all events
- `PUT /api/v1/admin/events/{uuid}/status` - Update event status
- `DELETE /api/v1/admin/events/{uuid}` - Delete event

**Analytics**:
- `GET /api/v1/admin/analytics/overview` - Platform overview
- `GET /api/v1/admin/analytics/user-engagement` - User metrics
- `GET /api/v1/admin/platform-health` - System health
