REVOKE ALL ON ALL TABLES IN SCHEMA public FROM application;
REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM application;
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA public FROM application;

GRANT INSERT ON TABLE 
    registration_form_submissions, 
    contact_form_submissions, 
    users, 
    ip_bans 
TO application;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO application;

GRANT EXECUTE ON FUNCTION get_pk(INT) TO application;
GRANT EXECUTE ON FUNCTION get_user(VARCHAR) TO application;
GRANT EXECUTE ON FUNCTION dispatch_one_username() TO application;
GRANT EXECUTE ON FUNCTION check_ip_ban(INET) TO application;
