<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px;">
    <div style="max-width: 500px; margin: 0 auto; background: white; border-radius: 8px; padding: 24px; border: 1px solid #e5e5e5;">
        <h2 style="color: #4e1a77; margin-top: 0;">Reset Your Password</h2>
        <p>Hi <strong>{{ $memberName }}</strong>,</p>
        <p>We received a request to reset your eWards PMS password. Click the button below to set a new password. This link expires in <strong>60 minutes</strong>.</p>
        <p style="margin-top: 24px;">
            <a href="{{ url('/reset-password/' . $token . '?email=' . urlencode($email)) }}"
               style="display: inline-block; background: #4e1a77; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold;">
                Reset Password
            </a>
        </p>
        <p style="color: #666; font-size: 13px; margin-top: 20px;">If you did not request a password reset, you can ignore this email — your password will remain unchanged.</p>
        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;" />
        <p style="color: #999; font-size: 12px;">eWards PMS &mdash; Project Management System</p>
    </div>
</body>
</html>
