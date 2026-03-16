-- htdocs/custom/attestationsap/sql/llx_attestationsap.sql
-- Table de suivi des attestations fiscales SAP
-- Compatible MySQL / MariaDB — Dolibarr 14+

CREATE TABLE IF NOT EXISTS llx_attestationsap (
    rowid               INT AUTO_INCREMENT NOT NULL,
    entity              INT DEFAULT 1 NOT NULL,
    fk_soc              INT NOT NULL,
    annee_fiscale       SMALLINT NOT NULL,
    filename            VARCHAR(255) NOT NULL,
    filepath            VARCHAR(512),
    total_ttc           DOUBLE(24,8) DEFAULT 0,
    total_hours         DOUBLE(24,8) DEFAULT 0,
    credit_impot        DOUBLE(24,8) DEFAULT 0,
    date_generation     DATETIME,
    date_envoi          DATETIME,
    email_destinataire  VARCHAR(255),
    fk_user_gen         INT,
    fk_user_envoi       INT,
    statut              TINYINT DEFAULT 0 COMMENT '0=generee,1=envoyee,2=archivee',
    note_private        TEXT,
    tms                 TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    datec               DATETIME NOT NULL,
    PRIMARY KEY (rowid),
    INDEX idx_attestationsap_soc    (fk_soc),
    INDEX idx_attestationsap_annee  (annee_fiscale),
    INDEX idx_attestationsap_entity (entity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS llx_attestationsap_factures (
    rowid               INT AUTO_INCREMENT NOT NULL,
    fk_attestation      INT NOT NULL,
    fk_facture          INT NOT NULL,
    total_ttc_sap       DOUBLE(24,8) DEFAULT 0,
    heures_sap          DOUBLE(24,8) DEFAULT 0,
    PRIMARY KEY (rowid),
    INDEX idx_attfact_att (fk_attestation),
    INDEX idx_attfact_fac (fk_facture)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
