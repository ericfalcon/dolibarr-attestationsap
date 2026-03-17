# AttestationSAP — Plugin Dolibarr Services à la Personne

> Plugin pour Dolibarr 14–22+  
> Devis, factures et attestations fiscales conformes aux exigences légales des **Services à la Personne (SAP)**

---

## Fonctionnalités

- **Modèle de devis SAP** (`devis_sap_v2`) — cadre mentions obligatoires, crédit d'impôt 50%
- **Modèle de facture SAP** (`facture_sap_v3`) — crédit d'impôt, intervenant, numéro de déclaration/agrément
- **Attestation fiscale annuelle** conforme art. 199 sexdecies CGI et D.7233-1 :
  - Logo entreprise automatique + logo SAP officiel
  - Tableau : N° Facture | Date | Description | Heures | Montant TTC
  - Uniquement les factures **payées** (art. 199 sexdecies CGI : "sommes versées")
  - Cachet automatique depuis les données Dolibarr + signature uploadable
  - Crédit d'impôt estimé (50%) + mentions légales complètes
- **Onglet "Attestations SAP"** sur la fiche tiers — historique des attestations par client
- **Événements agenda** à chaque envoi d'attestation
- **Envoi email** des attestations par client ou en lot
- **Widget tableau de bord** — derniers devis/factures SAP, rappel en janvier
- **26 activités SAP officielles** (décret D.7231-1) avec filtrage automatique selon l'habilitation
- **Page de configuration complète** en 11 sections

---

## Conformité légale

| Texte | Objet |
|-------|-------|
| Art. 199 sexdecies CGI | Crédit d'impôt 50% — uniquement sur sommes versées (factures payées) |
| Art. D.7231-1 Code du travail | Liste officielle des 26 activités SAP |
| Art. D.7233-1 Code du travail | Mentions obligatoires + description de la prestation |
| Art. L.7232-1-1 Code du travail | Délivrance de l'attestation fiscale annuelle |
| Art. 293 B CGI | Exonération de TVA (franchise en base) |
| Décret n°2005-1698 | Agrément et déclaration préalable NOVA |

---

## Installation

### Via Git (recommandé)

```bash
cd htdocs/custom/
git clone https://github.com/ericfalcon/dolibarr-attestationsap.git attestationsap
```

Puis : **Configuration → Modules → AttestationSAP → Activer**

### Mise à jour

```bash
cd htdocs/custom/attestationsap
git fetch origin && git reset --hard origin/main
```

Désactiver/réactiver le module si nécessaire.

---

## Configuration (SAP → Paramètres SAP)

| Section | Contenu |
|---------|---------|
| 1 — Habilitation SAP | Type (déclaration/agrément), numéro SAP NOVA, date |
| 2 — Intervenant(s) | Utilisateur Dolibarr ou texte libre |
| 3 — Activités SAP | 26 activités officielles D.7231-1, filtrage agrément automatique |
| 4 — Signataire | Nom et fonction affichés sur les attestations |
| 5 — Identification prestations | Catégorie produit SAP ou mots-clés fallback |
| 6 — Modèles de factures | Sélection des modèles pris en compte |
| 7 — Modèles PDF par défaut | `devis_sap_v2` et `facture_sap_v3` |
| 8 — Options d'affichage | Crédit d'impôt, mention TVA |
| 9 — Template email | Objet et corps du mail d'envoi |
| 10 — Logo SAP | Logo affiché dans le cadre mentions obligatoires des factures |
| 11 — Signature et cachet | Image PNG apposée automatiquement sur les attestations |

---

## Utilisation

### Devis et factures SAP
**SAP → Créer un devis SAP** / **Créer une facture SAP**

### Générer les attestations fiscales
**SAP → Générer les attestations** → Sélectionner l'année → **Générer toutes les attestations** → **Envoyer**

> 💡 En **janvier**, le widget du tableau de bord affiche un rappel automatique.

### Suivi par client
Ouvrir la fiche d'un client → onglet **"Attestations SAP"** → liste des attestations avec statut d'envoi.

---

## Signature et cachet

Le cachet est généré **automatiquement** depuis les données de votre entreprise Dolibarr (nom, adresse, SIRET, N° SAP).

Pour la signature : préparez un PNG transparent (~300×100 px) et uploadez-le en **Section 11 → Paramètres SAP**.

---

## Structure des fichiers

```
attestationsap/
├── core/
│   ├── modules/
│   │   ├── modAttestationSap.class.php
│   │   ├── attestationsap/pdf_attestation_sap.modules.php
│   │   ├── facture/doc/pdf_facture_sap_v3.modules.php
│   │   └── propale/doc/pdf_devis_sap_v2.modules.php
│   └── boxes/box_attestationsap.php
├── class/
│   ├── actions_attestationsap.class.php
│   └── SapIntervenants.class.php
├── sql/llx_attestationsap.sql
├── langs/fr_FR/attestationsap.lang
├── img/logo-sap.jpg
├── build/build.sh
├── tools/                    # Scripts admin (fix_description, fix_models, repair_models)
├── index.php                 # Interface principale
├── tiers_tab.php             # Onglet attestations sur fiche tiers
├── setup.php                 # Configuration (11 sections)
└── help.php                  # Mode d'emploi intégré
```

---

## Prérequis

- Dolibarr **14.0 minimum** (testé jusqu'à 22.0)
- PHP **7.4+**
- Modules Dolibarr actifs : **Factures**, **Devis/Propales**, **Sociétés**

---

## Versions

| Version | Date | Notes |
|---------|------|-------|
| 2.1.0 | 2026-03 | Activités SAP D.7231-1, widget, signature/cachet, onglet tiers, factures payées uniquement |
| 2.0.0 | 2026-02 | Refonte complète, attestation PDF, Dolibarr 22 |
| 1.0.0 | 2025 | Version initiale |

---

## Licence

Plugin — Tous droits réservés.  
[github.com/ericfalcon/dolibarr-attestationsap](https://github.com/ericfalcon/dolibarr-attestationsap)
