<x-mail::message>
# Event Reminder: {{ $event->title }}

Hello {{ $user->full_name }},

@if($reminderType === '24h')
This is a friendly reminder that you have an event **tomorrow**!
@else
Your event is starting **in 1 hour**! Don't forget to join us.
@endif

## Event Details
- **Event:** {{ $event->title }}
- **Date:** {{ $event->start_date->format('F j, Y') }}
- **Time:** {{ $event->start_date->format('g:i A') }} - {{ $event->end_date->format('g:i A') }} ({{ $event->timezone }})
@if($event->location_type === 'physical')
- **Location:** {{ $event->location }}
@elseif($event->location_type === 'virtual')
- **Virtual Meeting:** [Join Now]({{ $event->virtual_link }})
@else
- **Location:** {{ $event->location }}
- **Virtual Meeting:** [Join Now]({{ $event->virtual_link }})
@endif

@if($event->requirements)
## What to Bring/Prepare
{{ $event->requirements }}
@endif

@if($event->location_type === 'virtual')
<x-mail::button :url="$event->virtual_link">
Join Virtual Event
</x-mail::button>
@else
<x-mail::button :url="config('app.frontend_url', config('app.url')) . '/events/' . $event->id">
View Event Details
</x-mail::button>
@endif

We look forward to seeing you there!

Best regards,<br>
{{ config('app.name') }} Team

---
**Event organized by:** {{ $event->host->full_name }} ({{ $event->host->institution }})
</x-mail::message>
