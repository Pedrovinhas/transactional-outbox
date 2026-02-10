CREATE TABLE IF NOT EXISTS inbox (
    id SERIAL PRIMARY KEY,
    event_id INTEGER NOT NULL UNIQUE,
    aggregate_id INTEGER NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    payload JSONB NOT NULL,
    trace_context VARCHAR(256),
    processed BOOLEAN DEFAULT FALSE,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP
);

CREATE INDEX idx_inbox_processed ON inbox(processed, received_at);
CREATE INDEX idx_inbox_event_id ON inbox(event_id);
