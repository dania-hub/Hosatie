# إعداد البريد الإلكتروني في Laravel

## الطريقة 1: استخدام Gmail (موصى به للاختبار)

### الخطوات:

1. **إنشاء App Password في Gmail:**
   - اذهب إلى [Google Account Settings](https://myaccount.google.com/)
   - اختر "Security" → "2-Step Verification" (يجب تفعيله أولاً)
   - ثم اختر "App passwords"
   - أنشئ App Password جديد وانسخه

2. **تعديل ملف `.env`:**
   ```env
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_USERNAME=your-email@gmail.com
   MAIL_PASSWORD=your-app-password-here
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=your-email@gmail.com
   MAIL_FROM_NAME="${APP_NAME}"
   ```

3. **مسح cache الإعدادات:**
   ```bash
   php artisan config:clear
   php artisan config:cache
   ```

## الطريقة 2: استخدام Mailtrap (للاختبار والتطوير)

1. **إنشاء حساب في [Mailtrap](https://mailtrap.io/)**

2. **تعديل ملف `.env`:**
   ```env
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.mailtrap.io
   MAIL_PORT=2525
   MAIL_USERNAME=your-mailtrap-username
   MAIL_PASSWORD=your-mailtrap-password
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=noreply@hosatie.ly
   MAIL_FROM_NAME="حُصتي"
   ```

3. **مسح cache الإعدادات:**
   ```bash
   php artisan config:clear
   php artisan config:cache
   ```

## الطريقة 3: استخدام SMTP آخر (Outlook, Yahoo, إلخ)

### Outlook/Hotmail:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp-mail.outlook.com
MAIL_PORT=587
MAIL_USERNAME=your-email@outlook.com
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@outlook.com
MAIL_FROM_NAME="${APP_NAME}"
```

### Yahoo:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mail.yahoo.com
MAIL_PORT=587
MAIL_USERNAME=your-email@yahoo.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@yahoo.com
MAIL_FROM_NAME="${APP_NAME}"
```

## اختبار الإعداد

بعد إعداد البريد، يمكنك اختباره عن طريق إنشاء موظف جديد. يجب أن تصل رسالة التفعيل إلى البريد الإلكتروني المحدد.

## ملاحظات مهمة:

- **Gmail**: يتطلب تفعيل "Less secure app access" أو استخدام App Password
- **Mailtrap**: مثالي للاختبار - الرسائل لا تُرسل فعلياً بل تُعرض في Mailtrap inbox
- **الإنتاج**: استخدم خدمة بريد احترافية مثل SendGrid, Mailgun, أو AWS SES

## استكشاف الأخطاء:

إذا لم تصل الرسائل:
1. تحقق من ملف `storage/logs/laravel.log` للأخطاء
2. تأكد من صحة بيانات SMTP
3. تأكد من مسح cache الإعدادات بعد التعديل
4. تحقق من أن البريد لم يذهب إلى Spam








