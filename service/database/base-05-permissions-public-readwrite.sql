REVOKE ALL ON ALL TABLES IN SCHEMA public FROM public-site-user;
REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM public-site-user;
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA public FROM public-site-user;

GRANT SELECT ON TABLE 
    user_agents
TO public-site-user;

GRANT INSERT ON TABLE 
    user_agents,
    secret_door_submissions, 
    secret_room_submissions
TO public-site-user;

GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO public-site-user;

GRANT EXECUTE ON FUNCTION
    pk_get,
    secret_room_submission_get,
    user_username_dispatch,
    user_get,
    user_activate,
    ip_ban_ban,
    ip_ban_check,
    unauthenticated_session_insert,
    unauthenticated_session_delete
TO public-site-user;
