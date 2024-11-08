<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.5; margin: 0; padding: 0; background-color: #f4f4f4;">
    <table cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <tr>
            <td style="background: linear-gradient(to bottom, #FFF6B7, #FFFCE9); padding: 20px; border-radius: 8px 8px 0 0;">
                <table cellpadding="0" cellspacing="0" width="100%">
                    <tr>
                        <td style="text-align: center;">
                            <img src="{{ config('app.url') }}/images/merchant_email/logo.png" alt="FUNHUB Logo" style="max-width: 150px; height: auto;">
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding: 30px;">
                <!-- Welcome Message -->
                <table cellpadding="0" cellspacing="0" width="100%">
                    <tr>
                        <td style="padding-bottom: 25px;">
                            <p style="margin: 0; color: #333333; font-size: 16px;">Hi {{ $merchantName }},</p>
                            <p style="margin: 10px 0 0 0; color: #333333; font-size: 16px;">Thank you for joining FUNHUB! We're excited to have you on board.</p>
                        </td>
                    </tr>
                </table>

                <!-- Master Code Section -->
                <table cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8f9fa; border-radius: 6px; margin-bottom: 25px;">
                    <tr>
                        <td style="padding: 20px;">
                            <p style="margin: 0 0 15px 0; color: #333333;">To streamline the voucher redemption process for your customers, we've provided a Master Code that will be used during the redemption.</p>
                            <p style="margin: 0 0 15px 0; color: #333333; font-weight: bold;">Your Master Code:</p>
                            <p style="margin: 0; color: #333333; font-size: 24px; font-weight: bold; background-color: #FFF6B7; padding: 10px; border-radius: 4px; width: 140px">{{ $redeemCode }}</p>
                        </td>
                    </tr>
                </table>
                <table cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 25px;">
                    <tr>
                        <td>
                            <h3 style="margin: 0 0 20px 0; color: #333333;">Steps To Use Your Master Code</h3>

                            <!-- Step 1 -->
                            <table cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 20px;">
                                <tr>
                                    <td width="30" style="vertical-align: top;">
                                        <div style="background-color: #017A88; color: white; width: 24px; height: 24px; border-radius: 12px; text-align: center; line-height: 24px; font-weight: bold;">1</div>
                                    </td>
                                    <td style="padding-left: 10px;">
                                        <p style="margin: 0 0 10px 0; color: #666666;">The customer clicks on the "Redeem" button in the app</p>
                                        <img src="{{ config('app.url') }}/images/merchant_email/fun.png" alt="Step 1" style="max-width: 100%; height: auto; border-radius: 4px;">
                                    </td>
                                </tr>
                            </table>

                            <!-- Step 2 -->
                            <table cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 20px;">
                                <tr>
                                    <td width="30" style="vertical-align: top;">
                                        <div style="background-color: #017A88; color: white; width: 24px; height: 24px; border-radius: 12px; text-align: center; line-height: 24px; font-weight: bold;">2</div>
                                    </td>
                                    <td style="padding-left: 10px;">
                                        <p style="margin: 0 0 10px 0; color: #666666;">Enter the master code provided above</p>
                                        <img src="{{ config('app.url') }}/images/merchant_email/fun2.png" alt="Step 2" style="max-width: 100%; height: auto; border-radius: 4px;">
                                    </td>
                                </tr>
                            </table>

                            <!-- Step 3 -->
                            <table cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 20px;">
                                <tr>
                                    <td width="30" style="vertical-align: top;">
                                        <div style="background-color: #017A88; color: white; width: 24px; height: 24px; border-radius: 12px; text-align: center; line-height: 24px; font-weight: bold;">3</div>
                                    </td>
                                    <td style="padding-left: 10px;">
                                        <p style="margin: 0 0 10px 0; color: #666666;">The voucher will then be successfully redeemed</p>
                                        <img src="{{ config('app.url') }}/images/merchant_email/fun3.png" alt="Step 3" style="max-width: 100%; height: auto; border-radius: 4px;">
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="background:  linear-gradient(to bottom, #FFF6B7, #FFFCE9); padding: 20px; border-radius: 0 0 8px 8px;">
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
            </td>
        </tr>
    </table>
</body>
</html>
