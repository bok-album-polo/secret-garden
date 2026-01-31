CREATE OR REPLACE FUNCTION get_pk(
    p_sequence VARCHAR(20) -- Changed from INT to match table definition
)
RETURNS SETOF pk_sequences 
LANGUAGE plpgsql
STABLE             -- Marked STABLE because it doesn't modify data, allowing for query optimization
SECURITY DEFINER   -- Escalates privileges to access pk_sequences securely
SET search_path = public, pg_temp -- Prevents search_path hijacking
AS $$
BEGIN
    RETURN QUERY
    SELECT domain, pk_sequence
    FROM pk_sequences
    -- Enforces that the app user can only see sequences belonging to their domain
    WHERE domain = SESSION_USER::VARCHAR 
      AND pk_sequence = p_sequence
    LIMIT 1;
END;
$$;

CREATE OR REPLACE FUNCTION check_ip_ban(
	p_ip INET
)
RETURNS BOOLEAN
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public, pg_temp
AS $$
BEGIN
	RETURN EXISTS (
		SELECT 1
		FROM ip_bans
		WHERE network >>= p_ip
		  AND (expires_at IS NULL OR expires_at > NOW())
	);
END;
$$;

CREATE OR REPLACE FUNCTION get_user(
    p_username TEXT
)
RETURNS SETOF users 
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public, pg_temp
AS $$
BEGIN
    RETURN QUERY
    SELECT *
    FROM users
    WHERE username = p_username
    LIMIT 1;
END;
$$;

CREATE OR REPLACE FUNCTION dispatch_one_username()
RETURNS TABLE(r_username VARCHAR, r_displayname VARCHAR, r_time TIMESTAMP WITH TIME ZONE)
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public, pg_temp
AS $$
BEGIN
    RETURN QUERY
    UPDATE users
    SET time_dispatched = NOW()
    WHERE username = (
        SELECT u.username
        FROM users u
        WHERE (u.time_dispatched IS NULL OR u.time_dispatched < NOW() - INTERVAL '30 minutes')
          AND u.password IS NULL
        ORDER BY u.time_dispatched ASC NULLS FIRST
        LIMIT 1
        FOR UPDATE SKIP LOCKED
    ) 
    RETURNING username, displayname, time_dispatched;
END;
$$;

CREATE OR REPLACE FUNCTION activate_user(
    p_username VARCHAR,
    p_password VARCHAR,
    p_pk_sequence VARCHAR
)
RETURNS VOID
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public, pg_temp
AS $$
BEGIN
    UPDATE users
    SET 
        password = p_password,
        pk_sequence = p_pk_sequence,
        activated_at = NOW(),
        domain = SESSION_USER::VARCHAR
    WHERE username = p_username
      AND password IS NULL;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'User % not found or already activated (password set).', p_username;
    END IF;
END;
$$;