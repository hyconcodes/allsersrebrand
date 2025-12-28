<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Suspended</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px 10px 0 0;
            text-align: center;
        }

        .content {
            background: #f9fafb;
            padding: 30px;
            border-radius: 0 0 10px 10px;
        }

        .alert-box {
            background: #fee;
            border-left: 4px solid #dc2626;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }

        .info-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 14px;
        }

        h1 {
            margin: 0;
            font-size: 24px;
        }

        .button {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>⚠️ Account Suspended</h1>
    </div>

    <div class="content">
        <p>Hello <strong>{{ $user->name }}</strong>,</p>

        <div class="alert-box">
            <strong>Your Allsers account has been temporarily suspended.</strong>
        </div>

        <div class="info-box">
            <p><strong>Suspension Details:</strong></p>
            <ul>
                <li><strong>Account:</strong> {{ $user->email }}</li>
                <li><strong>Suspended Until:</strong> {{ $bannedUntil->format('F j, Y g:i A') }}</li>
                @if($reason)
                    <li><strong>Reason:</strong> {{ $reason }}</li>
                @endif
            </ul>
        </div>

        <p>During this suspension period, you will not be able to:</p>
        <ul>
            <li>Access your account</li>
            <li>Create or view posts</li>
            <li>Interact with other users</li>
        </ul>

        <p>Your account will be automatically reactivated on <strong>{{ $bannedUntil->format('F j, Y') }}</strong> at
            <strong>{{ $bannedUntil->format('g:i A') }}</strong>.</p>

        <p>If you believe this suspension was made in error, please contact our support team.</p>

        <div style="text-align: center;">
            <a href="mailto:support@allsers.com" class="button">Contact Support</a>
        </div>
    </div>

    <div class="footer">
        <p>This is an automated message from Allsers.</p>
        <p>&copy; {{ date('Y') }} Allsers. All rights reserved.</p>
    </div>
</body>

</html>