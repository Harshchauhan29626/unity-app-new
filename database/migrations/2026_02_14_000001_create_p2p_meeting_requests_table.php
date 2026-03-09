<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'p2p_meeting_status_enum') THEN
        CREATE TYPE p2p_meeting_status_enum AS ENUM ('pending', 'accepted', 'rejected', 'cancelled');
    END IF;
END
$$;

CREATE TABLE IF NOT EXISTS p2p_meeting_requests (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    requester_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    invitee_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    scheduled_at TIMESTAMPTZ NOT NULL,
    place TEXT NOT NULL,
    message TEXT NULL,
    status p2p_meeting_status_enum NOT NULL DEFAULT 'pending',
    responded_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_p2p_meeting_requests_invitee_status
    ON p2p_meeting_requests(invitee_id, status);

CREATE INDEX IF NOT EXISTS idx_p2p_meeting_requests_requester_status
    ON p2p_meeting_requests(requester_id, status);

CREATE INDEX IF NOT EXISTS idx_p2p_meeting_requests_scheduled_at
    ON p2p_meeting_requests(scheduled_at);
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TABLE IF EXISTS p2p_meeting_requests;
DROP TYPE IF EXISTS p2p_meeting_status_enum;
SQL);
    }
};
