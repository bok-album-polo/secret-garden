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
	SELECT *
	FROM users u
	WHERE u.username = p_username
	LIMIT 1;
END;
$$;