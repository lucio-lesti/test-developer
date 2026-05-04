CREATE TABLE IF NOT EXISTS persons (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    first_name      TEXT NOT NULL,
    last_name       TEXT NOT NULL,
    email           TEXT NOT NULL,
    date_of_birth   TEXT,
    phone_number    TEXT,
    role            TEXT,
    notes           TEXT
);

CREATE UNIQUE INDEX IF NOT EXISTS uniq_persons_email ON persons(email);
