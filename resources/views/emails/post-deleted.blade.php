<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Removed</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%); color: white; padding: 30px; border-radius: 10px 10px 0 0; text-align: center; }
        .content { background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; }
        .info-box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 14px; }
        .alert-box { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; border-radius: 4px; }
        h1 { margin: 0; font-size: 24px; }
        .button { display: inline-block; padding: 12px 24px; background: #6a11cb; color: white; text-decoration: none; border-radius: 6px; margin-top: 20px; }
        .excerpt { font-style: italic; color: #6b7280; background: #f3f4f6; padding: 12px; border-radius: 6px; border-left: 3px solid #6a11cb; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Your post was removed</h1>
    </div>
    <div class="content">
        <p>Hello <strong>{{ $user->name }}</strong>,</p>
        <div class="alert-box">
            <strong>One of your posts on Allsers has been removed by an administrator.</strong>
        </div>
        <div class="info-box">
            <p><strong>Details:</strong></p>
            <p class="excerpt">"{{ \Illuminate\Support\Str::limit($postExcerpt, 300) }}"</p>
            @if($reason)
                <p style="margin-top:12px"><strong>Reason:</strong> {{ $reason }}</p>
            @endif
        </div>
        <p>If you believe this was a mistake, please reply to this email or contact <a href="mailto:support@allsers.com">support@allsers.com</a>.</p>
        <p>Please review our community guidelines to avoid future removals.</p>
        <div style="text-align:center;"><a href="mailto:support@allsers.com" class="button">Contact Support</a></div>
    </div>
    <div class="footer">
        <p>This is an automated message from Allsers.</p>
        <p>&copy; {{ date('Y') }} Allsers. All rights reserved.</p>
    </div>
</body>
</html>
