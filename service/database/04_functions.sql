CREATE OR REPLACE FUNCTION pk_get(
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

CREATE OR REPLACE FUNCTION user_username_dispatch()
RETURNS TABLE(r_username VARCHAR, r_displayname VARCHAR, r_time TIMESTAMPTZ)
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

CREATE OR REPLACE FUNCTION user_get(
    p_username VARCHAR,
    p_pk_sequence VARCHAR
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
      AND domain = SESSION_USER::VARCHAR
      AND pk_sequence = p_pk_sequence
    LIMIT 1;
END;
$$;

CREATE OR REPLACE FUNCTION user_activate(
    p_username VARCHAR,
    p_password VARCHAR,
    p_pk_sequence VARCHAR
)
RETURNS SETOF users
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public, pg_temp
AS $$
BEGIN
    RETURN QUERY
    UPDATE users
    SET 
        password = p_password,
        pk_sequence = p_pk_sequence,
        activated_at = NOW(),
        domain = SESSION_USER::VARCHAR
    WHERE username = p_username
      AND password IS NULL
    RETURNING *;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'User % not found or already activated (password set).', p_username;
    END IF;
END;
$$;

CREATE OR REPLACE FUNCTION ip_ban_ban(
    p_ip_address INET,
    p_reason TEXT,
    p_risk_score INT DEFAULT NULL,
    p_expires_at TIMESTAMPTZ DEFAULT NULL
)
RETURNS VOID
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public, pg_temp
AS $$
BEGIN
    INSERT INTO ip_bans (
        ip_address,
        netblock,
        reason,
        risk_score,
        expires_at,
        domain
    )
    VALUES (
        p_ip_address,
        CASE
            WHEN family(p_ip_address) = 4 THEN set_masklen(p_ip_address, 24)::cidr
            ELSE set_masklen(p_ip_address, 48)::cidr
        END,
        p_reason,
        p_risk_score,
        p_expires_at,
        SESSION_USER
    );
END;
$$;

CREATE OR REPLACE FUNCTION ip_ban_check(
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
		WHERE ip_address = p_ip
		  AND (expires_at IS NULL OR expires_at > NOW())
	);
END;
$$;

CREATE OR REPLACE FUNCTION unauthenticated_session_insert(
    p_ip_address INET,
    p_user_agent_id INT,
    p_session_id_hash VARCHAR
)
RETURNS BOOLEAN
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public, pg_temp
AS $$
DECLARE
    v_count INT;
BEGIN
    INSERT INTO unauthenticated_sessions (
        ip_address,
        user_agent_id,
        session_id_hash,
        domain
    )
    VALUES (
        p_ip_address,
        p_user_agent_id,
        p_session_id_hash,
        SESSION_USER
    );

    -- 1. Rate Limit Check
    -- Count sessions from this IP in the last 5 hours
    SELECT COUNT(*) INTO v_count
    FROM unauthenticated_sessions
    WHERE ip_address = p_ip_address
      AND created_at > (NOW() - INTERVAL '5 hours');

    -- If limit exceeded: BAN the IP and return FALSE
    IF v_count > 5 THEN
        PERFORM ip_ban_ban(
            p_ip_address,
            '> 5 unauthenticated sessions', -- Reason
            1,                              -- Risk Score
            NOW() + INTERVAL '24 hours'     -- Expires in 24h
        );
        RETURN FALSE; -- Rate limit exceeded
    END IF;

    RETURN TRUE; -- Rate limit not exceeded
END;
$$;

CREATE OR REPLACE FUNCTION unauthenticated_session_delete(
    p_session_id_hash VARCHAR
)
RETURNS VOID
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public, pg_temp
AS $$
BEGIN
    DELETE FROM unauthenticated_sessions
    WHERE session_id_hash = p_session_id_hash
      AND domain = SESSION_USER; -- Scope deletion to the current tenant/domain
END;
$$;

CREATE OR REPLACE FUNCTION group_admin_list_group_users(
    p_username VARCHAR
)
    RETURNS TABLE (
                      username VARCHAR
                  )
    LANGUAGE plpgsql
    SECURITY DEFINER
    SET search_path = public, pg_temp
AS $$
DECLARE
    v_domain VARCHAR;
    v_pk_sequence VARCHAR;
BEGIN
    -- Check if the user has the 'group_admin' role
    IF EXISTS (
        SELECT 1
        FROM user_roles ur
        WHERE ur.username = p_username
          AND ur.role = 'group_admin'
    ) THEN
        -- Get the admin's domain and pk_sequence
        -- Validation: Ensure the user's domain matches the current session user
        SELECT u.domain, u.pk_sequence
        INTO v_domain, v_pk_sequence
        FROM users u
        WHERE u.username = p_username
          AND u.domain = SESSION_USER::VARCHAR;

        -- Proceed only if the user was found and domain matched
        IF FOUND THEN
            -- Return all users sharing the same domain and pk_sequence
            RETURN QUERY
                SELECT u.username
                FROM users u
                WHERE u.domain = v_domain
                  AND u.pk_sequence = v_pk_sequence;
        END IF;
    END IF;
END;
$$;

CREATE OR REPLACE FUNCTION group_admin_list_group_submissions(
    p_username VARCHAR
)
    RETURNS SETOF secret_room_submissions
    LANGUAGE plpgsql
    SECURITY DEFINER
    SET search_path = public, pg_temp
AS $$
BEGIN
    RETURN QUERY
        SELECT DISTINCT ON (s.username) s.*
        FROM secret_room_submissions s
                 JOIN group_admin_list_group_users(p_username) gu
                      ON s.username = gu.username
        ORDER BY s.username, s.created_at DESC;
END;
$$;