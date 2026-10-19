<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Allsers</title>
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
            text-align: left;
        }

        .lila-row {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }

        .lila-avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            border: 3px solid #6d28d9;
            background-color: #f5f3ff;
            flex-shrink: 0;
        }

        .lila-intro h2 {
            margin: 0;
            font-size: 20px;
            color: #1e293b;
        }

        .lila-intro p {
            margin: 4px 0 0;
            font-size: 14px;
            color: #64748b;
        }

        .content p {
            font-size: 17px;
            line-height: 1.6;
            color: #334155;
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 13px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #6d28d9;
            margin: 32px 0 12px;
        }

        .benefit-list {
            list-style: none;
            padding: 0;
            margin: 0 0 28px;
        }

        .benefit-list li {
            position: relative;
            padding: 10px 0 10px 36px;
            font-size: 16px;
            line-height: 1.5;
            color: #334155;
            border-bottom: 1px solid #f1f5f9;
        }

        .benefit-list li:last-child {
            border-bottom: none;
        }

        .benefit-list li::before {
            content: "✓";
            position: absolute;
            left: 0;
            top: 10px;
            width: 24px;
            height: 24px;
            border-radius: 8px;
            background-color: #ede9fe;
            color: #6d28d9;
            font-size: 14px;
            font-weight: 900;
            text-align: center;
            line-height: 24px;
        }

        .business-card {
            background-color: #fffbeb;
            border: 2px solid #fcd34d;
            border-radius: 24px;
            padding: 24px;
            margin: 8px 0 28px;
            text-align: center;
        }

        .business-card h3 {
            margin: 0 0 8px;
            font-size: 18px;
            font-weight: 900;
            color: #92400e;
        }

        .business-card p {
            font-size: 15px;
            color: #78350f;
            margin-bottom: 14px;
        }

        .missing-chips {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 8px;
            margin-bottom: 18px;
        }

        .missing-chips span {
            display: inline-block;
            background-color: #ffffff;
            border: 1px solid #fcd34d;
            color: #92400e;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 12px;
            border-radius: 100px;
        }

        .button {
            display: inline-block;
            background-color: #6d28d9;
            color: #ffffff !important;
            padding: 18px 40px;
            border-radius: 100px;
            font-size: 15px;
            font-weight: 900;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 10px 20px rgba(109, 40, 217, 0.3);
            margin: 8px 6px;
        }

        .button-secondary {
            display: inline-block;
            background-color: #ffffff;
            color: #6d28d9 !important;
            padding: 17px 40px;
            border-radius: 100px;
            border: 2px solid #6d28d9;
            font-size: 15px;
            font-weight: 900;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 8px 6px;
        }

        .buttons {
            text-align: center;
            margin-top: 8px;
        }

        .signature {
            text-align: center;
            padding-top: 28px;
            margin-top: 28px;
            border-top: 1px solid #f1f5f9;
        }

        .signature .lila-avatar {
            width: 56px;
            height: 56px;
            margin: 0 auto 10px;
            display: block;
        }

        .signature p {
            margin: 0;
            font-size: 16px;
            font-weight: 900;
            color: #1e293b;
        }

        .signature span {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #94a3b8;
            margin-top: 2px;
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
            <div class="badge">Welcome to Allsers</div>
            <h1>Welcome, artisan!</h1>
        </div>
        <div class="content">
            <div class="lila-row">
                <img class="lila-avatar" src="{{ asset('assets/lila-avatar.png') }}" alt="Lilia from Allsers">
                <div class="lila-intro">
                    <h2>Hi, I&rsquo;m Lilia!</h2>
                    <p>Your Allsers guide &mdash; let&rsquo;s get you discovered by clients.</p>
                </div>
            </div>

            <p>
                <strong>Allsers</strong> is a services marketplace that connects skilled professionals
                like you with clients who need them. Your talent deserves to be seen &mdash; and
                Allsers puts you in front of people actively looking for your skills.
            </p>

            <div class="section-title">How Allsers helps your work grow</div>
            <ul class="benefit-list">
                <li>Get discovered by clients searching for your exact skills</li>
                <li>Receive service inquiries straight to your inbox</li>
                <li>Build your reputation with reviews, badges and a showcase</li>
                <li>Join challenges to win visibility and rewards</li>
                <li>Manage everything from one simple dashboard</li>
            </ul>

            @if ($showBusinessPrompt)
                <div class="business-card">
                    <h3>Complete your business information</h3>
                    <p>
                        Clients trust complete profiles &mdash; and search results favor them.
                        Yours is still missing a few details:
                    </p>
                    <div class="missing-chips">
                        @foreach (array_slice($completion['missing'] ?? [], 0, 8) as $missing)
                            <span>{{ $missing['label'] }}</span>
                        @endforeach
                    </div>
                    <a href="{{ route('profile.edit') }}" class="button">Update My Business Info</a>
                </div>
            @endif

            <div class="buttons">
                @if (! $showBusinessPrompt)
                    <a href="{{ route('profile.edit') }}" class="button">View My Profile</a>
                @endif
                <a href="{{ route('dashboard') }}" class="button-secondary">Go to Dashboard</a>
            </div>

            <div class="signature">
                <img class="lila-avatar" src="{{ asset('assets/lila-avatar.png') }}" alt="Lilia">
                <p>Lilia from Allsers.</p>
                <span>Can&rsquo;t wait to see your work shine.</span>
            </div>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Allsers. Empowering Local Talent.</p>
        </div>
    </div>
</body>

</html>
