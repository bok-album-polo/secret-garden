-- contact from submissions
ALTER TABLE contact_form_submissions
	ENABLE ROW LEVEL SECURITY
;
CREATE POLICY domain_isolation_contact
	ON contact_form_submissions
	FOR ALL USING (domain = CURRENT_USER)
	WITH CHECK (domain = CURRENT_USER)
;

-- registration form submissions
ALTER TABLE registration_form_submissions
	ENABLE ROW LEVEL SECURITY
;
CREATE POLICY
	domain_isolation_reg ON registration_form_submissions
	FOR ALL USING (domain = CURRENT_USER)
	WITH CHECK (domain = CURRENT_USER)
;

-- sequences
ALTER TABLE pk_sequences
	ENABLE ROW LEVEL SECURITY
;
CREATE POLICY domain_isolation_pk
	ON pk_sequences
	FOR ALL USING (domain = CURRENT_USER)
	WITH CHECK (domain = CURRENT_USER)
;
