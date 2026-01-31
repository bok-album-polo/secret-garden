-- Function: get_secret_room_submission(p_username VARCHAR)
-- Description: Retrieves the most recent secret room submission for a given username, ensuring domain security.
-- Used in readwrite mode to allow users to view their own submissions (vs writeonly mode where they cannot).

CREATE OR REPLACE FUNCTION get_secret_room_submission(
    p_username VARCHAR
)
RETURNS SETOF secret_room_submissions
LANGUAGE plpgsql
STABLE
SECURITY DEFINER
SET search_path = public, pg_temp
AS $$
BEGIN
    RETURN QUERY
    SELECT sr.*
    FROM secret_room_submissions sr
    JOIN users u ON sr.username = u.username
    WHERE sr.username = p_username
      AND u.domain = SESSION_USER::VARCHAR
    ORDER BY sr.created_at DESC
    LIMIT 1;
END;
$$;