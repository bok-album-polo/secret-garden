REVOKE ALL ON ALL TABLES IN SCHEMA public FROM admin-site-user;
REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM admin-site-user;
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA public FROM admin-site-user;

GRANT SELECT ON TABLE 
    user_agents,
    secret_door_submissions, 
    secret_room_submissions, 
    ip_bans 
TO admin-site-user;

GRANT INSERT ON TABLE 
    secret_room_submissions, 
    ip_bans 
TO admin-site-user;

GRANT UPDATE (authenticated,password) ON users TO admin-site-user;
GRANT UPDATE (authenticated) ON secret_room_submissions TO admin-site-user;

GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO admin-site-user;
