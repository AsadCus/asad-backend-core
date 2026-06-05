<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Quotation {{ $quotation->quotation_number }}</title>
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

                    <tr>
                        <td align="left" style="font-size:18px; font-weight:bold; color:#333333; padding-bottom:15px;">
                            Hello {{ $quotation->customer->user->name ?? 'Customer' }},
                        </td>
                    </tr>

                    <tr>
                        <td align="left" style="font-size:16px; color:#555555; line-height:1.6; padding-bottom:10px;">
                            {!! nl2br(e($customMessage ?? '')) !!}
                        </td>
                    </tr>

                    @if(!isset($isBulk) || !$isBulk)
                    <tr>
                        <td align="left" style="padding-top:10px; padding-bottom:15px;">
                            <a href="{{ route('quotation.generate.pdf', $quotation->id) }}" style="background-color:#0f172a; color:#ffffff; padding:10px 20px; text-decoration:none; border-radius:5px; font-size:16px; font-weight:bold; display:inline-block;">Download Quotation PDF</a>
                        </td>
                    </tr>
                    @endif

                    <tr>
                        <td align="left" style="font-size:16px; color:#555555; line-height:1.6; padding-top:20px;">
                            Thank you,<br>
                            The {{ config('app.name') }} Team
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
