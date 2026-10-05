-- One row per scheme and period. Rows are only inserted, never updated.
CREATE TABLE payout_runs (
    scheme            varchar(100) NOT NULL,
    period            varchar(64)  NOT NULL,
    input_hash        char(64)     NOT NULL,
    rules_fingerprint char(64)     NOT NULL,
    input             text         NOT NULL, -- canonical JSON
    rules             text         NOT NULL, -- JSON
    result            text         NOT NULL, -- canonical JSON
    created_at        timestamptz  NOT NULL DEFAULT now(),
    PRIMARY KEY (scheme, period)
);
