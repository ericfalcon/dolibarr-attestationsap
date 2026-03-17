<?php
// htdocs/custom/attestationsap/help.php — Mode d'emploi AttestationSAP v2.1

$res = 0;
if (!$res && file_exists(__DIR__ . '/../../main.inc.php'))    $res = @include __DIR__ . '/../../main.inc.php';
if (!$res && file_exists(__DIR__ . '/../../../main.inc.php')) $res = @include __DIR__ . '/../../../main.inc.php';
if (!$res) { header('HTTP/1.1 500 Internal Server Error'); echo 'Include fails'; exit; }

if (empty($user->rights->attestationsap->read) && empty($user->admin)) accessforbidden();

llxHeader('', 'Mode d\'emploi — AttestationSAP');
print load_fiche_titre('Mode d\'emploi — AttestationSAP v2.1', '', 'help');
print '<div style="max-width:900px;line-height:1.7">';

// INTRO
print '<div style="background:#e8f4fd;border-left:4px solid #2196F3;padding:12px 16px;border-radius:4px;margin-bottom:24px">';
print '<strong>AttestationSAP</strong> gère l\'ensemble du cycle Services à la Personne : ';
print 'devis SAP → factures SAP → attestations fiscales annuelles signées et envoyées aux clients.';
print '</div>';

// SOMMAIRE
print '<h3>Sommaire</h3><ol style="columns:2;column-gap:40px">';
$sections = array(
    'config'      => '1. Configuration initiale',
    'devis'       => '2. Créer un devis SAP',
    'facture'     => '3. Créer une facture SAP',
    'attestation' => '4. Générer les attestations',
    'signature'   => '5. Signature et cachet',
    'tiers'       => '6. Onglet tiers',
    'widget'      => '7. Widget tableau de bord',
    'activites'   => '8. Activités SAP officielles',
    'intervenant' => '9. Gestion des intervenants',
    'legal'       => '10. Conformité légale',
    'faq'         => '11. FAQ',
);
foreach ($sections as $id => $label) print '<li><a href="#'.$id.'">'.dol_escape_htmltag($label).'</a></li>';
print '</ol><hr>';

// SECTION 1 : CONFIGURATION
print '<h3 id="config">1. Configuration initiale</h3>';
print '<p>Allez dans <strong>SAP → Paramètres SAP</strong> (11 sections) :</p>';
$steps = array(
    '1 — Habilitation SAP' => array(
        'Choisissez <strong>Déclaration préalable (NOVA)</strong> ou <strong>Agrément préfectoral</strong>',
        'Renseignez votre numéro SAP (ex : <code>SAP500484498</code>) — affiché sur tous les documents',
    ),
    '2 — Intervenant(s)' => array(
        'Sélectionnez votre compte utilisateur Dolibarr',
        '⚠ Vérifiez que votre <strong>Prénom</strong> et <strong>Nom</strong> sont renseignés dans votre fiche utilisateur',
    ),
    '3 — Activités SAP' => array(
        'Cochez vos activités parmi les <strong>26 activités officielles</strong> (décret D.7231-1)',
        'Les activités ⚠ Agr. n\'apparaissent qu\'en mode agrément préfectoral',
        'Le champ "Nature affichée" se remplit automatiquement',
    ),
    '4 — Signataire' => array('Nom et fonction affichés sur les attestations'),
    '5 — Identification des prestations' => array(
        'Créez une <strong>catégorie Dolibarr SAP</strong> et affectez-la à vos produits',
        'Ou renseignez des mots-clés (1 par ligne) comme fallback',
    ),
    '6 — Modèles de factures' => array('Sélectionnez <code>facture_sap_v3</code>'),
    '7 — Modèles PDF par défaut' => array('Devis : <code>devis_sap_v2</code> — Facture : <code>facture_sap_v3</code>'),
    '8 — Options d\'affichage' => array('Crédit d\'impôt 50%, mention TVA non applicable'),
    '9 — Template email' => array('Objet et corps du mail avec variables <code>{YEAR}</code> <code>{CLIENT}</code> <code>{COMPANY}</code>'),
    '10 — Logo SAP' => array('Logo affiché dans le cadre mentions obligatoires des factures (~200×80 px)'),
    '11 — Signature et cachet' => array(
        'Le <strong>cachet</strong> est automatique (données entreprise Dolibarr)',
        'La <strong>signature</strong> : PNG transparent ~300×100 px, uploadé ici, apposé par-dessus le cachet',
    ),
);
foreach ($steps as $section => $items) {
    print '<p><strong>'.$section.'</strong></p><ul>';
    foreach ($items as $item) print '<li>'.$item.'</li>';
    print '</ul>';
}

// SECTION 2 : DEVIS
print '<hr><h3 id="devis">2. Créer un devis SAP</h3>';
print '<ol>';
print '<li><strong>SAP → Créer un devis SAP</strong></li>';
print '<li>Sélectionnez le client, ajoutez les lignes de prestation</li>';
print '<li>Le modèle <code>devis_sap_v2</code> est automatiquement sélectionné</li>';
print '<li>Le PDF inclut le cadre "Mentions obligatoires SAP" avec N° de déclaration, nature du service et mode d\'intervention</li>';
print '</ol>';

// SECTION 3 : FACTURE
print '<hr><h3 id="facture">3. Créer une facture SAP</h3>';
print '<ol>';
print '<li><strong>SAP → Créer une facture SAP</strong></li>';
print '<li>Sélectionnez le client, ajoutez les lignes de prestation</li>';
print '<li>Le modèle <code>facture_sap_v3</code> est automatiquement sélectionné</li>';
print '<li>Le PDF inclut le crédit d\'impôt 50%, le cadre mentions obligatoires et la mention TVA</li>';
print '</ol>';
print '<div style="background:#fff3cd;border-left:4px solid #ffc107;padding:10px 14px;border-radius:4px">';
print '💡 <strong>Important :</strong> Pour figurer dans l\'attestation, la facture doit être <strong>payée</strong> ';
print '(art. 199 sexdecies CGI : "sommes versées") et le produit doit correspondre à votre catégorie SAP ou mots-clés.';
print '</div>';

// SECTION 4 : ATTESTATION
print '<hr><h3 id="attestation">4. Générer les attestations fiscales</h3>';
print '<p>En <strong>janvier</strong> de chaque année, pour l\'année précédente :</p>';
print '<ol>';
print '<li><strong>SAP → Générer les attestations</strong></li>';
print '<li>Sélectionnez l\'année fiscale (N-1 par défaut)</li>';
print '<li>Cliquez <strong>Générer toutes les attestations</strong></li>';
print '<li>Vérifiez les PDF (bouton Télécharger)</li>';
print '<li>Sélectionnez et cliquez <strong>Envoyer</strong></li>';
print '</ol>';
print '<p>Chaque attestation contient : logo entreprise, coordonnées prestataire/bénéficiaire, intervenant, ';
print 'nature des services, tableau des factures payées, crédit d\'impôt 50%, mentions légales, signature + cachet.</p>';

// SECTION 5 : SIGNATURE
print '<hr><h3 id="signature">5. Signature et cachet</h3>';
print '<h4>Cachet automatique (fond)</h4>';
print '<p>Généré automatiquement depuis <strong>Configuration → Ma société</strong> : nom, adresse, SIRET, N° SAP. Aucune action nécessaire.</p>';
print '<h4>Signature (premier plan, par-dessus le cachet)</h4>';
print '<ol>';
print '<li>Signez sur papier blanc, scannez</li>';
print '<li>Supprimez le fond blanc → PNG transparent (~300×100 px)</li>';
print '<li><strong>SAP → Paramètres SAP → Section 11</strong> → Uploader</li>';
print '</ol>';
print '<div style="background:#e8f5e9;border-left:4px solid #4caf50;padding:10px 14px;border-radius:4px">';
print '✅ Attestations signées et cachetées automatiquement — envoi direct par email.';
print '</div>';

// SECTION 6 : ONGLET TIERS
print '<hr><h3 id="tiers">6. Onglet tiers</h3>';
print '<p>Sur chaque fiche client, un onglet <strong>"Attestations SAP"</strong> liste :</p>';
print '<ul>';
print '<li>Toutes les attestations générées pour ce client</li>';
print '<li>L\'année fiscale, la taille du fichier, la date de génération</li>';
print '<li>Le statut d\'envoi (email + date)</li>';
print '<li>Un lien direct vers la gestion des attestations</li>';
print '</ul>';
print '<p>Les envois d\'attestations apparaissent également dans les <strong>événements/agenda</strong> du client.</p>';

// SECTION 7 : WIDGET
print '<hr><h3 id="widget">7. Widget tableau de bord</h3>';
print '<p>Activez depuis <strong>Accueil → ⚙ Configurer les widgets</strong> → "Widget SAP".</p>';
print '<ul>';
print '<li>🔴 Rappel en janvier pour générer les attestations</li>';
print '<li>5 derniers devis SAP avec statut</li>';
print '<li>5 dernières factures SAP avec montant et date</li>';
print '<li>Lien direct vers la génération des attestations</li>';
print '</ul>';

// SECTION 8 : ACTIVITES
print '<hr><h3 id="activites">8. Activités SAP officielles</h3>';
print '<p>26 activités officielles du décret D.7231-1, réparties en 6 familles :</p>';
$familles = array(
    'Garde d\'enfants'           => 'Garde à domicile (<3 ans ⚠ Agr. / 3 ans+), accompagnement ⚠ Agr.',
    'Assistance aux personnes'   => 'Personnes âgées ⚠ Agr., handicapées ⚠ Agr., mobilité ⚠ Agr., conduite ⚠ Agr.',
    'Entretien & vie quotidienne'=> 'Ménage, jardinage, nettoyage vitres, préparation repas, livraison repas ⚠ Agr.',
    'Assistance technique'       => 'Informatique, administrative, animaux, maintenance résidence',
    'Soutien scolaire & cours'   => 'Cours particuliers, informatique, musique, autres cours',
    'Soins & bien-être'          => 'Soins non médicaux ⚠ Agr., sport, numérique, téléassistance ⚠ Agr., interprète ⚠ Agr.',
);
print '<table class="noborder centpercent"><tr class="liste_titre"><th>Famille</th><th>Activités</th></tr>';
$flip = false;
foreach ($familles as $f => $desc) {
    print '<tr class="'.($flip?'even':'oddeven').'"><td><strong>'.dol_escape_htmltag($f).'</strong></td><td>'.$desc.'</td></tr>';
    $flip = !$flip;
}
print '</table>';
print '<p><small class="opacitymedium">⚠ Agr. = agrément préfectoral requis — apparaît uniquement si "Agrément préfectoral" est sélectionné en section 1.</small></p>';

// SECTION 9 : INTERVENANTS
print '<hr><h3 id="intervenant">9. Gestion des intervenants</h3>';
print '<table class="noborder centpercent"><tr class="liste_titre"><th>Structure</th><th>Configuration</th></tr>';
$interv = array(
    'Auto-entrepreneur' => 'Sélectionnez votre compte Dolibarr — renseignez Prénom/Nom dans votre fiche utilisateur',
    'EURL / SASU'       => 'Idem — votre compte = l\'intervenant',
    'Société + salariés'=> 'Chaque salarié = un compte Dolibarr actif',
    'Sous-traitant'     => 'Mode "Texte libre" dans section 2 des paramètres',
);
$flip = false;
foreach ($interv as $s => $c) {
    print '<tr class="'.($flip?'even':'oddeven').'"><td><strong>'.dol_escape_htmltag($s).'</strong></td><td>'.dol_escape_htmltag($c).'</td></tr>';
    $flip = !$flip;
}
print '</table>';

// SECTION 10 : LÉGAL
print '<hr><h3 id="legal">10. Conformité légale</h3>';
print '<table class="noborder centpercent"><tr class="liste_titre"><th>Texte</th><th>Objet</th><th>Où dans le module</th></tr>';
$legal = array(
    array('Art. 199 sexdecies CGI',     'Crédit d\'impôt 50% — sommes versées',         'Factures payées uniquement + attestations'),
    array('Art. D.7231-1 C. travail',   'Liste officielle 26 activités SAP',             'Section 3 des paramètres'),
    array('Art. D.7233-1 C. travail',   'Mentions obligatoires + description prestation','Cadre factures + colonne Description attestation'),
    array('Art. L.7232-1-1 C. travail', 'Délivrance attestation fiscale annuelle',       'Module Générer les attestations'),
    array('Art. 293 B CGI',             'TVA non applicable',                            'Mention cadre SAP des factures'),
);
$flip = false;
foreach ($legal as $row) {
    print '<tr class="'.($flip?'even':'oddeven').'">';
    foreach ($row as $cell) print '<td>'.dol_escape_htmltag($cell).'</td>';
    print '</tr>';
    $flip = !$flip;
}
print '</table>';

// SECTION 11 : FAQ
print '<hr><h3 id="faq">11. FAQ</h3>';
$faq = array(
    'Mes factures n\'apparaissent pas dans les attestations'
        => 'Vérifiez que la facture est bien <strong>payée</strong> (statut vert dans Dolibarr), que le modèle PDF est <code>facture_sap_v3</code>, et que les lignes correspondent à votre catégorie SAP ou mots-clés (section 5).',
    'Les heures affichent 0,00 h'
        => 'Le produit n\'est pas reconnu comme SAP. Affectez-le à votre catégorie SAP ou ajoutez son libellé dans les mots-clés (section 5).',
    'L\'intervenant est vide'
        => 'Renseignez votre Prénom et Nom dans votre fiche utilisateur (Utilisateurs &amp; Groupes → votre compte → Modifier).',
    'Le modèle facture_sap_v3 affiche ":Aucun"'
        => 'Allez dans <code>tools/fix_description.php</code> pour corriger la base de données.',
    'L\'onglet Attestations SAP n\'apparaît pas sur la fiche tiers'
        => 'Désactivez puis réactivez le module AttestationSAP — l\'onglet est enregistré à l\'activation.',
    'Le widget SAP n\'apparaît pas dans la liste'
        => 'Désactivez puis réactivez le module. Le widget est enregistré automatiquement.',
    'Comment mettre à jour le module ?'
        => '<code>cd htdocs/custom/attestationsap && git fetch origin && git reset --hard origin/main</code>',
    'La signature n\'apparaît pas'
        => 'Vérifiez l\'upload en section 11. Supprimez l\'attestation existante et régénérez-en une nouvelle.',
    'Déclaration ou agrément ?'
        => 'La déclaration préalable (NOVA) suffit pour ménage, informatique, cours. L\'agrément préfectoral est requis pour personnes âgées, handicapées, enfants <3 ans.',
);
foreach ($faq as $q => $r) {
    print '<details style="margin-bottom:8px;border:1px solid #dee2e6;border-radius:4px">';
    print '<summary style="padding:10px 14px;cursor:pointer;font-weight:bold">'.dol_escape_htmltag($q).'</summary>';
    print '<div style="padding:10px 14px;background:#f8f9fa">'.$r.'</div>';
    print '</details>';
}

print '</div>';
print '<br><div class="center">';
print '<a href="'.dol_buildpath('/custom/attestationsap/setup.php', 1).'" class="butAction">⚙ Paramètres SAP</a> &nbsp; ';
print '<a href="'.dol_buildpath('/custom/attestationsap/index.php', 1).'" class="butAction">📋 Générer les attestations</a>';
print '</div><br>';

llxFooter();
$db->close();
