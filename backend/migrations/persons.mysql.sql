CREATE TABLE IF NOT EXISTS persons (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    first_name      VARCHAR(100)  NOT NULL,
    last_name       VARCHAR(100)  NOT NULL,
    email           VARCHAR(255)  NOT NULL,
    date_of_birth   DATE          NULL,
    phone_number    VARCHAR(20)   NULL,
    role            VARCHAR(20)   NULL,
    notes           VARCHAR(1000) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_persons_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
