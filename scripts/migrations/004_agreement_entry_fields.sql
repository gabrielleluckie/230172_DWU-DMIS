-- Extra fields captured on the Partnership Director register form.

ALTER TABLE agreement
    ADD COLUMN Agreement_Title VARCHAR(255) NULL DEFAULT NULL AFTER Agreement_Type,
    ADD COLUMN Physical_Address TEXT NULL DEFAULT NULL AFTER Scope_Description,
    ADD COLUMN Mailing_Address TEXT NULL DEFAULT NULL AFTER Physical_Address,
    ADD COLUMN Partner_Email VARCHAR(150) NULL DEFAULT NULL AFTER Mailing_Address,
    ADD COLUMN Director_Email VARCHAR(150) NULL DEFAULT NULL AFTER Partner_Email;

ALTER TABLE partner
    ADD COLUMN Mailing_Address TEXT NULL DEFAULT NULL AFTER Address;
