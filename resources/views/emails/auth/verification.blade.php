<x-mail::message>
# Email Verification Required

Hello {{ $user->full_name }},

Welcome to **Academia World**! To complete your registration and start using the platform, please verify your email address.

## Why Verify?
- Access all platform features
- Receive important event notifications
- Ensure account security
- Connect with the academic community

<x-mail::button :url="$verificationUrl">
Verify Email Address
</x-mail::button>

This verification link will expire in 60 minutes for security reasons. If you didn't create this account, please ignore this email.

Need help? Contact our support team.

Best regards,<br>
{{ config('app.name') }} Team

---
**Note:** This link is unique to your account and should not be shared.
</x-mail::message>
