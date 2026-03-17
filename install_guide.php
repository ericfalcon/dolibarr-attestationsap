<?php
// htdocs/custom/attestationsap/install_guide.php — Guide de démarrage AttestationSAP

$res = 0;
if (!$res && file_exists(__DIR__ . '/../../main.inc.php'))    $res = @include __DIR__ . '/../../main.inc.php';
if (!$res && file_exists(__DIR__ . '/../../../main.inc.php')) $res = @include __DIR__ . '/../../../main.inc.php';
if (!$res) { header('HTTP/1.1 500 Internal Server Error'); echo 'Include fails'; exit; }

if (empty($user->rights->attestationsap->read) && empty($user->admin)) accessforbidden();

llxHeader('', 'Guide de démarrage — AttestationSAP');
print load_fiche_titre('Guide de démarrage — AttestationSAP v2.1', '', 'setup');

print '<div style="max-width:900px;line-height:1.8">';

// INTRO
print '<div style="background:#e8f5e9;border-left:4px solid #4caf50;padding:14px 18px;border-radius:4px;margin-bottom:28px;font-size:15px">';
print '🎉 <strong>Bienvenue dans AttestationSAP !</strong> Ce guide vous accompagne pas à pas, ';
print 'de l\'installation jusqu\'à l\'envoi de votre première attestation fiscale.<br>';
print '<small class="opacitymedium">Durée estimée : 20 à 30 minutes</small>';
print '</div>';

// SOMMAIRE
print '<h3>Étapes</h3>';
print '<ol style="font-size:15px;line-height:2">';
$etapes = array(
    'install'  => 'Installation du module',
    'dolibarr' => 'Préparer Dolibarr (catégories, fiche entreprise)',
    'config'   => 'Configurer le module (11 sections)',
    'docs'     => 'Créer vos premiers documents SAP',
    'attest'   => 'Générer et envoyer les attestations',
);
foreach ($etapes as $id => $label) {
    print '<li><a href="#'.$id.'" style="font-weight:bold">'.dol_escape_htmltag($label).'</a></li>';
}
print '</ol><hr>';

// =====================================================================
// ÉTAPE 1 : INSTALLATION
// =====================================================================
print '<h2 id="install" style="color:#1565c0;border-bottom:2px solid #1565c0;padding-bottom:6px">'."\n";
print '📦 Étape 1 — Installation du module</h2>';

print '<h4>Via le gestionnaire de modules Dolibarr (recommandé)</h4>';
print '<ol>';
print '<li>Téléchargez le fichier <strong>ZIP</strong> du module AttestationSAP</li>';
print '<li>Dans Dolibarr : <strong>Configuration → Modules/Applications</strong></li>';
print '<li>Cliquez sur <strong>"Déployer/installer un module externe"</strong> (icône en haut à droite)</li>';
print '<li>Sélectionnez le fichier ZIP et cliquez <strong>Envoyer</strong></li>';
print '<li>Le module apparaît dans la liste → cliquez <strong>Activer</strong></li>';
print '</ol>';

print '<div style="background:#e3f2fd;border-left:4px solid #2196F3;padding:10px 14px;border-radius:4px;margin:12px 0">';
print '💡 <strong>Alternative via FTP/SSH :</strong> décompressez le ZIP, renommez le dossier en <code>attestationsap</code> ';
print 'et copiez-le dans <code>htdocs/custom/</code> de votre Dolibarr. Puis activez depuis Configuration → Modules.';
print '</div>';

print '<div style="background:#fff3cd;border-left:4px solid #ffc107;padding:10px 14px;border-radius:4px;margin:12px 0">';
print '⚠ <strong>Important :</strong> Le dossier doit impérativement s\'appeler <code>attestationsap</code> (tout en minuscules, sans tiret ni espace).';
print '</div>';

// =====================================================================
// ÉTAPE 2 : PRÉPARER DOLIBARR
// =====================================================================
print '<hr><h2 id="dolibarr" style="color:#1565c0;border-bottom:2px solid #1565c0;padding-bottom:6px">';
print '⚙ Étape 2 — Préparer Dolibarr</h2>';

print '<h4>2a — Créer une catégorie pour vos produits/services SAP</h4>';
print '<ol>';
print '<li><strong>Produits/Services → Catégories</strong></li>';
print '<li>Créez une catégorie : <code>Services SAP</code> (ou tout autre nom)</li>';
print '</ol>';

print '<h4>2b — Créer une catégorie pour vos clients SAP</h4>';
print '<ol>';
print '<li><strong>Tiers → Catégories</strong></li>';
print '<li>Créez une catégorie : <code>Clients SAP</code> (ou tout autre nom)</li>';
print '</ol>';

print '<h4>2c — Affecter la catégorie à vos produits SAP</h4>';
print '<ol>';
print '<li><strong>Produits/Services → Liste</strong></li>';
print '<li>Ouvrez chaque produit correspondant à une prestation SAP</li>';
print '<li>Onglet <strong>Catégories</strong> → ajoutez <code>Services SAP</code></li>';
print '</ol>';

print '<h4>2d — Affecter la catégorie à vos clients SAP</h4>';
print '<ol>';
print '<li><strong>Tiers → Liste</strong></li>';
print '<li>Ouvrez chaque client bénéficiant de vos services SAP</li>';
print '<li>Onglet <strong>Catégories</strong> → ajoutez <code>Clients SAP</code></li>';
print '</ol>';

print '<h4>2e — Vérifier votre fiche entreprise</h4>';
print '<p>Allez dans <strong>Configuration → Ma société</strong> et vérifiez :</p>';
print '<ul>';
print '<li>✅ Nom, adresse complète, SIRET, téléphone, email</li>';
print '<li>✅ Logo uploadé (apparaîtra en haut des attestations)</li>';
print '</ul>';

print '<h4>2f — Vérifier votre fiche utilisateur</h4>';
print '<p><strong>Utilisateurs &amp; Groupes → votre compte → Modifier</strong></p>';
print '<ul><li>✅ Prénom et Nom renseignés (affiché comme intervenant sur les documents)</li></ul>';

// =====================================================================
// ÉTAPE 3 : CONFIGURATION
// =====================================================================
print '<hr><h2 id="config" style="color:#1565c0;border-bottom:2px solid #1565c0;padding-bottom:6px">';
print '🔧 Étape 3 — Configurer le module</h2>';
print '<p>Allez dans <strong>SAP → Paramètres SAP</strong> et renseignez les 11 sections :</p>';

$sections = array(
    array(
        'num'   => '1',
        'titre' => 'Habilitation SAP',
        'requis'=> true,
        'items' => array(
            '<strong>Type d\'habilitation</strong> : "Déclaration préalable" si vous avez un numéro NOVA, sinon "Agrément préfectoral"',
            '<strong>N° de déclaration SAP</strong> : votre numéro NOVA (ex : SAP500484498) — figure sur votre récépissé DIRECCTE/DREETS',
            '<strong>Date de déclaration</strong> : la date du récépissé',
        ),
    ),
    array(
        'num'   => '2',
        'titre' => 'Intervenant(s)',
        'requis'=> true,
        'items' => array(
            'Sélectionnez votre compte Dolibarr dans la liste',
            'Si vous avez des salariés : chaque salarié doit avoir un compte Dolibarr actif avec son Prénom/Nom',
        ),
    ),
    array(
        'num'   => '3',
        'titre' => 'Activités SAP',
        'requis'=> true,
        'items' => array(
            'Cochez toutes vos activités parmi les 26 activités officielles du décret D.7231-1',
            'Les activités ⚠ Agr. apparaissent uniquement en mode "Agrément préfectoral"',
            'Le champ "Nature affichée" se remplit automatiquement',
        ),
    ),
    array(
        'num'   => '4',
        'titre' => 'Signataire',
        'requis'=> false,
        'items' => array(
            'Renseignez votre nom et votre fonction (ex : Dirigeant, Gérant)',
        ),
    ),
    array(
        'num'   => '5',
        'titre' => 'Identification des prestations',
        'requis'=> true,
        'items' => array(
            '<strong>Catégorie produit SAP</strong> : sélectionnez la catégorie "Services SAP" créée à l\'étape 2a',
            '<strong>Catégorie tiers SAP</strong> : sélectionnez la catégorie "Clients SAP" créée à l\'étape 2b',
            '⚠ Ces deux champs sont essentiels — sans eux, aucune facture ni aucun client ne sera reconnu comme SAP',
        ),
    ),
    array(
        'num'   => '6',
        'titre' => 'Modèles de factures',
        'requis'=> true,
        'items' => array('Sélectionnez <code>facture_sap_v3</code> dans la liste'),
    ),
    array(
        'num'   => '7',
        'titre' => 'Modèles PDF par défaut',
        'requis'=> true,
        'items' => array(
            'Devis : <code>devis_sap_v2</code>',
            'Facture : <code>facture_sap_v3</code>',
        ),
    ),
    array(
        'num'   => '8',
        'titre' => 'Options d\'affichage',
        'requis'=> false,
        'items' => array(
            'Afficher le crédit d\'impôt 50% → <strong>Oui</strong> (recommandé)',
            'Mention TVA non applicable → <strong>Oui</strong> si vous êtes en franchise de base de TVA (art. 293 B CGI)',
        ),
    ),
    array(
        'num'   => '9',
        'titre' => 'Template email',
        'requis'=> false,
        'items' => array(
            'Personnalisez l\'email d\'envoi des attestations',
            'Variables : <code>{YEAR}</code> <code>{CLIENT}</code> <code>{COMPANY}</code>',
        ),
    ),
    array(
        'num'   => '10',
        'titre' => 'Logo SAP',
        'requis'=> false,
        'items' => array('Uploadez le logo "Services à la Personne" officiel si souhaité (~200×80 px)'),
    ),
    array(
        'num'   => '11',
        'titre' => 'Signature et cachet',
        'requis'=> false,
        'items' => array(
            'Le <strong>cachet</strong> est généré automatiquement depuis vos données entreprise — aucune action nécessaire',
            'La <strong>signature</strong> : signez sur papier, scannez, supprimez le fond blanc → PNG transparent ~300×100 px, uploadez ici',
            'Une fois uploadée, la signature s\'appose automatiquement sur toutes les attestations',
        ),
    ),
);

foreach ($sections as $s) {
    $badge = $s['requis']
        ? '<span style="background:#c62828;color:#fff;font-size:10px;padding:2px 6px;border-radius:10px;margin-left:6px">Obligatoire</span>'
        : '<span style="background:#616161;color:#fff;font-size:10px;padding:2px 6px;border-radius:10px;margin-left:6px">Optionnel</span>';
    print '<div style="border:1px solid #e0e0e0;border-radius:6px;padding:12px 16px;margin:10px 0;background:#fafafa">';
    print '<strong>Section '.$s['num'].' — '.dol_escape_htmltag($s['titre']).'</strong>'.$badge;
    print '<ul style="margin:8px 0 0 0">';
    foreach ($s['items'] as $item) print '<li>'.$item.'</li>';
    print '</ul></div>';
}

print '<br><div class="center">';
print '<a href="'.dol_buildpath('/custom/attestationsap/setup.php', 1).'" class="butAction" style="font-size:15px;padding:10px 24px">⚙ Ouvrir les Paramètres SAP maintenant</a>';
print '</div>';

// =====================================================================
// ÉTAPE 4 : PREMIERS DOCUMENTS
// =====================================================================
print '<hr><h2 id="docs" style="color:#1565c0;border-bottom:2px solid #1565c0;padding-bottom:6px">';
print '📄 Étape 4 — Créer vos premiers documents SAP</h2>';

print '<h4>Créer un devis SAP</h4>';
print '<ol>';
print '<li><strong>SAP → Créer un devis SAP</strong></li>';
print '<li>Sélectionnez votre client, ajoutez vos prestations (produits de la catégorie SAP)</li>';
print '<li>Générez le PDF → cadre "Mentions obligatoires SAP" automatique</li>';
print '</ol>';

print '<h4>Créer une facture SAP</h4>';
print '<ol>';
print '<li><strong>SAP → Créer une facture SAP</strong></li>';
print '<li>Sélectionnez votre client, ajoutez vos prestations</li>';
print '<li>Générez le PDF → crédit d\'impôt 50% et mentions légales automatiques</li>';
print '</ol>';

print '<h4>Marquer la facture comme payée ⚠</h4>';
print '<p>Pour figurer dans l\'attestation, la facture doit être <strong>payée</strong> :</p>';
print '<ol>';
print '<li>Ouvrez la facture validée</li>';
print '<li>Cliquez <strong>Enregistrer le paiement</strong></li>';
print '<li>Renseignez la date, le montant et le mode de paiement</li>';
print '</ol>';
print '<div style="background:#fff3cd;border-left:4px solid #ffc107;padding:10px 14px;border-radius:4px">';
print '⚠ Conformément à l\'art. 199 sexdecies CGI ("sommes versées"), seules les factures <strong>payées</strong> ';
print 'sont incluses dans les attestations fiscales.';
print '</div>';

// =====================================================================
// ÉTAPE 5 : ATTESTATIONS
// =====================================================================
print '<hr><h2 id="attest" style="color:#1565c0;border-bottom:2px solid #1565c0;padding-bottom:6px">';
print '📋 Étape 5 — Générer et envoyer les attestations</h2>';

print '<div style="background:#e3f2fd;border-left:4px solid #2196F3;padding:10px 14px;border-radius:4px;margin-bottom:14px">';
print '📅 Cette étape se fait <strong>en janvier</strong> pour les prestations de l\'année précédente.';
print '</div>';

print '<ol>';
print '<li><strong>SAP → Générer les attestations</strong></li>';
print '<li>Vérifiez l\'année fiscale (ex : <code>2025</code> pour les prestations de 2025)</li>';
print '<li>Cliquez <strong>Générer toutes les attestations</strong></li>';
print '<li>Vérifiez chaque PDF via le bouton <strong>Télécharger</strong></li>';
print '<li>Sélectionnez les clients et cliquez <strong>Envoyer</strong></li>';
print '</ol>';

print '<p>Chaque client reçoit un email avec son attestation en pièce jointe, signée et cachetée automatiquement.</p>';
print '<p>Le suivi est disponible sur chaque fiche client → onglet <strong>"Attestations SAP"</strong>.</p>';

// =====================================================================
// PROBLÈMES COURANTS
// =====================================================================
print '<hr><h2 style="color:#1565c0;border-bottom:2px solid #1565c0;padding-bottom:6px">🔧 Résolution des problèmes courants</h2>';

$problems = array(
    'Aucune facture trouvée lors de la génération'
        => 'Vérifiez que (1) le modèle PDF de la facture est <code>facture_sap_v3</code>, (2) la facture est <strong>payée</strong>, (3) le produit est dans la catégorie Services SAP.',
    'Aucun client dans la liste de génération'
        => 'Vérifiez que le client est bien dans la catégorie "Clients SAP" (Tiers → fiche client → onglet Catégories).',
    'Heures = 0,00 h dans le tableau'
        => 'Le produit/service n\'est pas dans la catégorie Services SAP. Ouvrez le produit → onglet Catégories → ajoutez la catégorie SAP.',
    'L\'intervenant est vide ou "Aucun intervenant configuré"'
        => 'Allez dans Utilisateurs &amp; Groupes → votre compte → Modifier et renseignez votre Prénom et Nom.',
    'Le modèle de facture affiche ":Aucun"'
        => 'Allez dans <code>SAP → Paramètres SAP → Section 6</code> et sélectionnez <code>facture_sap_v3</code>. Si le problème persiste, exécutez (admin) <code>tools/fix_description.php</code>.',
    'Le widget SAP n\'apparaît pas sur le tableau de bord'
        => 'Désactivez puis réactivez le module AttestationSAP. Ensuite : Accueil → ⚙ Configurer les widgets → activez "Widget SAP".',
    'L\'onglet "Attestations SAP" n\'apparaît pas sur la fiche client'
        => 'Désactivez puis réactivez le module AttestationSAP.',
    'La signature n\'apparaît pas sur le PDF'
        => 'Vérifiez l\'upload en Section 11 des paramètres. Supprimez l\'attestation existante et régénérez-en une nouvelle.',
);

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th style="width:40%">Problème</th><th>Solution</th></tr>';
$flip = false;
foreach ($problems as $prob => $sol) {
    print '<tr class="'.($flip?'even':'oddeven').'">';
    print '<td><strong>'.dol_escape_htmltag($prob).'</strong></td>';
    print '<td>'.$sol.'</td>';
    print '</tr>';
    $flip = !$flip;
}
print '</table>';

// FIN
print '<hr>';
print '<div class="center" style="margin:24px 0">';
print '<a href="'.dol_buildpath('/custom/attestationsap/help.php', 1).'" class="butAction">📖 Mode d\'emploi complet</a> &nbsp; ';
print '<a href="'.dol_buildpath('/custom/attestationsap/setup.php', 1).'" class="butAction">⚙ Paramètres SAP</a> &nbsp; ';
print '<a href="'.dol_buildpath('/custom/attestationsap/index.php', 1).'" class="butAction">📋 Générer les attestations</a>';
print '</div>';

print '</div>';
llxFooter();
$db->close();
