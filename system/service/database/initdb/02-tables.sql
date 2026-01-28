CREATE TABLE contact_form_submissions (
	created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
	domain VARCHAR(100) DEFAULT CURRENT_USER,
	email VARCHAR(255) NOT NULL,
	id SERIAL PRIMARY KEY,
	ip_address INET,
	message TEXT NOT NULL,
	name VARCHAR(255) NOT NULL,
	user_agent VARCHAR(512)
);

CREATE TABLE ip_bans (
	banned_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
	banned_by TEXT,
	expires_at TIMESTAMPTZ,
	id SERIAL PRIMARY KEY,
	network inet NOT NULL,
	reason TEXT,
	CONSTRAINT unique_network UNIQUE (network)
);
CREATE INDEX idx_ip_bans_network ON ip_bans USING GIST (network inet_ops);

CREATE TABLE pk_sequences (
	domain VARCHAR(100),
	pk_sequence VARCHAR(20),
	PRIMARY KEY (domain, pk_sequence)
);

CREATE TABLE username_pool (
	displayname VARCHAR(100),
	time_dispatched TIMESTAMP WITH TIME ZONE DEFAULT '1970-01-01 00:00:00+00',
	username VARCHAR(50) PRIMARY KEY
);
CREATE INDEX idx_usernames_vending_pool ON username_pool (time_dispatched ASC) WHERE time_dispatched < '9999-01-01 00:00:00+00';

CREATE TABLE registration_form_submissions (
	authenticated BOOLEAN DEFAULT FALSE,
	created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
	created_by VARCHAR(50),
	domain VARCHAR(100) DEFAULT CURRENT_USER,
	email VARCHAR(255),
	id SERIAL PRIMARY KEY,
	ip_address INET,
	pk_sequence VARCHAR(20),
	user_agent VARCHAR(512),
	username VARCHAR(50) REFERENCES username_pool(username)
);

CREATE TABLE users (
	authenticated BOOLEAN DEFAULT FALSE,
	created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
	password VARCHAR(255) NOT NULL,
	username VARCHAR(50) PRIMARY KEY
);


