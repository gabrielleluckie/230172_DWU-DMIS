-- Tokenized external partner view
-- Adds a unique, nullable access token used by view_agreement.php.

ALTER TABLE agreement
    ADD COLUMN access_token VARCHAR(64) NULL DEFAULT NULL;

ALTER TABLE agreement
    ADD UNIQUE KEY uq_agreement_access_token (access_token);
