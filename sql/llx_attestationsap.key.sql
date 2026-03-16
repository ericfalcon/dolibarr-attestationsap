-- htdocs/custom/attestationsap/sql/llx_attestationsap.key.sql
-- Index et clés étrangères

ALTER TABLE llx_attestationsap ADD INDEX idx_attestationsap_soc (fk_soc);
ALTER TABLE llx_attestationsap ADD INDEX idx_attestationsap_annee (annee_fiscale);
ALTER TABLE llx_attestationsap ADD INDEX idx_attestationsap_entity (entity);

ALTER TABLE llx_attestationsap_factures ADD INDEX idx_attfact_att (fk_attestation);
ALTER TABLE llx_attestationsap_factures ADD INDEX idx_attfact_fac (fk_facture);
