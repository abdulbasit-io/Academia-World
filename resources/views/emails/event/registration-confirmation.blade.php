<x-mail::message>
# Event Registration Confirmation

Hello {{ $user->full_name }},

Thank you for registering for **{{ $event->title }}**! We're excited to have you join us.

## Event Details
- **Event:** {{ $event->title }}
- **Date:** {{ $event->start_date->format('F j, Y') }}
- **Time:** {{ $event->start_date->format('g:i A') }} - {{ $event->end_date->format('g:i A') }} ({{ $event->timezone }})
@if($event->location_type === 'physical')
- **Location:** {{ $event->location }}
@elseif($event->location_type === 'virtual')
- **Virtual Link:** [Join Event]({{ $event->virtual_link }})
@else
- **Location:** {{ $event->location }}
- **Virtual Link:** [Join Event]({{ $event->virtual_link }})
@endif

## What's Next?
- You'll receive reminder emails 24 hours and 1 hour before the event
- Check your email for any updates about the event
- Contact the organizer if you have any questions

<x-mail::button :url="config('app.frontend_url', config('app.url')) . '/events/' . $event->id">
View Event Details
</x-mail::button>

If you need to cancel your registration, please log in to your account and manage your registrations.

Best regards,<br>
{{ config('app.name') }} Team

---
**Event organized by:** {{ $event->host->full_name }} ({{ $event->host->institution }})
</x-mail::message>
