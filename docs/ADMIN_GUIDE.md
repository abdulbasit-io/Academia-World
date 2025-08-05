
# Academia World Admin Guide

Welcome to the administrator's guide for Academia World. This document provides a comprehensive overview of all administrative routes, functionalities, and how to use them to manage the platform effectively.

## Authentication

All administrative routes are protected and require two levels of authentication:

1.  **Cookie-Based Authentication**: Like general users, administrators must be authenticated via a session cookie obtained by logging in.
2.  **Admin Middleware**: In addition to being logged in, the user must have administrative privileges to access these endpoints.

Requests to admin endpoints will fail if the user is not a designated administrator.

---

## Admin Endpoints

All admin endpoints are prefixed with `/api/v1/admin`.

### Dashboard & Platform Health

| Method | URI | Description |
| --- | --- | --- |
| `GET` | `/dashboard` | Retrieves aggregated data for the admin dashboard. |
| `GET` | `/platform-health` | Checks the health of the platform, including database and service connections. |
| `GET` | `/logs` | Retrieves admin action logs for auditing purposes. |

### User Management

| Method | URI | Description |
| --- | --- | --- |
| `GET` | `/users` | Get a paginated list of all users on the platform. |
| `POST` | `/users` | Create a new administrator account. |
| `PUT` | `/users/{user}/ban` | Ban a user from the platform. |
| `PUT` | `/users/{user}/unban` | Unban a previously banned user. |
| `POST` | `/users/{user}/promote` | Promote a regular user to an administrator. |
| `POST` | `/users/{user}/demote` | Demote an administrator to a regular user. |

### Event Management

| Method | URI | Description |
| --- | --- | --- |
| `GET` | `/events` | Get a paginated list of all events on the platform. |
| `GET` | `/events/{event}` | Get details for a specific event. |
| `DELETE` | `/events/{event}` | Soft delete an event. |
| `DELETE` | `/events/{event}/force-delete` | Permanently delete an event. **Use with caution.** |
| `POST` | `/events/{event}/ban` | Ban an event, making it inaccessible to users. |
| `POST` | `/events/{event}/unban` | Unban a previously banned event. |
| `PUT` | `/events/{event}/status` | Update the status of an event (e.g., `published`, `draft`, `completed`). |

### Forum & Post Management

| Method | URI | Description |
| --- | --- | --- |
| `GET` | `/posts` | Get a paginated list of all forum posts. |
| `DELETE` | `/posts/{post}` | Soft delete a specific forum post. |

### Analytics

| Method | URI | Description |
| --- | --- | --- |
| `GET` | `/analytics/overview` | Get an overview of platform analytics. |
| `GET` | `/analytics/user-engagement` | Get detailed analytics on user engagement. |
| `GET` | `/analytics/event-engagement` | Get detailed analytics on event engagement. |
| `GET` | `/analytics/forum-activity` | Get detailed analytics on forum activity. |
| `POST` | `/analytics/generate-daily` | Manually trigger the generation of daily analytics reports. |

---
