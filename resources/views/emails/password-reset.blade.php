<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعادة تعيين كلمة المرور</title>
    <style>
        body { margin: 0; padding: 0; background: #f4f4f8; font-family: 'Segoe UI', Tahoma, sans-serif; }
        .wrapper { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 24px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #4f46e5, #7c3aed); padding: 40px 30px; text-align: center; }
        .header h1 { color: #ffffff; font-size: 28px; margin: 0; font-weight: 800; letter-spacing: 1px; }
        .header p { color: rgba(255,255,255,0.8); font-size: 14px; margin-top: 8px; }
        .header p { color: rgba(255,255,255,0.8); font-size: 14px; margin-top: 8px; }
        .body { padding: 40px 30px; }
        .body h2 { color: #1e1e2e; font-size: 20px; margin-bottom: 15px; text-align: center; }
        .body p { color: #6b7280; line-height: 1.7; font-size: 15px; text-align: center; }
        .btn-wrap { text-align: center; margin: 30px 0; }
        .btn { display: inline-block; background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #ffffff; text-decoration: none; padding: 16px 48px; border-radius: 14px; font-weight: 700; font-size: 16px; box-shadow: 0 8px 24px rgba(79,70,229,0.3); transition: all 0.2s; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(79,70,229,0.4); }
        .footer { background: #f9fafb; padding: 25px 30px; text-align: center; border-top: 1px solid #f0f0f5; }
        .footer p { color: #9ca3af; font-size: 12px; margin: 4px 0; }
        .footer .links { margin-top: 10px; }
        .footer .links a { color: #4f46e5; text-decoration: none; font-size: 13px; margin: 0 10px; }
        .divider { height: 1px; background: #f0f0f5; margin: 25px 0; }
        .info { background: #f0f4ff; border-radius: 16px; padding: 15px 20px; margin-top: 20px; }
        .info p { color: #4f46e5; font-size: 13px; margin: 0; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>SmartMall</h1>
            <p>المنصة الذكية للمولات والسوبر ماركت</p>
        </div>

        <div class="body">
            <h2>إعادة تعيين كلمة المرور</h2>
            <p>مرحباً {{ $user->name }}،</p>
            <p>لقد تلقينا طلباً لإعادة تعيين كلمة المرور لحسابك في SmartMall. يمكنك تعيين كلمة مرور جديدة بالضغط على الزر أدناه:</p>

            <div class="btn-wrap" style="text-align:center;margin:30px 0;">
                <a href="{{ $resetUrl }}" style="display:inline-block;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#ffffff;text-decoration:none;padding:16px 48px;border-radius:14px;font-weight:700;font-size:16px;font-family:'Segoe UI',Tahoma,sans-serif;mso-hide:all;">تعيين كلمة مرور جديدة</a>
            </div>

            <p style="font-size: 13px; color: #9ca3af;">إذا لم تقم بطلب إعادة تعيين كلمة المرور، يرجى تجاهل هذه الرسالة.</p>

            <div class="divider"></div>

            <div class="info">
                <p>⚠️ رابط إعادة التعيين صالح لمدة 60 دقيقة فقط</p>
            </div>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} SmartMall. جميع الحقوق محفوظة.</p>
            <p>للاستفسار: info@samrtmall.cloud | للدعم: support@samrtmall.cloud</p>
            <div class="links">
                <a href="{{ config('app.frontend_url') }}">زيارة الموقع</a>
                <a href="mailto:support@samrtmall.cloud">اتصل بنا</a>
            </div>
        </div>
    </div>
</body>
</html>
