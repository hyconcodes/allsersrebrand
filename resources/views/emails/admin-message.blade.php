<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px 10px 0 0; text-align: center; }
        h1 { margin: 0; font-size: 22px; }
        .content { background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; }
        .message-box { background: white; padding: 24px; border-radius: 8px; margin: 20px 0; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .message-title { font-size: 16px; font-weight: 700; color: #111827; margin: 0 0 10px; }
        .message-body { color: #374151; font-size: 15px; white-space: pre-wrap; word-wrap: break-word; margin: 0; }
        .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 14px; }
        .button { display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>A message from the Allsers team</h1>
    </div>
    <div class="content">
        <p>Hello <strong>{{ $user->name }}</strong>,</p>
        <div class="message-box">
            <h2 class="message-title">{{ $title }}</h2>
            <p class="message-body">{!! nl2br(e($messageBody)) !!}</p>
        </div>
        <p>If you have any questions about this message, please contact <a href="mailto:support@allsers.com">support@allsers.com</a>.</p>
        <div style="text-align:center;"><a href="{{ url('/') }}" class="button">Open Allsers</a></div>
    </div>
    <div class="footer">
        <p>This message was sent to you by an administrator of Allsers.</p>
        <p>&copy; {{ date('Y') }} Allsers. All rights reserved.</p>
    </div>
</body>
</html>
