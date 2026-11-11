<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voice Call Notices</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px 10px 0 0; text-align: center; }
        h1 { margin: 0; font-size: 22px; }
        .content { background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; }
        .info-box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 14px; }
        .button { display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>&#9742; Voice Call Notices Are On</h1>
    </div>
    <div class="content">
        <p>Hello <strong>{{ $user->name }}</strong>,</p>
        <p>You have agreed to receive <strong>voice call notices</strong> on Allsers. Thank you — here is exactly what that means for you:</p>

        <div class="info-box">
            <p><strong>How it works</strong></p>
            <ul>
                <li>When a customer requests your services on Allsers, we place a short automated call to your phone.</li>
                <li>The call simply informs you: who requested your services, for what job, and reminds you to log in and accept the request.</li>
                <li>It is a one-time informational call — not a conversation. Nothing is discussed over the phone.</li>
                <li>Calls are placed between <strong>8:00 AM and 8:00 PM (West Africa Time)</strong>.</li>
            </ul>
        </div>

        <p><strong>How this helps you:</strong> service requests are time-sensitive. The voice notice means you hear about new jobs the moment they come in — even when you are not checking the app — so you can log in and accept the work before anyone else does.</p>

        <p>Your default call language is <strong>English</strong>. More African languages are coming soon.</p>

        <p>You can change your preference or stop voice call notices at any time from
            <strong>Settings &rsaquo; Profile &rsaquo; Voice Call Notices</strong>.</p>

        <div style="text-align: center;">
            <a href="{{ route('profile.edit') }}" class="button">Review Your Settings</a>
        </div>
    </div>
    <div class="footer">
        <p>This is an automated message from Allsers.</p>
        <p>&copy; {{ date('Y') }} Allsers. All rights reserved.</p>
    </div>
</body>
</html>