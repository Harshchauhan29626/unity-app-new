<?php

return [
    'activities' => [
        'attend_circle_meeting' => [
            'label' => 'Attend Circle Meeting',
            'coins' => 10,
            'fields' => [
                ['key' => 'meeting_date', 'label' => 'Meeting Date', 'type' => 'date', 'required' => true],
            ],
        ],
        'vyapaarjagat_story' => [
            'label' => 'VyapaarJagat Story',
            'coins' => 20,
            'fields' => [
                ['key' => 'story_url', 'label' => 'Story URL', 'type' => 'url', 'required' => true],
            ],
        ],
        'host_member_spotlight' => [
            'label' => 'Host Member Spotlight',
            'coins' => 15,
            'fields' => [
                ['key' => 'member_name', 'label' => 'Member Name', 'type' => 'text', 'required' => true],
                ['key' => 'spotlight_date', 'label' => 'Spotlight Date', 'type' => 'date', 'required' => true],
                ['key' => 'spotlight_link', 'label' => 'Spotlight Link', 'type' => 'url', 'required' => true],
            ],
        ],
        'bring_speaker' => [
            'label' => 'Bring Speaker',
            'coins' => 15,
            'fields' => [
                ['key' => 'speaker_name', 'label' => 'Speaker Name', 'type' => 'text', 'required' => true],
            ],
        ],
        'join_circle' => [
            'label' => 'Join Circle',
            'coins' => 10,
            'fields' => [
                ['key' => 'circle_name', 'label' => 'Circle Name', 'type' => 'text', 'required' => true],
            ],
        ],
        'renew_membership' => [
            'label' => 'Renew Membership',
            'coins' => 25,
            'fields' => [
                ['key' => 'renewal_date', 'label' => 'Renewal Date', 'type' => 'date', 'required' => true],
                ['key' => 'payment_proof_file', 'label' => 'Payment Proof', 'type' => 'file', 'required' => true],
            ],
        ],
        'invite_visitor' => [
            'label' => 'Invite Visitor',
            'coins' => 10,
            'fields' => [
                ['key' => 'visitor_name', 'label' => 'Visitor Name', 'type' => 'text', 'required' => true],
                ['key' => 'visitor_mobile', 'label' => 'Visitor Mobile', 'type' => 'phone', 'required' => true],
                ['key' => 'visitor_email', 'label' => 'Visitor Email', 'type' => 'email', 'required' => true],
                ['key' => 'visit_date', 'label' => 'Visit Date', 'type' => 'date', 'required' => true],
                ['key' => 'event_confirmation_file', 'label' => 'Event Confirmation', 'type' => 'file', 'required' => false],
            ],
        ],
        'new_member_addition' => [
            'label' => 'New Member Addition',
            'coins' => 50,
            'fields' => [
                ['key' => 'new_member_name', 'label' => 'New Member Name', 'type' => 'text', 'required' => true],
                ['key' => 'new_member_mobile', 'label' => 'New Member Mobile', 'type' => 'phone', 'required' => true],
                ['key' => 'new_member_email', 'label' => 'New Member Email', 'type' => 'email', 'required' => true],
                ['key' => 'joining_date', 'label' => 'Joining Date', 'type' => 'date', 'required' => true],
                ['key' => 'membership_confirmation_file', 'label' => 'Membership Confirmation', 'type' => 'file', 'required' => false],
            ],
        ],
    ],
];
