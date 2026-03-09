<p>Hello {{ $claim->user?->display_name ?? $claim->user?->first_name ?? 'Peer' }},</p>
<p>Your coin claim has been approved.</p>
<p>Coins awarded: {{ $claim->coins_awarded }}</p>
