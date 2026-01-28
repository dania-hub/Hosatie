<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعادة تعيين كلمة المرور - حُصتي</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden;">
                    
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #2E5077 0%, #4DA1A9 100%); padding: 30px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 28px; font-weight: bold;">نظام حصتي</h1>
                            <p style="color: #ffffff; margin: 10px 0 0 0; font-size: 14px; opacity: 0.9;">نظام رقمي لتوزيع الأدوية المدعومة</p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px; text-align: right; direction: rtl;">
                            <h2 style="color: #333333; margin: 0 0 20px 0; font-size: 22px;">مرحباً {{ $name }}</h2>
                            
                            <p style="color: #666666; line-height: 1.8; margin: 0 0 20px 0; font-size: 16px;">
                                تم إعادة تعيين كلمة المرور الخاصة بحسابك في نظام حصتي من قبل إدارة النظام.
                            </p>

                            <p style="color: #666666; line-height: 1.8; margin: 0 0 30px 0; font-size: 16px;">
                                يمكنك استخدام كلمة المرور المؤقتة التالية لتسجيل الدخول:
                            </p>

                            <!-- Password Box -->
                            <div style="background: linear-gradient(135deg, #2E5077 0%, #4DA1A9 100%); border-radius: 10px; padding: 25px; text-align: center; margin: 30px 0;">
                                <p style="color: #ffffff; margin: 0 0 10px 0; font-size: 14px; opacity: 0.9;">كلمة المرور الجديدة</p>
                                <div style="background-color: #ffffff; border-radius: 8px; padding: 15px; display: inline-block;">
                                    <span style="color: #2E5077; font-size: 32px; font-weight: bold; letter-spacing: 2px; font-family: monospace;">{{ $password }}</span>
                                </div>
                            </div>

                            <!-- Warning Box -->
                            <div style="background-color: #fff3cd; border-right: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 5px;">
                                <p style="color: #856404; margin: 0; font-size: 14px;">
                                    <strong>⚠️ هام جداً:</strong> هذه كلمة مرور مؤقتة. يرجى تسجيل الدخول وتغييرها فوراً من إعدادات حسابك.
                                </p>
                            </div>

                            <p style="color: #999999; line-height: 1.6; margin: 20px 0 0 0; font-size: 14px;">
                                إذا لم تطلب إعادة تعيين كلمة المرور، يرجى التواصل مع إدارة النظام فوراً.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #e9ecef;">
                            <p style="color: #999999; margin: 0 0 10px 0; font-size: 13px;">
                                نظام حصتي - نظام رقمي لتعزيز الشفافية والعدالة
                            </p>
                            <p style="color: #999999; margin: 0; font-size: 13px;">
                                في توزيع الأدوية المدعومة في ليبيا 🇱🇾
                            </p>
                            <p style="color: #cccccc; margin: 15px 0 0 0; font-size: 12px;">
                                © {{ date('Y') }} نظام حصتي - جميع الحقوق محفوظة
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
