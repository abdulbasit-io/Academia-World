<x-mail::message>
# Reset Your Password

Hello {{ $user->name }},

We received a request to reset your password for your **Academia World** account. If you didn't make this request, please ignore this email.

## Reset Instructions
Click the button below to reset your password. This link will expire in 60 minutes for security reasons.

<x-mail::button :url="$resetUrl">
Reset Password
</x-mail::button>

If you're having trouble clicking the button, copy and paste the URL below into your web browser:
{{ $resetUrl }}

## Security Notice
- This link will expire in 60 minutes
- If you didn't request this reset, your account is still secure
- For assistance, contact our support team

Best regards,  
The Academia World Team
</x-mail::message>
