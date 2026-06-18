<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset your FitStack password</title>
    <style>
        body { font-family: sans-serif; background: #f4f4f4; margin: 0; padding: 40px 0; }
        .container { max-width: 520px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 40px; }
        h1 { font-size: 22px; margin-bottom: 16px; }
        p { color: #444; line-height: 1.6; }
        .btn {
            display: inline-block; margin: 24px 0; padding: 12px 28px;
            background: #3b82f6; color: #fff; text-decoration: none;
            border-radius: 6px; font-weight: 600;
        }
        .muted { font-size: 12px; color: #888; margin-top: 24px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Reset your password</h1>
        <p>Hi {{ $user->username }},</p>
        <p>We received a request to reset your FitStack password. Click the button below — this link is valid for 1 hour.</p>
        <a href="{{ $resetUrl }}" class="btn">Reset password</a>
        <p>If you didn't request this, you can safely ignore this email.</p>
        <p class="muted">If the button doesn't work, copy and paste this URL into your browser:<br>{{ $resetUrl }}</p>
    </div>
</body>
</html>
