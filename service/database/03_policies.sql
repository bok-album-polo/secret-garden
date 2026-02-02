ALTER TABLE secret_door_submissions
	ENABLE ROW LEVEL SECURITY
;
CREATE POLICY domain_isolation_secret_door
	ON secret_door_submissions
	FOR ALL USING (domain = CURRENT_USER)
	WITH CHECK (domain = CURRENT_USER)
;

ALTER TABLE secret_room_submissions
	ENABLE ROW LEVEL SECURITY
;
CREATE POLICY
	domain_isolation_secret_room ON secret_room_submissions
	FOR ALL USING (domain = CURRENT_USER)
	WITH CHECK (domain = CURRENT_USER)
;

ALTER TABLE pk_sequences
	ENABLE ROW LEVEL SECURITY
;
CREATE POLICY domain_isolation_pk
	ON pk_sequences
	FOR ALL USING (domain = CURRENT_USER)
	WITH CHECK (domain = CURRENT_USER)
;

ALTER TABLE ip_bans
	ENABLE ROW LEVEL SECURITY
;
CREATE POLICY domain_isolation_ip_bans
	ON ip_bans
	FOR ALL USING (domain = CURRENT_USER)
	WITH CHECK (domain = CURRENT_USER)
;

ALTER TABLE unauthenticated_sessions
	ENABLE ROW LEVEL SECURITY
;
CREATE POLICY domain_isolation_unauthenticated_sessions
	ON unauthenticated_sessions
	FOR ALL USING (domain = CURRENT_USER)
	WITH CHECK (domain = CURRENT_USER)
;
