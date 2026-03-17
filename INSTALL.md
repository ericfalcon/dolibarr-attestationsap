# Guide de démarrage — AttestationSAP pour Dolibarr

> Ce guide vous accompagne de l'installation jusqu'à l'envoi de votre première attestation fiscale.  
> Durée estimée : **20 à 30 minutes**

---

## Étape 1 — Installation

### Télécharger et installer le module

1. Téléchargez le fichier ZIP du module
2. Décompressez-le et **renommez le dossier en `attestationsap`** (exactement ce nom)
3. Copiez le dossier dans `htdocs/custom/` de votre installation Dolibarr

```
votre-dolibarr/
└── htdocs/
    └── custom/
        └── attestationsap/   ← le dossier doit s'appeler exactement ainsi
```

4. Dans Dolibarr : **Configuration → Modules/Applications**
5. Cherchez "AttestationSAP" et cliquez **Activer**

---

## Étape 2 — Préparer Dolibarr

Avant de configurer le module, créez les catégories nécessaires dans Dolibarr.

### 2a — Créer une catégorie pour vos produits/services SAP

1. Allez dans **Produits/Services → Catégories**
2. Créez une nouvelle catégorie, par exemple : `Services SAP`
3. Notez son nom — vous en aurez besoin à l'étape 3

### 2b — Créer une catégorie pour vos clients SAP

1. Allez dans **Tiers → Catégories**
2. Créez une nouvelle catégorie, par exemple : `Clients SAP`
3. Notez son nom

### 2c — Affecter la catégorie à vos produits SAP

1. Allez dans **Produits/Services → Liste**
2. Ouvrez chaque produit/service correspondant à une prestation SAP
3. Onglet **Catégories** → ajoutez la catégorie `Services SAP`

### 2d — Affecter la catégorie à vos clients SAP

1. Allez dans **Tiers → Liste**
2. Ouvrez chaque client bénéficiant de vos services SAP
3. Onglet **Catégories** → ajoutez la catégorie `Clients SAP`

### 2e — Vérifier votre fiche entreprise

Allez dans **Configuration → Ma société** et vérifiez que ces champs sont renseignés :
- ✅ Nom de l'entreprise
- ✅ Adresse complète
- ✅ SIRET
- ✅ Téléphone / Email
- ✅ Logo (apparaîtra en haut des attestations)

### 2f — Vérifier votre fiche utilisateur

Allez dans **Utilisateurs & Groupes → votre compte → Modifier** :
- ✅ Prénom et Nom renseignés (affiché comme intervenant sur les documents)

---

## Étape 3 — Configurer le module

Allez dans **SAP → Paramètres SAP** et renseignez les 11 sections.

### Section 1 — Habilitation SAP ⚠ Obligatoire

| Champ | Valeur |
|-------|--------|
| Type d'habilitation | **Déclaration préalable** si vous avez un numéro NOVA, sinon **Agrément préfectoral** |
| N° de déclaration SAP | Votre numéro NOVA (ex : `SAP500484498`) |
| Date de déclaration | La date figurant sur votre récépissé NOVA |

> 💡 Votre numéro SAP figure sur le récépissé de déclaration envoyé par la DIRECCTE/DREETS.

### Section 2 — Intervenant(s)

- Sélectionnez **votre compte Dolibarr** dans la liste
- Si vous avez des salariés, chaque salarié doit avoir un compte Dolibarr actif avec son Prénom/Nom

### Section 3 — Activités SAP

- Cochez **toutes vos activités** parmi les 26 activités officielles
- ⚠ Les activités marquées **Agr.** ne sont visibles qu'en mode "Agrément préfectoral"
- Le champ "Nature affichée" se remplit automatiquement

### Section 4 — Signataire

- Renseignez votre nom et votre fonction (ex : `Dirigeant`, `Gérant`)

### Section 5 — Identification des prestations ⚠ Critique

| Champ | Valeur |
|-------|--------|
| **Catégorie produit SAP** | Sélectionnez la catégorie `Services SAP` créée à l'étape 2a |
| **Catégorie tiers SAP** | Sélectionnez la catégorie `Clients SAP` créée à l'étape 2b |

> ⚠ **Ces deux champs sont essentiels.** Sans eux, le module ne peut pas identifier vos prestations SAP ni vos clients bénéficiaires.

### Section 6 — Modèles de factures

- Sélectionnez `facture_sap_v3` dans la liste (cliquez dessus)

### Section 7 — Modèles PDF par défaut

| Champ | Valeur |
|-------|--------|
| Modèle devis | `devis_sap_v2` |
| Modèle facture | `facture_sap_v3` |

### Section 8 — Options d'affichage

- ✅ Afficher le crédit d'impôt 50% → **Oui** (recommandé)
- ✅ Mention TVA non applicable → **Oui** si vous êtes en franchise de base de TVA

### Section 9 — Template email

Personnalisez l'email envoyé avec les attestations. Variables disponibles :
- `{YEAR}` → l'année de l'attestation
- `{CLIENT}` → le nom du client
- `{COMPANY}` → votre nom d'entreprise

### Section 10 — Logo SAP

- Uploadez l'image du logo "Services à la Personne" officiel si vous le souhaitez
- Format PNG ou JPG, ~200×80 px

### Section 11 — Signature et cachet

Le **cachet** est généré automatiquement depuis vos données entreprise.

Pour la **signature** :
1. Signez sur une feuille blanche
2. Scannez et supprimez le fond blanc (PNG transparent, ~300×100 px)
3. Uploadez ici

> 💡 Une fois uploadée, la signature s'appose automatiquement sur toutes les attestations — vous pouvez envoyer directement par email sans manipulation supplémentaire.

Cliquez **ENREGISTRER** en bas de page.

---

## Étape 4 — Créer vos premiers documents SAP

### Créer un devis SAP

1. **SAP → Créer un devis SAP**
2. Sélectionnez votre client, ajoutez vos prestations
3. Générez le PDF — il contient automatiquement le cadre "Mentions obligatoires SAP"

### Créer une facture SAP

1. **SAP → Créer une facture SAP**
2. Sélectionnez votre client, ajoutez vos prestations
3. Générez le PDF — il contient le crédit d'impôt 50% et les mentions légales

> ⚠ **Important :** Vos produits/services doivent être dans la catégorie `Services SAP` pour apparaître dans les attestations.

### Marquer la facture comme payée

Pour figurer dans l'attestation fiscale, la facture doit être **payée** :
1. Ouvrez la facture
2. Cliquez **Enregistrer le paiement**
3. Renseignez la date, le montant et le mode de paiement

---

## Étape 5 — Générer et envoyer les attestations

> 📅 Cette étape se fait **en janvier** pour l'année précédente.

1. **SAP → Générer les attestations**
2. Vérifiez que l'année fiscale est correcte (ex : `2025` pour les prestations de 2025)
3. Cliquez **Générer toutes les attestations**
4. Vérifiez chaque PDF (bouton **Télécharger**)
5. Sélectionnez les clients et cliquez **Envoyer**

Chaque client recevra un email avec son attestation en pièce jointe.

---

## Suivi par client

Sur chaque fiche client, un onglet **"Attestations SAP"** liste toutes les attestations générées avec leur statut d'envoi.

Les envois apparaissent également dans les **événements/agenda** du client.

---

## Résolution des problèmes courants

| Problème | Solution |
|----------|----------|
| Aucune facture trouvée | Vérifiez que le modèle PDF de la facture est `facture_sap_v3` et que la facture est **payée** |
| Heures = 0,00 h | Le produit n'est pas dans la catégorie `Services SAP` (section 5) |
| Aucun client dans la liste | Le client n'est pas dans la catégorie `Clients SAP` (section 5) |
| Intervenant vide | Renseignez Prénom/Nom dans votre fiche utilisateur Dolibarr |
| Modèle ":Aucun" | Allez dans `tools/fix_description.php` (admin uniquement) |
| Widget absent | Désactivez et réactivez le module |
| Onglet tiers absent | Désactivez et réactivez le module |

---

## Conformité légale

Ce module vous aide à respecter :

- **Art. 199 sexdecies CGI** — attestation des sommes versées pour crédit d'impôt 50%
- **Art. D.7231-1** — liste officielle des 26 activités SAP
- **Art. D.7233-1** — mentions obligatoires sur les documents SAP
- **Art. L.7232-1-1** — délivrance de l'attestation fiscale annuelle

> ℹ Ce module est un outil de gestion. Il ne remplace pas les conseils d'un expert-comptable ou d'un juriste.

---

## Support

Pour toute question sur le module, consultez d'abord :
- **SAP → Mode d'emploi** dans votre Dolibarr
- Le fichier `README.md` inclus dans le module
