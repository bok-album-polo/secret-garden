CREATE OR REPLACE FUNCTION debug_clear_auth_tables()
RETURNS void
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public, pg_temp
AS $$
BEGIN
    TRUNCATE TABLE ip_bans, unauthenticated_sessions;
END;
$$;