<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __("You're invited to join :app", ['app' => config('app.name')]) }}</title>
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
                                <p style="margin:0 0 16px 0;">{{ __('Hello :name,', ['name' => $user->name]) }}</p>
                                <p style="margin:0 0 16px 0;">{{ __("You've been added to the :app portal as :role.", ['app' => config('app.name'), 'role' => $user->role->label()]) }}</p>

                                <p style="margin:0 0 8px 0; font-weight:bold; color:#3A2E28;">{{ __('To sign in:') }}</p>
                                <ol style="margin:0 0 24px 0; padding-left:20px;">
                                    <li style="margin-bottom:4px;">{{ __('Open the sign-in page with the button below.') }}</li>
                                    <li style="margin-bottom:4px;">{{ __('Tap your name, :name.', ['name' => $user->name]) }}</li>
                                    <li style="margin-bottom:4px;">{{ __('Enter the starting PIN your manager gave you.') }}</li>
                                    <li>{{ __("Choose your own PIN. You'll use it from then on.") }}</li>
                                </ol>

                                <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 24px auto;">
                                    <tr>
                                        <td align="center" style="border-radius:10px; background-color:#8A3330;">
                                            <a href="{{ $url }}" target="_blank" style="display:inline-block; padding:12px 28px; font-size:14px; font-weight:bold; color:#ffffff; text-decoration:none;">{{ __('Sign In') }}</a>
                                        </td>
                                    </tr>
                                </table>

                                <p style="margin:0 0 16px 0;">{{ __("For your security, your starting PIN is not included in this email. Ask the manager who created your account if you don't have it yet.") }}</p>
                                <p style="margin:0;">{{ __('If you were not expecting this invitation, you can safely ignore this email.') }}</p>

                                <p style="margin:24px 0 0 0;">{{ __('Regards,') }}<br>88 Hot Spring Resort Development Team</p>

                                <hr style="border:none; border-top:1px solid #E5DDD0; margin:24px 0;">

                                <p style="margin:0; font-size:12px; color:#8A7B6D;">
                                    {{ __('If you\'re having trouble clicking the ":button" button, copy and paste the URL below into your web browser:', ['button' => __('Sign In')]) }}
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
