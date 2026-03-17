# AttestationSAP — Plugin Dolibarr Services à la Personne

> Plugin pour Dolibarr 14–22+  
> Devis, factures et attestations fiscales conformes aux exigences légales des **Services à la Personne (SAP)**

---

## Fonctionnalités

- **Modèle de devis SAP** (`devis_sap_v2`) — cadre mentions obligatoires, crédit d'impôt 50%
- **Modèle de facture SAP** (`facture_sap_v3`) — crédit d'impôt, intervenant, numéro de déclaration/agrément, mentions légales
- **Attestation fiscale annuelle** conforme art. 199 sexdecies CGI et D.7233-1 Code du travail
  - Tableau détaillé : N° Facture, Date, Description, Heures, Montant TTC
  - Signature et cachet intégrés automatiquement (image uploadée)
  - Crédit d'impôt estimé (50%)
  - Mentions légales complètes
- **Envoi email** des attestations par client ou en lot
- **Widget tableau de bord** — derniers devis SAP, dernières factures SAP, rappel en janvier
- **26 activités SAP officielles** (décret D.7231-1) avec filtrage automatique selon le type d'habilitation
- **Signature/cachet uploadable** — intégrée automatiquement dans les PDF envoyés par email
- **Page de configuration complète** en 11 sections

---

## Conformité légale

| Texte | Objet |
|-------|-------|
| Art. 199 sexdecies CGI | Crédit d'impôt 50% pour services à domicile |
| Art. D.7231-1 Code du travail | Liste officielle des 26 activités SAP |
| Art. D.7233-1 Code du travail | Mentions obligatoires sur les documents SAP |
| Art. L.7232-1-1 Code du travail | Délivrance de l'attestation fiscale annuelle |
| Art. 293 B CGI | Exonération de TVA (franchise en base) |
| Décret n°2005-1698 | Agrément et déclaration préalable NOVA |

---

## Installation

### Via Git (recommandé pour les mises à jour)

```bash
cd htdocs/custom/
git clone https://github.com/ericfalcon/dolibarr-attestationsap.git attestationsap
```

Puis dans Dolibarr : **Configuration → Modules → AttestationSAP → Activer**

### Mise à jour

```bash
cd htdocs/custom/attestationsap
git fetch origin && git reset --hard origin/main
```

Puis désactiver/réactiver le module dans Dolibarr si nécessaire.

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
| 9 — Template email | Objet et corps du mail d'envoi des attestations |
| 10 — Logo SAP | Logo affiché dans le cadre mentions obligatoires des factures |
| 11 — Signature et cachet | Image PNG apposée automatiquement sur les attestations |

---

## Utilisation

### Créer un devis SAP
**SAP → Créer un devis SAP** → modèle `devis_sap_v2` automatiquement sélectionné.

### Créer une facture SAP
**SAP → Créer une facture SAP** → modèle `facture_sap_v3` avec crédit d'impôt 50% et mentions obligatoires.

### Générer les attestations fiscales
**SAP → Générer les attestations** → sélectionnez l'année → **Générer toutes les attestations** → **Envoyer**.

> 💡 En **janvier**, le widget du tableau de bord affiche un rappel automatique.

---

## Signature et cachet des attestations

Pour que les attestations envoyées par email soient légalement valables et complètes :

**Cachet automatique** — généré automatiquement à partir des données de votre entreprise Dolibarr (nom, adresse, SIRET, téléphone, N° SAP). Aucune configuration nécessaire.

**Signature** — image PNG apposée au premier plan par-dessus le cachet :

1. Signez sur papier, scannez, supprimez le fond blanc → PNG transparent (~300×100 px)
2. **SAP → Paramètres SAP → Section 11** → Uploader la signature
3. Régénérez les attestations → signature intégrée automatiquement par-dessus le cachet

---

## Structure des fichiers

```
attestationsap/
├── core/
│   ├── modules/
│   │   ├── modAttestationSap.class.php
│   │   ├── attestationsap/
│   │   │   └── pdf_attestation_sap.modules.php
│   │   ├── facture/doc/
│   │   │   └── pdf_facture_sap_v3.modules.php
│   │   └── propale/doc/
│   │       └── pdf_devis_sap_v2.modules.php
│   └── boxes/
│       └── box_attestationsap.php
├── class/
│   └── SapIntervenants.class.php
├── sql/
│   └── llx_attestationsap.sql
├── langs/fr_FR/
│   └── attestationsap.lang
├── img/
│   └── logo-sap.jpg
├── build/
│   └── build.sh
├── tools/                    # Scripts de diagnostic (admin)
├── index.php                 # Interface principale
├── setup.php                 # Page de configuration (11 sections)
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
| 2.1.0 | 2026-03 | Activités SAP officielles (D.7231-1), widget, signature/cachet uploadable, tableau attestation conforme D.7233-1 |
| 2.0.0 | 2026-02 | Refonte complète, attestation avec détail par facture, Dolibarr 22 |
| 1.0.0 | 2025 | Version initiale |

---

## Licence

Plugin — Tous droits réservés.  
Dépôt : [github.com/ericfalcon/dolibarr-attestationsap](https://github.com/ericfalcon/dolibarr-attestationsap)
