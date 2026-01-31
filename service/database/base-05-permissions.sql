REVOKE ALL ON ALL TABLES IN SCHEMA public FROM dbuser;
REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM dbuser;
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA public FROM dbuser;

GRANT INSERT ON TABLE 
    secret_door_submissions, 
    secret_room_submissions, 
    ip_bans 
TO dbuser;

GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO dbuser;

GRANT EXECUTE ON FUNCTION
    get_pk,
    dispatch_one_username,
    get_user,
    activate_user,
    check_ip_ban
TO dbuser;
