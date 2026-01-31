CREATE TABLE secret_door_submissions (
	id SERIAL PRIMARY KEY,
	created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
	domain VARCHAR(100) DEFAULT CURRENT_USER,
	ip_address INET,
	user_agent VARCHAR(512)
);

CREATE TABLE secret_room_submissions (
	id SERIAL PRIMARY KEY,
	created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
	created_by VARCHAR(50),
	ip_address INET,
	user_agent VARCHAR(512),
	authenticated BOOLEAN DEFAULT FALSE,
	username VARCHAR(50) NOT NULL
);

CREATE TABLE ip_bans (
	banned_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
	banned_by TEXT,
	expires_at TIMESTAMPTZ,
	id SERIAL PRIMARY KEY,
	network inet NOT NULL,
	reason TEXT
);
CREATE INDEX idx_ip_bans_network ON ip_bans USING GIST (network inet_ops);

CREATE TABLE pk_sequences (
	domain VARCHAR(100),
	pk_sequence VARCHAR(20),
	PRIMARY KEY (domain, pk_sequence)
);

CREATE TABLE users (
	username VARCHAR(50) PRIMARY KEY,
	displayname VARCHAR(100),
	password VARCHAR(255) DEFAULT NULL,
	authenticated BOOLEAN DEFAULT FALSE,
	domain VARCHAR(100) DEFAULT NULL,
	pk_sequence VARCHAR(20) DEFAULT NULL,
	created_at TIMESTAMP WITH TIME ZONE DEFAULT NULL,
	time_dispatched TIMESTAMP WITH TIME ZONE IS NOT NULL
);
CREATE INDEX idx_usernames_vending_pool ON users (time_dispatched ASC) WHERE password IS NULL;
