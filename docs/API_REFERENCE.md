
# Academia World API Reference

Welcome to the Academia World API documentation. This guide provides detailed information about the available endpoints for general users, covering authentication, event management, user interactions, and more.

## Authentication

Academia World uses a cookie-based authentication system powered by Laravel Sanctum. When a user successfully logs in, the API responds with an `HttpOnly` cookie containing the session information. This cookie must be included in all subsequent requests to authenticated endpoints.

### How It Works

1.  **Login Request**: Your application sends a `POST` request to `/api/v1/auth/login` with the user's credentials (email and password).
2.  **Cookie Response**: Upon successful authentication, the server sets a secure, `HttpOnly` cookie in the response. Your browser or client will automatically store this cookie.
3.  **Authenticated Requests**: For all subsequent requests to protected endpoints, the browser will automatically include the session cookie, authenticating the user.

### Key Headers

-   `Accept: application/json`
-   `Content-Type: application/json`
-   `X-XSRF-TOKEN`: For protection against Cross-Site Request Forgery (CSRF), you must include the value of the `XSRF-TOKEN` cookie in the `X-XSRF-TOKEN` header for all state-changing requests (`POST`, `PUT`, `DELETE`).

---

## Endpoints

All endpoints are prefixed with `/api/v1`.

### Authentication

| Method | URI | Description |
| --- | --- | --- |
| `POST` | `/auth/register` | Register a new user account. |
| `POST` | `/auth/login` | Authenticate a user and start a session. |
| `POST` | `/auth/logout` | Terminate the current user's session. (Authentication required) |
| `POST` | `/auth/verify-email` | Verify a user's email address using a token. |
| `POST` | `/auth/resend-verification` | Resend the email verification link. |
| `POST` | `/auth/forgot-password` | Request a password reset link. |
| `POST` | `/auth/reset-password` | Reset the user's password using a token. |
| `GET` | `/auth/user` | Get the currently authenticated user's profile. (Authentication required) |
| `POST` | `/auth/refresh` | Refresh the authentication session. (Authentication required) |

### User Profile

| Method | URI | Description |
| --- | --- | --- |
| `GET` | `/profile` | Get the authenticated user's profile. (Authentication required) |
| `PUT` | `/profile` | Update the authenticated user's profile. (Authentication required) |
| `POST` | `/profile/avatar` | Upload or update the user's avatar. (Authentication required) |
| `DELETE` | `/profile/avatar` | Delete the user's avatar. (Authentication required) |
| `GET` | `/profile/stats` | Get statistics for the authenticated user. (Authentication required) |

### Events

| Method | URI | Description |
| --- | --- | --- |
| `GET` | `/events` | Get a paginated list of all events. |
| `POST` | `/events` | Create a new event. (Authentication required) |
| `GET` | `/events/search` | Search for events based on a query. |
| `GET` | `/events/{event}` | Get details for a specific event. |
| `PUT` | `/events/{event}` | Update an event. (Authentication required, user must be owner) |
| `DELETE` | `/events/{event}` | Delete an event. (Authentication required, user must be owner) |
| `POST` | `/events/{event}/register` | Register the authenticated user for an event. (Authentication required) |
| `DELETE` | `/events/{event}/unregister` | Unregister the authenticated user from an event. (Authentication required) |
| `GET` | `/events/{event}/attendees` | Get a list of attendees for an event. (Authentication required) |
| `POST` | `/events/{event}/poster` | Upload a poster for an event. (Authentication required, user must be owner) |
| `DELETE` | `/events/{event}/poster` | Delete the poster for an event. (Authentication required, user must be owner) |
| `GET` | `/my-events` | Get a list of events created by the authenticated user. (Authentication required) |
| `GET` | `/my-registrations` | Get a list of events the authenticated user is registered for. (Authentication required) |
| `GET` | `/my-cancelled-registrations` | Get a list of cancelled event registrations for the authenticated user. (Authentication required) |

### Event Resources

| Method | URI | Description |
| --- | --- | --- |
| `GET` | `/events/{event}/resources` | Get a list of resources for a specific event. |
| `POST` | `/events/{event}/resources` | Upload a new resource for an event. (Authentication required, user must be owner) |
| `GET` | `/resources/{resource}` | Get details for a specific resource. |
| `PUT` | `/resources/{resource}` | Update a resource. (Authentication required, user must be owner) |
| `DELETE` | `/resources/{resource}` | Delete a resource. (Authentication required, user must be owner) |
| `GET` | `/resources/{resource}/download` | Download a specific resource. |

### Discussion Forums & Posts

| Method | URI | Description |
| --- | --- | --- |
| `GET` | `/events/{event}/forums` | Get a list of forums for a specific event. (Authentication required) |
| `POST` | `/events/{event}/forums` | Create a new forum for an event. (Authentication required, user must be owner) |
| `GET` | `/forums/{forum}` | Get details for a specific forum. (Authentication required) |
| `PUT` | `/forums/{forum}` | Update a forum. (Authentication required, user must be owner) |
| `DELETE` | `/forums/{forum}` | Delete a forum. (Authentication required, user must be owner) |
| `GET` | `/forums/{forum}/posts` | Get a list of posts in a specific forum. (Authentication required) |
| `POST` | `/forums/{forum}/posts` | Create a new post in a forum. (Authentication required) |
| `GET` | `/posts/{post}` | Get details for a specific post. (Authentication required) |
| `PUT` | `/posts/{post}` | Update a post. (Authentication required, user must be owner) |
| `DELETE` | `/posts/{post}` | Delete a post. (Authentication required, user must be owner) |
| `POST` | `/posts/{post}/like` | Toggle a like on a post. (Authentication required) |
| `POST` | `/posts/{post}/pin` | Toggle the pin status of a post. (Authentication required, forum owner only) |
| `POST` | `/posts/{post}/solution` | Mark a post as the solution. (Authentication required, forum owner only) |

### User Connections

| Method | URI | Description |
| --- | --- | --- |
| `GET` | `/connections` | Get a list of the authenticated user's connections. (Authentication required) |
| `POST` | `/connections` | Send a connection request to another user. (Authentication required) |
| `GET` | `/connections/pending` | Get a list of pending connection requests. (Authentication required) |
| `DELETE` | `/connections/{connection}` | Remove a connection or cancel a request. (Authentication required) |
| `PUT` | `/connections/{connection}/respond` | Respond to a pending connection request (accept or decline). (Authentication required) |
| `GET` | `/users/search` | Search for users to connect with. (Authentication required) |

---
