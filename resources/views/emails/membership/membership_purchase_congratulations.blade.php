<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Congratulations! Your Membership Is Now Active</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.6;">
<p>Hello {{ $user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'Peer' }},</p>

<p>Congratulations on purchasing your membership.</p>
<p>Your membership is now active, and you can now enjoy full Peers Global benefits.</p>
<p>Thank you for joining and growing with the community.</p>

<p>Warm regards,<br>Peers Global Team</p>
</body>
</html>
