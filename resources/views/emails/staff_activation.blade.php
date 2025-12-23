<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفعيل حسابك في حُصتي</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #4DA1A9;
        }
        .header h1 {
            color: #2E5077;
            margin: 0;
            font-size: 28px;
        }
        .content {
            margin: 20px 0;
        }
        .content p {
            margin: 15px 0;
            font-size: 16px;
            color: #555;
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .button {
            display: inline-block;
            padding: 15px 40px;
            background-color: #4DA1A9;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        .button:hover {
            background-color: #3a8c94;
        }
        .link {
            word-break: break-all;
            color: #4DA1A9;
            text-decoration: none;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 14px;
            color: #888;
        }
        .warning {
            background-color: #fff3cd;
            border-right: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>مرحباً {{ $name }}</h1>
        </div>
        
        <div class="content">
            <p>تم إنشاء حسابك بنجاح في نظام <strong>حُصتي</strong>.</p>
            
            <p>لتفعيل حسابك وتعيين كلمة المرور، يرجى الضغط على الزر أدناه:</p>
            
            <div class="button-container">
                <a href="{{ $url }}" class="button">تفعيل الحساب وتعيين كلمة المرور</a>
            </div>
            
            <p>أو يمكنك نسخ الرابط التالي ولصقه في المتصفح:</p>
            <p><a href="{{ $url }}" class="link">{{ $url }}</a></p>
            
            <div class="warning">
                <strong>⚠️ ملاحظة مهمة:</strong> هذا الرابط صالح لمدة 24 ساعة فقط. بعد انتهاء المدة، ستحتاج إلى طلب رابط جديد.
            </div>
            
            <p>إذا لم تطلب إنشاء هذا الحساب، يرجى تجاهل هذه الرسالة.</p>
        </div>
        
        <div class="footer">
            <p>© {{ date('Y') }} حُصتي. جميع الحقوق محفوظة.</p>
            <p>هذه رسالة تلقائية، يرجى عدم الرد عليها.</p>
        </div>
    </div>
</body>
</html>

