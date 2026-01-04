-- Dodaj stolpce za email verifikacijo v tabelo oseba
-- Ta skripta mora biti izvedena v bazi podatkov

ALTER TABLE oseba 
ADD COLUMN verification_token VARCHAR(64) NULL,
ADD COLUMN email_verified TINYINT(1) DEFAULT 1;

-- Nastavi vse obstoječe uporabnike kot že verificirane
UPDATE oseba SET email_verified = 1 WHERE email_verified IS NULL;

-- Admin uporabnik mora biti vedno verificiran
UPDATE oseba SET email_verified = 1 WHERE e_mail = 'admin@gmail.com';

