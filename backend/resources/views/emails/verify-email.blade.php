<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('auth.verify_email.mail_subject', ['site_name' => $site_name]) }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f2;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f2;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" max-width="520px" cellpadding="0" cellspacing="0" style="max-width:520px;width:100%;background-color:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e8e4dd;">
                    <tr>
                        <td style="background-color:#1a1a1a;padding:24px 32px;">
                            <h1 style="margin:0;font-family:Georgia,'Times New Roman',serif;font-size:22px;font-weight:600;color:#ffffff;letter-spacing:0.2px;">{{ $site_name }}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <h2 style="margin:0 0 12px;font-family:Georgia,'Times New Roman',serif;font-size:20px;font-weight:600;color:#1a1a1a;">{{ __('auth.verify_email.greeting') }}</h2>
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#5c5852;">{{ __('auth.verify_email.mail_body') }}</p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $url }}" style="display:inline-block;background-color:#b8860b;color:#ffffff;font-size:15px;font-weight:600;text-decoration:none;padding:14px 32px;border-radius:10px;">
                                            {{ __('auth.verify_email.verify_button') }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 16px;font-size:13px;line-height:1.5;color:#7a756d;">{{ __('auth.verify_email.expires_in', ['minutes' => $expire_minutes]) }}</p>
                            <p style="margin:0 0 16px;font-size:13px;line-height:1.5;color:#7a756d;">{{ __('auth.verify_email.ignore_hint') }}</p>
                            @if ($support_email)
                                <p style="margin:0;font-size:13px;line-height:1.5;color:#7a756d;">{{ __('auth.verify_email.support_hint') }} <a href="mailto:{{ $support_email }}" style="color:#b8860b;">{{ $support_email }}</a></p>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>