<p>Hello {{ $claim->user?->display_name ?? $claim->user?->first_name ?? 'Peer' }},</p>
<p>Your coin claim has been submitted and is currently pending review.</p>
<p>Activity: {{ $claim->activity_code }}</p>
