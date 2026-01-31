CREATE OR REPLACE FUNCTION get_pk(
	p_sequence INT
)
RETURNS SETOF pk_sequences 
LANGUAGE plpgsql
SECURITY DEFINER  -- This allows the function to "sudo" to access the table
SET search_path = public, pg_temp
AS $$
BEGIN
	RETURN QUERY
	SELECT domain, pk_sequence
	FROM pk_sequences
	-- SESSION_USER remains the application user (e.g., 'marketing_app')
	-- even while the function executes with system privileges.
	WHERE domain = SESSION_USER::VARCHAR 
	  AND pk_sequence = p_sequence::VARCHAR
	LIMIT 1;

	IF NOT FOUND THEN
		RETURN;
	END IF;
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

CREATE FUNCTION dispatch_one_username()
RETURNS TABLE(r_username VARCHAR, r_displayname VARCHAR, r_time TIMESTAMP WITH TIME ZONE)
AS $$
BEGIN
	RETURN QUERY
	UPDATE username_pool SET time_dispatched = NOW()
	WHERE username = (
		SELECT u.username
		FROM username_pool u
		WHERE u.time_dispatched < NOW() - INTERVAL '30 minutes'
		ORDER BY u.time_dispatched ASC
		LIMIT 1
		FOR UPDATE SKIP LOCKED
	) RETURNING username, displayname, time_dispatched;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER SET search_path = public;



CREATE FUNCTION mark_username_used()
RETURNS TRIGGER AS $$
BEGIN
	UPDATE username_pool
	SET time_dispatched = '9999-12-31 23:59:59+00'
	WHERE username = NEW.username;
	RETURN NEW;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

CREATE TRIGGER trg_burn_username AFTER INSERT ON registration_form_submissions FOR EACH ROW EXECUTE FUNCTION mark_username_used();