-- Create table for requirement interactions (interest/connect)
CREATE TABLE IF NOT EXISTS public.requirement_interests (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    requirement_id UUID NOT NULL REFERENCES public.requirements(id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
    source VARCHAR(50) NULL,
    comment TEXT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_requirement_interests_requirement_user
    ON public.requirement_interests(requirement_id, user_id);

CREATE INDEX IF NOT EXISTS idx_requirement_interests_requirement_id
    ON public.requirement_interests(requirement_id);

CREATE INDEX IF NOT EXISTS idx_requirement_interests_user_id
    ON public.requirement_interests(user_id);

-- Keep updated_at current
CREATE OR REPLACE FUNCTION public.set_updated_at_requirement_interests()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_requirement_interests_set_updated_at ON public.requirement_interests;
CREATE TRIGGER trg_requirement_interests_set_updated_at
BEFORE UPDATE ON public.requirement_interests
FOR EACH ROW
EXECUTE FUNCTION public.set_updated_at_requirement_interests();

-- Optional: add enum values only if notification_type_enum exists.
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_type WHERE typname = 'notification_type_enum') THEN
        BEGIN
            ALTER TYPE notification_type_enum ADD VALUE IF NOT EXISTS 'requirement_created';
        EXCEPTION WHEN OTHERS THEN
            RAISE NOTICE 'Could not add enum value requirement_created: %', SQLERRM;
        END;

        BEGIN
            ALTER TYPE notification_type_enum ADD VALUE IF NOT EXISTS 'requirement_interest';
        EXCEPTION WHEN OTHERS THEN
            RAISE NOTICE 'Could not add enum value requirement_interest: %', SQLERRM;
        END;
    END IF;
END $$;
