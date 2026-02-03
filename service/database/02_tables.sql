CREATE TABLE users
(
    username        VARCHAR(50) PRIMARY KEY,
    displayname     VARCHAR(100),
    password        VARCHAR(255) DEFAULT NULL,
    authenticated   BOOLEAN      DEFAULT FALSE,
    domain          VARCHAR(100) DEFAULT NULL,
    pk_sequence     VARCHAR(20)  DEFAULT NULL,
    activated_at    TIMESTAMPTZ  DEFAULT NULL,
    time_dispatched TIMESTAMPTZ NOT NULL
);
CREATE INDEX idx_usernames_vending_pool ON users (time_dispatched ASC) WHERE password IS NULL;

CREATE TABLE pk_sequences
(
    domain      VARCHAR(100),
    pk_sequence VARCHAR(20),
    PRIMARY KEY (domain, pk_sequence)
);

CREATE TABLE user_agents
(
    id SERIAL PRIMARY KEY,
    user_agent TEXT NOT NULL,
    CONSTRAINT uq_user_agent UNIQUE (user_agent)
);

CREATE TABLE secret_door_submissions
(
    id            SERIAL PRIMARY KEY,
    created_at    TIMESTAMPTZ  DEFAULT CURRENT_TIMESTAMP,
    domain        VARCHAR(100) DEFAULT CURRENT_USER,
    ip_address    INET,
    user_agent_id INT REFERENCES user_agents (id)
);

CREATE TABLE secret_room_submissions
(
    id            SERIAL PRIMARY KEY,
    username      VARCHAR(50) NOT NULL,
    created_at    TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    created_by    VARCHAR(50),
    domain        VARCHAR(100) DEFAULT CURRENT_USER,
    ip_address    INET,
    user_agent_id INT REFERENCES user_agents (id),
    authenticated BOOLEAN     DEFAULT FALSE
);

CREATE TABLE ip_bans
(
    id         SERIAL PRIMARY KEY,
    banned_at  TIMESTAMPTZ  DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMPTZ,
    ip_address inet NOT NULL,
    netblock   cidr NOT NULL,
    domain     VARCHAR(100) DEFAULT CURRENT_USER,
    reason     TEXT,
    risk_score INT
);
CREATE INDEX idx_ip_bans_ip ON ip_bans USING GIST (ip_address inet_ops);
CREATE INDEX idx_ip_bans_netblock ON ip_bans USING GIST (netblock inet_ops);
CREATE INDEX idx_ip_bans_expires_at ON ip_bans (expires_at ASC);

CREATE TABLE unauthenticated_sessions
(
    id              SERIAL PRIMARY KEY,
    created_at      TIMESTAMPTZ  DEFAULT CURRENT_TIMESTAMP,
    domain          VARCHAR(100) DEFAULT CURRENT_USER,
    ip_address      INET,
    user_agent_id   INT REFERENCES user_agents (id),
    session_id_hash VARCHAR(255) UNIQUE NOT NULL
);
CREATE INDEX idx_unauthenticated_sessions_created_at ON unauthenticated_sessions (created_at ASC);
CREATE INDEX idx_unauthenticated_sessions_ip ON unauthenticated_sessions USING GIST (ip_address inet_ops);