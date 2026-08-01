<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Reset Password Notification</title>
    </head>
    <body style="margin:0; padding:0; background-color:#F7F0E3; font-family: Arial, Helvetica, sans-serif;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F7F0E3; padding:40px 20px;">
            <tr>
                <td align="center">
                    <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="max-width:480px; width:100%; background-color:#ffffff; border-radius:16px; overflow:hidden; border:1px solid #E5DDD0;">

                        {{-- Logo header --}}
                        <tr>
                            <td align="center" style="padding:32px 32px 8px 32px;">
                                <img src="{{ $message->embed(public_path('images/logo.png')) }}" alt="88 Hot Spring Resort" width="72" height="72" style="border-radius:50%; object-fit:cover; display:block;">
                                <div style="margin-top:12px; font-size:18px; font-weight:bold; color:#3A2E28;">88 Hot Spring Resort</div>
                            </td>
                        </tr>

                        {{-- Body --}}
                        <tr>
                            <td style="padding:16px 32px 32px 32px; color:#4B4136; font-size:14px; line-height:1.6;">
                                <p style="margin:0 0 16px 0;">Hello!</p>
                                <p style="margin:0 0 24px 0;">You are receiving this email because we received a password reset request for your account.</p>

                                <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 24px auto;">
                                    <tr>
                                        <td align="center" style="border-radius:10px; background-color:#8A3330;">
                                            <a href="{{ $url }}" target="_blank" style="display:inline-block; padding:12px 28px; font-size:14px; font-weight:bold; color:#ffffff; text-decoration:none;">Reset Password</a>
                                        </td>
                                    </tr>
                                </table>

                                <p style="margin:0 0 16px 0;">This password reset link will expire in 60 minutes.</p>
                                <p style="margin:0;">If you did not request a password reset, no further action is required.</p>

                                <p style="margin:24px 0 0 0;">Regards,<br>88 Hot Spring Resort Development Team</p>

                                <hr style="border:none; border-top:1px solid #E5DDD0; margin:24px 0;">

                                <p style="margin:0; font-size:12px; color:#8A7B6D;">
                                    If you're having trouble clicking the "Reset Password" button, copy and paste the URL below into your web browser:
                                    <br>
                                    <a href="{{ $url }}" style="color:#8A3330; word-break:break-all;">{{ $url }}</a>
                                </p>
                            </td>
                        </tr>

                        {{-- Banner footer --}}
                        <tr>
                            <td style="background-color:#F7F0E3; padding:20px;">
                                <img src="{{ $message->embed(public_path('images/logo2024.png')) }}" alt="88 Hot Spring Resort" width="100%" style="display:block; max-width:100%; height:auto;">
                            </td>
                        </tr>
                    </table>

                    <p style="margin:20px 0 0 0; font-size:12px; color:#8A7B6D;">
                        &copy; {{ date('Y') }} 88 Hot Spring Resort Inc. All rights reserved.
                    </p>
                </td>
            </tr>
        </table>
    </body>
</html>
