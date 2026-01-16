<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Access Granted</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body style="margin:0; padding:0; background-color:#f6f9fc; font-family: Arial, sans-serif; color:#333333;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f6f9fc; padding:20px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="padding:25px 0;">
                    <tr>
                        <td align="center" style="font-size:20px;">
                            <a style="text-decoration:none; color:#333333; font-weight: bold;"
                                href="{{ config('app.url') }}">
                                {{ config('app.name') }}
                            </a>
                        </td>

                    </tr>
                </table>
                <table width="600" cellpadding="0" cellspacing="0"
                    style="background-color:#ffffff; border-radius:8px; padding:32px;">
                    {{-- <tr>
                        <td align="left" style="padding-bottom:20px;">
                            <img src="cid:logo.png" alt="{{ config('app.name') }}" width="120"
                                style="display:block; border:0; outline:none; text-decoration:none;">
                        </td>
                    </tr> --}}

                    <tr>
                        <td align="left" style="font-size:18px; font-weight:bold; color:#333333; padding-bottom:5px;">
                            Hello, {{ $name }}
                        </td>
                    </tr>

                    <tr>
                        <td align="left" style="font-size:16px; color:#555555; line-height:1.6; padding-bottom:10px;">
                            You’ve been granted access to the <strong>{{ config('app.name') }}</strong> platform.
                        </td>
                    </tr>

                    <tr>
                        <td align="left" style="font-size:16px; color:#555555; line-height:1.6;">
                            {{-- <p style="margin:8px 0;"><strong>Login URL:</strong> <a href="{{ $loginUrl }}"
                                    style="color:#007bff; text-decoration:none;">{{ $loginUrl }}</a></p> --}}
                            <p style="margin:8px 0;"><strong>Email:</strong> {{ $email }}</p>
                            <p style="margin:8px 0;"><strong>Password:</strong> {{ $password }}</p>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:20px 0;">
                            <a href="{{ $loginUrl }}"
                                style="background-color:#007bff; color:#ffffff; text-decoration:none; padding:12px 28px; border-radius:5px; display:inline-block; font-size:14px;">
                                Login Now
                            </a>
                        </td>
                    </tr>

                    <tr>
                        <td align="left" style="font-size:16px; color:#555555; line-height:1.6;">
                            We recommend changing your password after your first login for better security.
                        </td>
                    </tr>
                </table>

                <table width="600" cellpadding="0" cellspacing="0" style="padding:25px 0;">
                    <tr>
                        <td align="center" style="font-size:12px; color:#999999;">
                            © {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>

</body>

</html>
