# AttestationSAP — Plugin Dolibarr Services à la Personne

> Plugin pour Dolibarr 14–22+  
> Devis, factures et attestations fiscales conformes aux exigences légales des **Services à la Personne (SAP)**

---

## Fonctionnalités

- **Modèle de devis SAP** (`devis_sap_v2`) — cadre mentions obligatoires, crédit d'impôt 50%
- **Modèle de facture SAP** (`facture_sap_v3`) — crédit d'impôt, intervenant, numéro de déclaration/agrément, mentions légales
- **Attestation fiscale annuelle** conforme art. 199 sexdecies CGI et D.7233-1 Code du travail
- **Envoi email** des attestations par client ou en lot
- **Widget tableau de bord** — derniers devis SAP, dernières factures SAP, rappel en janvier
- **Sélection des activités SAP officielles** (26 activités, décret D.7231-1) avec filtrage automatique selon le type d'habilitation
- **Page de configuration complète** en 8 sections

---

## Conformité légale

| Texte | Objet |
|-------|-------|
| Art. 199 sexdecies CGI | Crédit d'impôt 50% pour services à domicile |
| Art. D.7231-1 Code du travail | Liste officielle des activités SAP |
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
cd htdocs/custom/attestationsap && git pull origin main
```

Puis désactiver/réactiver le module dans Dolibarr.

### Via ZIP

Télécharger le ZIP depuis GitHub, extraire et **renommer le dossier en `attestationsap`** avant de le copier dans `htdocs/custom/`.

---

## Configuration (SAP → Paramètres SAP)

### Section 1 — Habilitation SAP
- **Déclaration préalable (NOVA)** : renseignez votre numéro SAP (ex : `SAP500484498`) et la date
- **Agrément préfectoral** : renseignez le numéro et la date d'agrément

### Section 2 — Intervenant(s)
- **Utilisateur Dolibarr** : sélectionnez votre compte (auto-entrepreneur) ou le compte du salarié
- **Texte libre** : pour les sous-traitants ponctuels
- ⚠ Renseignez votre Prénom et Nom dans votre fiche utilisateur Dolibarr

### Section 3 — Activités SAP & mode d'intervention
- Cochez vos activités parmi les **26 activités officielles** (décret D.7231-1)
- Les activités nécessitant un **agrément** n'apparaissent qu'en mode agrément
- Le champ "Nature affichée sur les documents" se remplit automatiquement
- Mode prestataire ou mandataire

### Section 4 — Signataire
- Nom et fonction affichés en bas des attestations fiscales

### Section 5 — Identification des prestations SAP
- Catégorie produit Dolibarr (méthode prioritaire)
- Mots-clés fallback (1 par ligne)

### Section 6 — Modèles de factures
- Sélectionnez `facture_sap_v3` (Ctrl+clic pour multi-sélection)

### Section 7 — Modèles PDF par défaut
- Devis : `devis_sap_v2`
- Facture : `facture_sap_v3`

### Section 8 — Options d'affichage & email
- Afficher/masquer le crédit d'impôt 50% sur les factures
- Mention TVA non applicable (art. 293 B CGI)
- Template d'email pour l'envoi des attestations

---

## Utilisation

### Créer un devis SAP
**SAP → Créer un devis SAP** → le modèle `devis_sap_v2` est automatiquement sélectionné avec le cadre mentions obligatoires.

### Créer une facture SAP
**SAP → Créer une facture SAP** → le modèle `facture_sap_v3` inclut :
- Crédit d'impôt 50% dans les totaux
- Cadre mentions obligatoires en bas de page
- Numéro de déclaration SAP et nature du service

### Générer les attestations fiscales
**SAP → Générer les attestations** :
1. Sélectionnez l'année fiscale
2. Cliquez **Générer toutes les attestations**
3. Sélectionnez les clients et cliquez **Envoyer**

💡 En **janvier**, le widget du tableau de bord affiche un rappel automatique.

---

## Widget tableau de bord

Activez le widget depuis **Accueil → ⚙ Configurer les widgets** → cherchez "Widget SAP".

Le widget affiche :
- 🔴 Rappel en janvier pour générer les attestations
- Les 5 derniers devis SAP avec statut
- Les 5 dernières factures SAP avec statut et date
- Lien direct vers la génération des attestations

---

## Structure des fichiers

```
attestationsap/
├── core/
│   ├── modules/
│   │   ├── modAttestationSap.class.php       # Descripteur du module
│   │   ├── facture/doc/
│   │   │   └── pdf_facture_sap_v3.modules.php # PDF facture SAP
│   │   ├── propale/doc/
│   │   │   └── pdf_devis_sap_v2.modules.php   # PDF devis SAP
│   │   └── pdf/
│   │       └── pdf_attestation_sap.modules.php # PDF attestation
│   └── boxes/
│       └── box_attestationsap.php             # Widget tableau de bord
├── class/
│   └── SapIntervenants.class.php             # Gestion des intervenants
├── sql/
│   └── llx_attestationsap.sql                # Tables de suivi
├── langs/fr_FR/
│   └── attestationsap.lang                   # Traductions FR
├── img/
│   └── logo-sap.jpg                          # Logo SAP officiel
├── build/
│   └── build.sh                              # Script génération ZIP
├── tools/                                    # Scripts de diagnostic (admin)
├── index.php                                 # Interface principale
├── setup.php                                 # Page de configuration
└── help.php                                  # Mode d'emploi intégré
```

---

## Gestion des intervenants

| Structure | Configuration |
|-----------|---------------|
| Auto-entrepreneur | Sélectionnez votre compte Dolibarr dans Paramètres SAP |
| EURL / SASU | Idem — renseignez Prénom/Nom dans votre fiche utilisateur |
| Société avec salariés | Chaque salarié = un compte Dolibarr actif |
| Sous-traitant ponctuel | Mode "Texte libre" dans Paramètres SAP |

---

## Prérequis

- Dolibarr **14.0 minimum** (testé jusqu'à 22.0)
- PHP **7.4+**
- Modules Dolibarr actifs : **Factures**, **Devis/Propales**, **Sociétés**

---

## Versions

| Version | Date | Notes |
|---------|------|-------|
| 2.1.0 | 2026-03 | Activités SAP officielles (D.7231-1), widget tableau de bord, cases à cocher avec filtrage agrément |
| 2.0.0 | 2026-02 | Refonte complète, attestation avec détail par facture, table SQL, Dolibarr 22 |
| 1.0.0 | 2025 | Version initiale |

---

## Licence

Plugin — Tous droits réservés.  
Dépôt : [github.com/ericfalcon/dolibarr-attestationsap](https://github.com/ericfalcon/dolibarr-attestationsap)
