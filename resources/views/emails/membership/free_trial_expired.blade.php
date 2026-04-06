<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Your Free Trial Has Ended – Continue Your Peers Global Journey</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.6;">
<p>Hello {{ $user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'Peer' }},</p>

<p>Your free trial period has ended.</p>
<p>To continue enjoying Peers Global benefits, please activate or renew your membership.</p>
<p>Keep networking, joining circles, and accessing member opportunities.</p>
<p><strong>Activate your membership now to continue your Peers Global journey.</strong></p>

<p>Warm regards,<br>Peers Global Team</p>
</body>
</html>
