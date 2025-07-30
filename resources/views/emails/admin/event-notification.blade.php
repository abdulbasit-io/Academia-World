<x-mail::message>
# Admin Notification: {{ $notificationType }}

Hello Admin,

@if($notificationType === 'new_event')
A new event has been published on the platform.
@elseif($notificationType === 'new_registration')
A new user has registered for an event.
@elseif($notificationType === 'event_cancelled')
An event has been cancelled by the organizer.
@endif

## Event Details
- **Event:** {{ $event->title }}
- **Organizer:** {{ $event->host->full_name }} ({{ $event->host->institution }})
- **Date:** {{ $event->start_date->format('F j, Y g:i A') }}
@if($notificationType === 'new_registration' && isset($user))
- **New Participant:** {{ $user->full_name }} ({{ $user->institution }})
@endif

@if($event->description)
## Description
{{ Str::limit($event->description, 200) }}
@endif

<x-mail::button :url="config('app.frontend_url', config('app.url')) . '/admin/events/' . $event->uuid">
View in Admin Panel
</x-mail::button>

This is an automated notification from the Academia World platform.

Best regards,<br>
{{ config('app.name') }} System
</x-mail::message>
