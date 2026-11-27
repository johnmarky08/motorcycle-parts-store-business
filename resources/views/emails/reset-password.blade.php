@php
    // The logo travels inside the email (CID attachment), so it shows even when remote images are blocked.
    $logo = isset($message) && is_file($logoPath) ? $message->embed($logoPath) : asset('images/logo.png');
    $font = "Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
@endphp
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>Reset your {{ $appName }} password</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; padding: 0; width: 100% !important; background: #f8fafc; }
        a { color: #1d4ed8; }
        @media (max-width: 600px) {
            .card { padding: 28px 22px !important; }
            .title { font-size: 21px !important; }
            .button a { display: block !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background:#f8fafc;">
    {{-- Preview text shown next to the subject in the inbox list. --}}
    <div style="display:none; max-height:0; overflow:hidden; mso-hide:all; font-size:1px; line-height:1px; color:#f8fafc;">
        Use this link within {{ $minutes }} minutes to choose a new password for your {{ $appName }} account.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f8fafc;">
        <tr>
            <td align="center" style="padding:40px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;">

                    {{-- Brand row: the same logo tile and wording as the sidebar and sign-in page. --}}
                    <tr>
                        <td style="padding:0 4px 20px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="vertical-align:middle;">
                                        <img src="{{ $logo }}" width="44" height="44" alt="{{ $appName }}" style="display:block; width:44px; height:44px; border:0; border-radius:12px;">
                                    </td>
                                    <td style="vertical-align:middle; padding-left:12px; font-family:{!! $font !!};">
                                        <div style="font-size:15px; font-weight:600; color:#0f172a; letter-spacing:-0.01em;">{{ $appName }}</div>
                                        <div style="font-size:12px; color:#64748b; margin-top:2px;">Motorcycle Parts Store</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Card --}}
                    <tr>
                        <td class="card" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:40px; font-family:{!! $font !!}; box-shadow:0 1px 2px rgba(15,23,42,0.05);">
                            <div style="font-size:11px; font-weight:600; letter-spacing:0.16em; text-transform:uppercase; color:#1d4ed8;">Account Recovery</div>
                            <h1 class="title" style="margin:12px 0 0; font-size:24px; line-height:1.3; font-weight:600; letter-spacing:-0.02em; color:#0f172a;">Reset your password</h1>

                            <p style="margin:20px 0 0; font-size:14px; line-height:1.65; color:#475569;">Dear {{ $name }},</p>
                            <p style="margin:8px 0 0; font-size:14px; line-height:1.65; color:#475569;">
                                We have received a request to reset the password for your {{ $appName }} account. To proceed, please select the button below and choose a new password.
                            </p>

                            {{-- Button (table-based so it renders in Outlook too) --}}
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:28px 0 0;" class="button">
                                <tr>
                                    <td align="center" bgcolor="#0f172a" style="border-radius:8px; background:#0f172a;">
                                        <a href="{{ $url }}" target="_blank" rel="noopener" style="display:inline-block; padding:13px 26px; font-family:{!! $font !!}; font-size:14px; font-weight:600; color:#ffffff; text-decoration:none; border-radius:8px;">Set a New Password</a>
                                    </td>
                                </tr>
                            </table>

                            {{-- Expiry notice --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:28px 0 0;">
                                <tr>
                                    <td style="background:#eff6ff; border:1px solid #dbeafe; border-radius:8px; padding:12px 14px; font-size:13px; line-height:1.55; color:#1e3a8a;">
                                        This link expires in <strong>{{ $minutes }} minutes</strong> and can only be used once.
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:24px 0 0; font-size:13px; line-height:1.6; color:#64748b;">
                                If you did not request a password reset, no further action is required and your current password will remain unchanged.
                            </p>

                            <p style="margin:20px 0 0; font-size:14px; line-height:1.65; color:#475569;">
                                Sincerely,<br>
                                <span style="font-weight:600; color:#0f172a;">The {{ $appName }} Team</span>
                            </p>

                            {{-- Fallback link --}}
                            <div style="margin:24px 0 0; padding-top:20px; border-top:1px solid #f1f5f9;">
                                <p style="margin:0; font-size:12px; line-height:1.6; color:#94a3b8;">If the button does not work, please copy and paste the following link into your browser:</p>
                                <p style="margin:6px 0 0; font-size:12px; line-height:1.6; word-break:break-all;">
                                    <a href="{{ $url }}" target="_blank" rel="noopener" style="color:#1d4ed8; text-decoration:underline;">{{ $url }}</a>
                                </p>
                            </div>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td align="center" style="padding:24px 16px 0; font-family:{!! $font !!}; font-size:12px; line-height:1.6; color:#94a3b8;">
                            &copy; {{ date('Y') }} {{ $appName }} &middot; Motorcycle Parts Store<br>
                            This is an automated message; please do not reply to this email.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
