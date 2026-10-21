<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Welcome to Allsers</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f4f4f5;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }

        table {
            border-collapse: collapse;
        }

        img {
            border: 0;
            outline: none;
            text-decoration: none;
            -ms-interpolation-mode: bicubic;
        }

        @media only screen and (max-width: 620px) {
            .card {
                width: 100% !important;
                border-radius: 0 !important;
                border-left: none !important;
                border-right: none !important;
            }

            .inner {
                padding: 24px 16px !important;
            }

            .btn-cell {
                display: block !important;
                width: 100% !important;
                padding: 0 0 10px !important;
            }

            .btn {
                display: block !important;
                text-align: center !important;
            }

            .step-img {
                width: 130px !important;
            }
        }
    </style>
</head>

<body style="margin: 0; padding: 0; background-color: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-text-size-adjust: 100%;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f5;">
        <tr>
            <td align="center" style="padding: 32px 12px;">
                <!--[if mso]>
                <table role="presentation" width="560" align="center"><tr><td>
                <![endif]-->
                <table role="presentation" class="card" width="560" cellpadding="0" cellspacing="0"
                    style="width: 100%; max-width: 560px; background-color: #ffffff; border: 1px solid #e4e4e7; border-radius: 12px; overflow: hidden;">
                    <tr>
                        <td class="inner" style="padding: 32px 40px;">
                            <p style="margin: 0 0 20px; font-size: 12px; font-weight: 600; letter-spacing: 2px; text-transform: uppercase; color: #a1a1aa;">
                                Allsers
                            </p>

                            <h1 style="margin: 0 0 12px; font-size: 22px; font-weight: 700; line-height: 1.3; color: #18181b;">
                                Welcome, artisan!
                            </h1>

                            <p style="margin: 0 0 14px; font-size: 14px; line-height: 1.7; color: #52525b;">
                                Your talent deserves to be seen. <strong style="color: #18181b;">Allsers</strong>
                                puts you in front of clients who are actively searching for your skills.
                                Lilia, your Allsers guide, is here to help you get set up and discovered.
                            </p>

                            <p style="margin: 0 0 6px; font-size: 14px; font-weight: 600; color: #18181b;">How Allsers grows your work</p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
                                <tr>
                                    <td style="padding: 5px 0; font-size: 14px; line-height: 1.6; color: #52525b; border-bottom: 1px solid #f4f4f5;">
                                        &bull;&nbsp; Get discovered by clients searching for your exact skills
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 5px 0; font-size: 14px; line-height: 1.6; color: #52525b; border-bottom: 1px solid #f4f4f5;">
                                        &bull;&nbsp; Receive service inquiries straight to your inbox
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 5px 0; font-size: 14px; line-height: 1.6; color: #52525b; border-bottom: 1px solid #f4f4f5;">
                                        &bull;&nbsp; Build your reputation with reviews, badges and a showcase
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 5px 0; font-size: 14px; line-height: 1.6; color: #52525b;">
                                        &bull;&nbsp; Join challenges to win visibility and rewards
                                    </td>
                                </tr>
                            </table>

                            @if ($showBusinessPrompt)
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                                    style="margin: 24px 0 4px; border-collapse: collapse;">
                                    <tr>
                                        <td style="padding: 16px 18px; background-color: #fafafa; border: 1px solid #e4e4e7; border-radius: 10px;">
                                            <p style="margin: 0 0 6px; font-size: 14px; font-weight: 600; color: #18181b;">
                                                Complete your business information
                                            </p>
                                            <p style="margin: 0 0 10px; font-size: 13px; line-height: 1.6; color: #71717a;">
                                                Clients trust complete profiles &mdash; and search results favor them.
                                                Yours is still missing:
                                            </p>
                                            <p style="margin: 0 0 12px; font-size: 12px; line-height: 2; color: #52525b;">
                                                @foreach (array_slice($completion['missing'] ?? [], 0, 8) as $missing)
                                                    <span style="display: inline-block; margin: 0 4px 4px 0; padding: 3px 10px; background-color: #ffffff; border: 1px solid #d4d4d8; border-radius: 100px; font-size: 12px; color: #52525b;">{{ $missing['label'] }}</span>
                                                @endforeach
                                            </p>
                                            <a href="{{ route('profile.edit') }}"
                                                style="display: inline-block; padding: 9px 16px; background-color: #18181b; color: #ffffff; font-size: 12px; font-weight: 600; text-decoration: none; border-radius: 8px;">
                                                Update my business info
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            @include('emails.partials.install-guide')

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 24px 0 4px; border-collapse: collapse;">
                                <tr>
                                    @if (! $showBusinessPrompt)
                                        <td class="btn-cell" width="50%" valign="top" style="padding: 0 5px 0 0;">
                                            <a href="{{ route('profile.edit') }}" class="btn"
                                                style="display: block; padding: 12px 20px; background-color: #18181b; color: #ffffff; font-size: 13px; font-weight: 600; text-decoration: none; text-align: center; border-radius: 8px;">
                                                View My Profile
                                            </a>
                                        </td>
                                        <td class="btn-cell" width="50%" valign="top" style="padding: 0 0 0 5px;">
                                            <a href="{{ route('dashboard') }}" class="btn"
                                                style="display: block; padding: 12px 20px; background-color: #ffffff; color: #18181b; font-size: 13px; font-weight: 600; text-decoration: none; text-align: center; border: 1px solid #d4d4d8; border-radius: 8px;">
                                                Go to Dashboard
                                            </a>
                                        </td>
                                    @else
                                        <td class="btn-cell" valign="top" style="padding: 0;">
                                            <a href="{{ route('dashboard') }}" class="btn"
                                                style="display: block; padding: 12px 20px; background-color: #18181b; color: #ffffff; font-size: 13px; font-weight: 600; text-decoration: none; text-align: center; border-radius: 8px;">
                                                Go to Dashboard
                                            </a>
                                        </td>
                                    @endif
                                </tr>
                            </table>

                            <p style="margin: 22px 0 0; padding-top: 18px; border-top: 1px solid #f4f4f5; font-size: 13px; color: #71717a;">
                                Lilia from Allsers.<br>
                                <span style="color: #a1a1aa;">Can&rsquo;t wait to see your work shine.</span>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td class="inner" style="padding: 14px 40px; background-color: #fafafa; border-top: 1px solid #e4e4e7;">
                            <p style="margin: 0; font-size: 12px; color: #a1a1aa; text-align: center;">
                                &copy; {{ date('Y') }} Allsers. Empowering Local Talent.
                            </p>
                        </td>
                    </tr>
                </table>
                <!--[if mso]>
                </td></tr></table>
                <![endif]-->
            </td>
        </tr>
    </table>
</body>

</html>
