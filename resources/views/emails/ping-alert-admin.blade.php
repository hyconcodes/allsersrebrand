<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Ping on Allsers</title>
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
            padding: 48px 40px;
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
            font-size: 28px;
            font-weight: 900;
            margin: 0;
            letter-spacing: -1px;
        }

        .header .subtitle {
            color: rgba(255, 255, 255, 0.85);
            font-size: 15px;
            margin: 12px 0 0;
        }

        .content {
            padding: 32px 40px;
        }

        .meta {
            background-color: #f1f5f9;
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 28px;
            font-size: 14px;
            color: #475569;
            line-height: 1.7;
        }

        .meta strong {
            color: #1e293b;
        }

        .section-label {
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #6d28d9;
            margin: 0 0 12px;
        }

        .party-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .party-head {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 14px;
        }

        .avatar {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background-color: #6d28d9;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 20px;
            flex-shrink: 0;
            overflow: hidden;
        }

        .party-head h3 {
            margin: 0;
            font-size: 17px;
            color: #1e293b;
        }

        .party-head .role {
            margin: 2px 0 0;
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .contact-grid {
            display: table;
            width: 100%;
            border-spacing: 0 6px;
            font-size: 14px;
        }

        .contact-row {
            display: table-row;
        }

        .contact-row .label {
            display: table-cell;
            color: #94a3b8;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 1px;
            padding-right: 16px;
            white-space: nowrap;
            width: 1%;
            vertical-align: top;
        }

        .contact-row .value {
            display: table-cell;
            color: #334155;
            word-break: break-word;
        }

        .contact-row .value a {
            color: #6d28d9;
            text-decoration: none;
            font-weight: 700;
        }

        .task-box {
            background: linear-gradient(135deg, #6d28d9 0%, #4f46e5 100%);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 28px;
            color: #ffffff;
        }

        .task-box .section-label {
            color: rgba(255, 255, 255, 0.75);
        }

        .task-box h3 {
            margin: 0 0 8px;
            font-size: 17px;
            font-weight: 900;
        }

        .task-box p {
            margin: 0;
            font-size: 14px;
            line-height: 1.6;
            color: rgba(255, 255, 255, 0.9);
        }

        .task-tags {
            margin-top: 14px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .task-tags span {
            display: inline-block;
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 100px;
            padding: 4px 12px;
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .button {
            display: block;
            text-align: center;
            background-color: #6d28d9;
            color: #ffffff !important;
            padding: 18px 48px;
            border-radius: 100px;
            font-size: 15px;
            font-weight: 900;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 10px 20px rgba(109, 40, 217, 0.3);
            transition: transform 0.2s;
        }

        .footer {
            padding: 32px 40px;
            text-align: center;
            border-top: 1px solid #f1f5f9;
            background-color: #fafafa;
        }

        .footer p {
            font-size: 13px;
            color: #94a3b8;
            margin: 0;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <div class="badge">Admin Ping Alert</div>
            <h1>New Ping on Allsers</h1>
            <p class="subtitle">{{ $pinger->name }} pinged {{ $artisan->name }} &mdash; {{ $engagement->created_at->format('M j, Y \a\t g:i A') }}</p>
        </div>
        <div class="content">

            <div class="task-box">
                <p class="section-label">The task</p>
                <h3>{{ $engagement->title }}</h3>
                @if ($engagement->location_context)
                    <p><em>Location: {{ $engagement->location_context }}</em></p>
                @endif
                <div class="task-tags">
                    @if ($engagement->urgency_level)
                        <span>Urgency: {{ $engagement->urgency_level }}</span>
                    @endif
                    <span>Status: {{ $engagement->status }}</span>
                    <span>{{ $engagement->created_at->format('M j, Y g:i A') }}</span>
                </div>
            </div>

            <p class="section-label">Pinger (potential client)</p>
            <div class="party-card">
                <div class="party-head">
                    <div class="avatar">
                        @if ($pinger->profile_picture_url)
                            <img src="{{ $pinger->profile_picture_url }}"
                                style="width: 100%; height: 100%; object-fit: cover;">
                        @else
                            {{ $pinger->initials() }}
                        @endif
                    </div>
                    <div>
                        <h3>{{ $pinger->name }}</h3>
                        <p class="role">{{ $pinger->work ?: ucfirst($pinger->role) }}</p>
                    </div>
                </div>
                <div class="contact-grid">
                    <div class="contact-row">
                        <span class="label">Email</span>
                        <span class="value"><a href="mailto:{{ $pinger->email }}">{{ $pinger->email }}</a></span>
                    </div>
                    @if ($pinger->phone_number)
                        <div class="contact-row">
                            <span class="label">Phone</span>
                            <span class="value">{{ $pinger->phone_number }}</span>
                        </div>
                    @endif
                    @if ($pinger->address)
                        <div class="contact-row">
                            <span class="label">Address</span>
                            <span class="value">{{ $pinger->address }}</span>
                        </div>
                    @endif
                    @if ($pinger->username)
                        <div class="contact-row">
                            <span class="label">Profile</span>
                            <span class="value"><a href="{{ config('app.url') }}/u/{{ $pinger->slug }}">/{{ $pinger->username }}</a></span>
                        </div>
                    @endif
                </div>
            </div>

            <p class="section-label">Artisan (receiver)</p>
            <div class="party-card">
                <div class="party-head">
                    <div class="avatar">
                        @if ($artisan->profile_picture_url)
                            <img src="{{ $artisan->profile_picture_url }}"
                                style="width: 100%; height: 100%; object-fit: cover;">
                        @else
                            {{ $artisan->initials() }}
                        @endif
                    </div>
                    <div>
                        <h3>{{ $artisan->name }}</h3>
                        <p class="role">{{ $artisan->work ?: ucfirst($artisan->role) }}</p>
                    </div>
                </div>
                <div class="contact-grid">
                    <div class="contact-row">
                        <span class="label">Email</span>
                        <span class="value"><a href="mailto:{{ $artisan->email }}">{{ $artisan->email }}</a></span>
                    </div>
                    @if ($artisan->phone_number)
                        <div class="contact-row">
                            <span class="label">Phone</span>
                            <span class="value">{{ $artisan->phone_number }}</span>
                        </div>
                    @endif
                    @if ($artisan->address)
                        <div class="contact-row">
                            <span class="label">Address</span>
                            <span class="value">{{ $artisan->address }}</span>
                        </div>
                    @endif
                    @if ($artisan->username)
                        <div class="contact-row">
                            <span class="label">Profile</span>
                            <span class="value"><a href="{{ config('app.url') }}/u/{{ $artisan->slug }}">/{{ $artisan->username }}</a></span>
                        </div>
                    @endif
                </div>
            </div>

            <a href="{{ config('app.url') }}/admin/pings" class="button">View in Pings Dashboard</a>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Allsers &middot; Admin ping alert</p>
        </div>
    </div>
</body>

</html>
