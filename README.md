# AttestationSAP — Plugin Dolibarr Services à la Personne

> Plugin commercial pour Dolibarr 14–22+  
> Devis, factures et attestations fiscales conformes aux exigences légales des **Services à la Personne (SAP)**

---

## Fonctionnalités

- **Modèle de devis SAP** (`devis_sap_v2`) — cadre mentions obligatoires SAP, crédit d'impôt 50%
- **Modèle de facture SAP** (`facture_sap_v3`) — crédit d'impôt, intervenant, numéro de déclaration/agrément
- **Attestation fiscale annuelle** conforme art. 199 sexdecies CGI et D.7233-1 Code du travail :
  - Détail par facture (ref, date, heures, montant TTC, intervenant)
  - Nom de l'intervenant (user Dolibarr ou texte libre)
  - Numéro de déclaration SAP / agrément NOVA
  - Crédit d'impôt estimé (50%)
  - Mentions légales complètes
  - Zone de signature
- **Envoi email** par attestation ou en lot
- **Tableau de bord** devis / factures / attestations
- **Page de configuration complète** : habilitation, intervenant, nature du service, template email, logo…

---

## Conformité légale

| Texte | Objet |
|-------|-------|
| Art. 199 sexdecies CGI | Crédit d'impôt 50% pour services à domicile |
| Art. D.7233-1 Code du travail | Mentions obligatoires sur les documents SAP |
| Art. L.7232-1-1 Code du travail | Délivrance de l'attestation fiscale annuelle |
| Art. 293 B CGI | Exonération de TVA (franchise en base) |
| Décret n°2005-1698 | Agrément et déclaration préalable NOVA |

---

## Installation

1. Copier le dossier `attestationsap/` dans `htdocs/custom/` de votre Dolibarr
2. Aller dans **Configuration → Modules → AttestationSAP** et activer
3. Aller dans **SAP → Paramètres SAP** et renseigner :
   - Numéro de déclaration SAP (NOVA)
   - Votre nom (intervenant)
   - Nature du service
4. Créer vos devis et factures depuis le menu **SAP**
5. En fin d'année : **SAP → Générer les attestations**

---

## Structure des fichiers

```
attestationsap/
├── class/
│   └── SapIntervenants.class.php      # Gestion des intervenants (users Dolibarr)
├── core/modules/
│   ├── modAttestationSap.class.php    # Descripteur du module
│   ├── pdf/
│   │   └── pdf_attestation_sap.modules.php   # PDF attestation fiscale
│   ├── facture/doc/
│   │   └── pdf_facture_sap_v3.modules.php    # PDF facture SAP
│   └── propale/doc/
│       └── pdf_devis_sap_v2.modules.php      # PDF devis SAP
├── sql/
│   └── llx_attestationsap.sql         # Tables de suivi
├── langs/fr_FR/
│   └── attestationsap.lang            # Traductions FR
├── img/
│   └── logo-sap.png                   # Logo SAP (optionnel)
├── index.php                          # Interface principale
├── setup.php                          # Page de configuration
└── about.php                          # À propos / aide
```

---

## Gestion des intervenants

Compatible avec tous les types de structure :

| Structure | Configuration |
|-----------|---------------|
| Auto-entrepreneur | Votre compte Dolibarr est détecté automatiquement |
| EURL / SASU | Sélectionnez votre user dans Paramètres SAP |
| Société avec salariés | Chaque salarié = un compte Dolibarr actif |
| Sous-traitant ponctuel | Mode "Texte libre" dans Paramètres SAP |

---

## Prérequis

- Dolibarr **14.0 minimum** (testé jusqu'à 22+)
- PHP **7.4+**
- Modules Dolibarr actifs : **Factures**, **Devis/Propales**, **Sociétés**

---

## Versions

| Version | Date | Notes |
|---------|------|-------|
| 2.1.0 | 2026-03 | Intégration intervenants (SapIntervenants), conformité D.7233-1 |
| 2.0.0 | 2026-02 | Refonte complète, attestation avec détail par facture, table SQL, Dolibarr 22 |
| 1.0.0 | 2025 | Version initiale |

---

## Licence

Plugin commercial — Tous droits réservés.  
Pour toute question : [ericfalcon](https://github.com/ericfalcon)
