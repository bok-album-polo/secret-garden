REVOKE ALL ON ALL TABLES IN SCHEMA public FROM dbuser;
REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM dbuser;
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA public FROM dbuser;

GRANT INSERT ON TABLE 
    registration_form_submissions, 
    contact_form_submissions, 
    users, 
    ip_bans 
TO dbuser;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO dbuser;

GRANT EXECUTE ON FUNCTION get_pk(INT) TO dbuser;
GRANT EXECUTE ON FUNCTION get_user(VARCHAR) TO dbuser;
GRANT EXECUTE ON FUNCTION dispatch_one_username() TO dbuser;
GRANT EXECUTE ON FUNCTION check_ip_ban(INET) TO dbuser;