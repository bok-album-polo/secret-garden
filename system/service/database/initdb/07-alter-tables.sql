ALTER TABLE contact_form_submissions
    ADD COLUMN uploaded_file BYTEA;

ALTER TABLE contact_form_submissions
    ADD COLUMN uploaded_file_name TEXT;

ALTER TABLE registration_form_submissions
    DROP CONSTRAINT registration_form_submissions_username_fkey;