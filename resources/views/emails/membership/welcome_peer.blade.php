<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Welcome to Peers Global</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.6;">
<p>Hello {{ $user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'Peer' }},</p>

<p>Welcome to Peers Global! We are happy to have you in the community.</p>

<p>Here are great next steps to get started:</p>
<ul>
    <li>Complete your profile</li>
    <li>Explore circles and opportunities</li>
    <li>Start networking and growing with the platform</li>
</ul>

<p>Warm regards,<br>Peers Global Team</p>
</body>
</html>
