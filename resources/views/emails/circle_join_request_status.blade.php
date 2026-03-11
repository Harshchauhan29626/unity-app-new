@php
    $user = $circleJoinRequest->user;
    $circle = $circleJoinRequest->circle;
@endphp
<p>Hello {{ $user?->display_name ?? trim(($user?->first_name ?? '') . ' ' . ($user?->last_name ?? '')) ?: 'Peer' }},</p>

<p>{{ $body }}</p>

<p><strong>Circle:</strong> {{ $circle?->name ?? 'N/A' }}</p>
@if($statusLabel)
<p><strong>Current Status:</strong> {{ $statusLabel }}</p>
@endif
@if($rejectionReason)
<p><strong>Rejection Reason:</strong> {{ $rejectionReason }}</p>
@endif

<p>Thank you,<br>Peers Global Unity Team</p>
