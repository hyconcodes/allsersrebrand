<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Opportunity on Allsers</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }

        .container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 32px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(109, 40, 217, 0.1);
        }

        .header {
            background: linear-gradient(135deg, #6d28d9 0%, #4f46e5 100%);
            padding: 60px 40px;
            text-align: center;
        }

        .badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 100px;
            color: #ffffff;
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 24px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .header h1 {
            color: #ffffff;
            font-size: 32px;
            font-weight: 900;
            margin: 0;
            letter-spacing: -1px;
        }

        .content {
            padding: 40px;
            text-align: center;
        }

        .content p {
            font-size: 18px;
            line-height: 1.6;
            color: #334155;
            margin-bottom: 32px;
        }

        .artisan-card {
            background-color: #f1f5f9;
            border-radius: 24px;
            padding: 24px;
            margin-bottom: 32px;
            display: flex;
            align-items: center;
            gap: 20px;
            text-align: left;
        }

        .avatar {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            background-color: #6d28d9;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 24px;
            flex-shrink: 0;
        }

        .artisan-info h3 {
            margin: 0;
            font-size: 18px;
            color: #1e293b;
        }

        .artisan-info p {
            margin: 4px 0 0;
            font-size: 14px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .button {
            display: inline-block;
            background-color: #6d28d9;
            color: #ffffff !important;
            padding: 20px 48px;
            border-radius: 100px;
            font-size: 16px;
            font-weight: 900;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 10px 20px rgba(109, 40, 217, 0.3);
            transition: transform 0.2s;
        }

        .footer {
            padding: 40px;
            text-align: center;
            border-top: 1px solid #f1f5f9;
            background-color: #fafafa;
        }

        .footer p {
            font-size: 14px;
            color: #94a3b8;
            margin: 0;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <div class="badge">New Ping Received</div>
            <h1>A New Potential Client!</h1>
        </div>
        <div class="content">
            <p>Hi <strong>{{ $recipient->name }}</strong>,</p>
            <p>{{ $messageText }}</p>

            <div class="artisan-card">
                <div class="avatar">
                    @if ($sender->profile_picture_url)
                        <img src="{{ $sender->profile_picture_url }}"
                            style="width: 100%; height: 100%; object-fit: cover; border-radius: 16px;">
                    @else
                        {{ $sender->initials() }}
                    @endif
                </div>
                <div class="artisan-info">
                    <h3>{{ $sender->name }}</h3>
                    <p>Wants to connect with you</p>
                </div>
            </div>

            <a href="{{ config('app.url') }}/dashboard" class="button">Connect Now</a>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Allsers. Empowering Local Talent.</p>
        </div>
    </div>
</body>

</html>
