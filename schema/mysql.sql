-- One row per scheme and period. Rows are only inserted, never updated.
CREATE TABLE payout_runs (
    scheme            varchar(100) NOT NULL,
    period            varchar(64)  NOT NULL,
    input_hash        char(64)     NOT NULL,
    rules_fingerprint char(64)     NOT NULL,
    input             longtext     NOT NULL, -- canonical JSON
    rules             text         NOT NULL, -- JSON
    result            longtext     NOT NULL, -- canonical JSON
    created_at        timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (scheme, period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
