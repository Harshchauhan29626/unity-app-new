<table style="font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 30px;" width="100%" cellspacing="0" cellpadding="0">
    <tbody>
    <tr>
        <td align="center">
            <table style="background-color: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05);" width="600" cellspacing="0" cellpadding="0">
                <tbody>
                <tr>
                    <td style="padding: 14px 14px; background-color: #240e5c; text-align: center;">
                        <img style="vertical-align: middle;" src="https://unity.peersglobal.com/wp-content/uploads/2025/08/peersglobal_white-removebg-preview.png" alt="Peers Global" width="135" />
                    </td>
                </tr>
                <tr>
                    <td style="padding: 14px 16px; font-size: 16px; color: #333333;">
                        Dear <strong>{{ $impact->user?->display_name ?? trim(($impact->user?->first_name ?? '') . ' ' . ($impact->user?->last_name ?? '')) ?: 'Peer' }}</strong>,<br /><br />
                        Your Impact has been submitted successfully and is now awaiting review.<br /><br />
                        <strong>Action:</strong> {{ $impact->action }}<br />
                        <strong>Impact Date:</strong> {{ optional($impact->impact_date)->toDateString() }}<br />
                        <strong>Story:</strong> {{ $impact->story_to_share }}<br />
                        <strong>Status:</strong> {{ ucfirst($impact->status) }}<br /><br />
                        Thank you for contributing to our community.<br /><br />
                        With appreciation,<br />
                        <strong>Peers Global Team</strong>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 10px 14px; background-color: #240e5c; text-align: center;">
                        <p style="font-size: 14px; font-weight: bold; color: #ffffff; margin: 4px 0;">Peers are partners in business and friends in life.</p>
                    </td>
                </tr>
                </tbody>
            </table>
        </td>
    </tr>
    </tbody>
</table>
