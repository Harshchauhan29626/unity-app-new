<?php

return [
    // Must match a valid value from circle_member_status_enum in PostgreSQL.
    'member_joined_status' => env('CIRCLE_MEMBER_JOINED_STATUS', 'approved'),
];
