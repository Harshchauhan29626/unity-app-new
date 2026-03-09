<p>Hello {{ $claim->user?->display_name ?? $claim->user?->first_name ?? 'Peer' }},</p>
<p>Your coin claim has been rejected.</p>
@if($claim->admin_note)
<p>Reason: {{ $claim->admin_note }}</p>
@endif
