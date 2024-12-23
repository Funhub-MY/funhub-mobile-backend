<?php
    /*
<x-mail::message>
    # Purchase Receipt

    Thank you for your purchase!

    **Transaction No:** {{ $transactionNo }}

    **Date and Time of Purchase:** {{ $dateTime }}

    **Title of Item Purchase:** {{ $itemTitle }}

    **Quantity:** {{ $quantity }}

    @if ($currencyType === 'points')
        **Subtotal (points):** {{ $subtotal }}
    @elseif ($currencyType === 'MYR')
        **Subtotal (MYR):** {{ $subtotal }}
    @endif

    Thanks,<br>
    {{ config('app.name') }}
</x-mail::message>
    */
?>

<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
	<meta name="supported-color-schemes" content="light">
    <style>
        .container {
            width: 600px;
            margin: 0 auto;
        }
        .voucher-img {
            border-radius: 8px;
        }
        .voucher-info-container {
            width: 400px;
            margin: 0 auto;
        }
        .title {
            color: #656565;
            font-weight: bold;
            padding-right: 25px;
        }
        .underline {
            width: 100%;
            height: 1px;
            background-color: #e5e5e5;
            margin: 20px 0;
        }

        .numbering {
            background-color: #047a88;
            color: #fff;
            font-weight: bold;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            text-align: center;
            line-height: 30px;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div style="background: linear-gradient(to bottom, #FFF6B7, #FFFCE9); padding: 20px; border-radius: 8px 8px 0 0;">
            <table cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td style="text-align: center;">
                        <img src="{{ config('app.url') }}/images/merchant_email/logo.png" alt="FUNHUB Logo" style="max-width: 150px; height: auto;">
                    </td>
                </tr>
                <tr>
                    <td style="text-align: center;">
                        <img src="{{ config('app.url') }}/images/merchant_email/web-banner.jpg" alt="FUNHUB banner" style="max-width: 150px; height: auto;">
                    </td>
                </tr>       
            </table>
        </div>
        <h1 style="font-size: 28px">Hi {{ $userName }},</h1>
        <p>Thank you for purchasing <span style="font-weight: bold">{{ $itemTitle }}</span>.</p>
        <p>You can find your voucher in this email. Make sure to check how to redeem the voucher before you visit <span style="font-weight: bold">{{ $merchantName }}</span>.</p>
        <p>You can redeem your voucher via <span style="font-weight: bold">downloading the app</span> or clicking the <span style="font-weight: bold">"Redeem Voucher Now"</span> button in this email:</p>
        <div style="text-align: center">
            <img src="{{ $merchantOfferCover }}" alt="voucher" class="voucher-img" style="text-align: center max-width: 150px; height: auto;">
        </div>
        <div class="voucher-info-container">
            <h2>{{ $itemTitle }}</h2>
            <table style="border-collapse: separate; border-spacing: 0 15px;">
                <tr>
                    <td class="title">Redeemable Time</td>
                    <td>{{ $redemptionStartDate }} until {{ $redemptionEndDate }} </td>
                </tr>
                <tr>
                    <td class="title">Transaction No</td>
                    <td>{{ $transactionNo }}</td>
                </tr>
                <tr>
                    <td class="title">Date of Purchase</td>
                    <td>{{ $purchaseDate  }}</td>
                </tr>
                <tr>
                    <td class="title">Time of Purchase</td>
                    <td>{{ $purchaseTime }}</td>
                </tr>
                <tr>
                    <td class="title">Quantity</td>
                    <td>{{ $quantity }}</td>
                </tr>
            </table>
        </div>
        <div class="underline"></div>
        <h1 style="font-size: 28px">To Redeem By App</h1>
        <table>
            <tr>
                <td><div class="numbering">1</div></td>
                <td style="font-size: 18px">Download FUNHUB app</td>
            </tr>
            <tr>
                <td></td>
                <td><img src="{{ config('app.url') }}/images/success-en/app_icon.png" alt="app icon" style="max-width: 150px; height: auto; border-radius: 10px"></td>
            </tr>

            <tr style="height: 25px"></tr>
            <tr>
                <td><div class="numbering">2</div></td>
                <td style="font-size: 18px">Log in with your phone number</td>
            </tr>
            <tr>
                <td></td>
                <td><img src="{{ config('app.url') }}/images/success-en/step_2.png" alt="app icon" style="max-width: 150px; height: auto; border-radius: 10px"></td>
            </tr>

            <tr style="height: 25px"></tr>
            <tr>
                <td><div class="numbering">3</div></td>
                <td style="font-size: 18px">Go to your Profile and click on My Voucher</td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <img src="{{ config('app.url') }}/images/success-en/step_3_1.png" alt="app icon" style="max-width: 150px; height: auto; border-radius: 10px">
                    <img src="{{ config('app.url') }}/images/success-en/step_3_2.png" alt="app icon" style="max-width: 150px; height: auto; border-radius: 10px">
                </td>
            </tr>

            <tr style="height: 25px"></tr>
            <tr>
                <td><div class="numbering">4</div></td>
                <td style="font-size: 18px">Select the voucher and click redeem</td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <img src="{{ config('app.url') }}/images/success-en/step_4.png" alt="app icon" style="max-width: 150px; height: auto; border-radius: 10px">
                </td>
            </tr>
        </table>
        <div style="margin: 0 auto; text-align: center">
            <a href="{{ config('app.frontend_app') }}/download/app" style="font-size:18px; margin-top: 20px; background-color: #ffe200; padding: 10px 25px; border: none; border-radius: 8px">Download FUNHUB Now</a>
        </div>
        <div class="underline"></div>
        <h1 style="font-size: 28px">To Redeem Now</h1>
        <table>
            <tr>
                <td><div class="numbering">1</div></td>
                <td style="font-size: 18px">Clicking on the “Redeem Voucher Now” Button</td>
            </tr>
            <tr style="height: 30px"></tr>
            <tr>
                <td><div class="numbering">2</div></td>
                <td style="font-size: 18px">Enter the 6-digit Master Code provided by the cashier or select Your location that can be redeemed </td>
            </tr>
            <tr style="height: 30px"></tr>
            <tr>
                <td><div class="numbering">3</div></td>
                <td style="font-size: 18px">Show The Master Code To The Cashier</td>
            </tr>
            <tr style="height: 30px"></tr>
        </table>
        <div style="margin: 15px auto; text-align: center">
            <a href="{{ config('app.frontend_app').'/redeem/voucher?data='.urlencode($encryptedData) }}" style="font-size:18px; margin-top: 20px; background-color: #ffe200; padding: 10px 25px; border: none; border-radius: 8px">Redeem Voucher Now</a>
        </div>
        <div style="background:  linear-gradient(to bottom, #FFF6B7, #FFFCE9); padding: 20px; border-radius: 0 0 8px 8px;">
            <table cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td style="text-align: center;">
                        <p style="margin: 0; color: #313131; font-size: 12px;">If you have received this communication in error, please do not forward its contents; notify the sender and delete it and any attachments.</p>
                        <p style="margin: 0; color: #313131; font-size: 12px;">Any questions or need further assistance? </p>
                        <p style="margin: 0; color: #313131; font-size: 12px;">Get in touch with us! <br/> Help center: <a href="mailto:admin@funhub.my">admin@funhub.my</a></p>
                        <p style="margin: 10px 0 0 0; color: #313131; font-size: 12px;">© {{ date('Y') }} FUNHUB TV Sdn.Bhd. All rights reserved.</p>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>
