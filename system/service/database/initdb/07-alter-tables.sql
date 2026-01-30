ALTER TABLE contact_form_submissions
    ADD COLUMN uploaded_file BYTEA;

ALTER TABLE contact_form_submissions
    ADD COLUMN uploaded_file_name TEXT;

ALTER TABLE registration_form_submissions
    DROP CONSTRAINT registration_form_submissions_username_fkey;

-- Move domain and pk_sequence from registrations to users table
ALTER TABLE users
    ADD COLUMN domain VARCHAR(100) DEFAULT CURRENT_USER,
    ADD COLUMN pk_sequence VARCHAR(20);

UPDATE users u
SET 
    domain = latest.domain,
    pk_sequence = latest.pk_sequence
FROM (
    SELECT DISTINCT ON (username) username, domain, pk_sequence
    FROM registration_form_submissions
    ORDER BY username, created_at DESC
) AS latest
WHERE u.username = latest.username;

ALTER TABLE registration_form_submissions
    DROP COLUMN domain,
    DROP COLUMN pk_sequence;

-- Update Policies
DROP POLICY IF EXISTS domain_isolation_users ON users;
ALTER TABLE users ENABLE ROW LEVEL SECURITY;
CREATE POLICY domain_isolation_users ON users
    FOR ALL USING (domain = CURRENT_USER)
    WITH CHECK (domain = CURRENT_USER);

DROP POLICY IF EXISTS domain_isolation_reg ON registration_form_submissions;
CREATE POLICY domain_isolation_reg ON registration_form_submissions
    FOR ALL USING (EXISTS (SELECT 1 FROM users u WHERE u.username = registration_form_submissions.username AND u.domain = CURRENT_USER))
    WITH CHECK (EXISTS (SELECT 1 FROM users u WHERE u.username = registration_form_submissions.username AND u.domain = CURRENT_USER));

-- Update Functions
DROP FUNCTION IF EXISTS get_user(VARCHAR);
CREATE OR REPLACE FUNCTION get_user(
	p_username VARCHAR
)
RETURNS TABLE (
	username VARCHAR,
	password_hash VARCHAR,
	authenticated BOOLEAN,
	domain VARCHAR,
	pk_sequence VARCHAR
) 
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public, pg_temp
AS $$
BEGIN
	RETURN QUERY
	SELECT u.username, u.password, u.authenticated, u.domain, u.pk_sequence
	FROM users u
	WHERE u.username = p_username
	LIMIT 1;
END;
$$;