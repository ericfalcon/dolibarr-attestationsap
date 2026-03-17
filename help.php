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

// ---- INTRO ----
print '<div style="background:#e8f4fd;border-left:4px solid #2196F3;padding:12px 16px;border-radius:4px;margin-bottom:24px">';
print '<strong>AttestationSAP</strong> gère l\'ensemble du cycle Services à la Personne dans Dolibarr : ';
print 'devis SAP → factures SAP → attestations fiscales annuelles envoyées aux clients.';
print '</div>';

// ---- SOMMAIRE ----
print '<h3>Sommaire</h3>';
print '<ol style="columns:2;column-gap:40px">';
$sections = array(
    'config'       => '1. Configuration initiale',
    'devis'        => '2. Créer un devis SAP',
    'facture'      => '3. Créer une facture SAP',
    'attestation'  => '4. Générer les attestations',
    'widget'       => '5. Widget tableau de bord',
    'activites'    => '6. Activités SAP officielles',
    'intervenant'  => '7. Gestion des intervenants',
    'legal'        => '8. Conformité légale',
    'faq'          => '9. FAQ',
);
foreach ($sections as $id => $label) {
    print '<li><a href="#'.$id.'">'.dol_escape_htmltag($label).'</a></li>';
}
print '</ol><hr>';

// ---- SECTION 1 : CONFIGURATION ----
print '<h3 id="config">1. Configuration initiale</h3>';
print '<p>Allez dans <strong>SAP → Paramètres SAP</strong> et renseignez :</p>';

$config_steps = array(
    '1 — Habilitation SAP' => array(
        'Choisissez <strong>Déclaration préalable (NOVA)</strong> ou <strong>Agrément préfectoral</strong>',
        'Renseignez votre numéro SAP (ex : <code>SAP500484498</code>) — il sera affiché sur tous les documents',
        'Renseignez la date d\'obtention',
    ),
    '2 — Intervenant(s)' => array(
        'Sélectionnez votre compte utilisateur Dolibarr (auto-entrepreneur)',
        '⚠ Vérifiez que votre <strong>Prénom</strong> et <strong>Nom</strong> sont renseignés dans votre fiche utilisateur',
        'Mode "Texte libre" pour les sous-traitants ponctuels',
    ),
    '3 — Activités SAP' => array(
        'Cochez vos activités parmi les <strong>26 activités officielles</strong> (décret D.7231-1)',
        'Les activités nécessitant un agrément n\'apparaissent qu\'en mode agrément',
        'Le champ "Nature affichée" se remplit automatiquement avec toutes vos activités cochées',
    ),
    '4 — Signataire' => array(
        'Renseignez votre nom et fonction (affichés en bas des attestations)',
    ),
    '5 — Identification des prestations' => array(
        'Créez une <strong>catégorie Dolibarr</strong> dédiée SAP et affectez-la à vos produits/services',
        'Ou renseignez des mots-clés (1 par ligne) comme fallback',
    ),
    '6 — Modèles de factures' => array(
        'Sélectionnez <code>facture_sap_v3</code> dans la liste',
    ),
    '7 — Modèles PDF par défaut' => array(
        'Devis : <code>devis_sap_v2</code>',
        'Facture : <code>facture_sap_v3</code>',
    ),
    '8 — Options' => array(
        'Activez/désactivez l\'affichage du crédit d\'impôt 50% sur les factures',
        'Personnalisez le template email d\'envoi des attestations',
    ),
);

foreach ($config_steps as $section => $items) {
    print '<p><strong>'.$section.'</strong></p><ul>';
    foreach ($items as $item) print '<li>'.$item.'</li>';
    print '</ul>';
}

// ---- SECTION 2 : DEVIS ----
print '<hr><h3 id="devis">2. Créer un devis SAP</h3>';
print '<ol>';
print '<li>Cliquez sur <strong>SAP → Créer un devis SAP</strong></li>';
print '<li>Sélectionnez votre client, ajoutez les lignes de prestation</li>';
print '<li>Le modèle <code>devis_sap_v2</code> est automatiquement sélectionné</li>';
print '<li>Cliquez <strong>Générer</strong> → le PDF inclut :<ul>';
print '<li>Cadre "Mentions obligatoires SAP" avec votre numéro de déclaration</li>';
print '<li>Nature du service et mode d\'intervention</li>';
print '</ul></li>';
print '</ol>';

// ---- SECTION 3 : FACTURE ----
print '<hr><h3 id="facture">3. Créer une facture SAP</h3>';
print '<ol>';
print '<li>Cliquez sur <strong>SAP → Créer une facture SAP</strong></li>';
print '<li>Sélectionnez votre client, ajoutez les lignes de prestation</li>';
print '<li>Le modèle <code>facture_sap_v3</code> est automatiquement sélectionné</li>';
print '<li>Cliquez <strong>Générer</strong> → le PDF inclut :<ul>';
print '<li>Ligne <strong>Crédit d\'impôt 50%</strong> dans les totaux (art. 199 sexdecies CGI)</li>';
print '<li>Cadre mentions obligatoires : numéro SAP, nature du service, intervenant</li>';
print '<li>Mention TVA non applicable (art. 293 B CGI) si applicable</li>';
print '</ul></li>';
print '</ol>';
print '<div style="background:#fff3cd;border-left:4px solid #ffc107;padding:10px 14px;border-radius:4px">';
print '💡 <strong>Important :</strong> Pour que la facture soit prise en compte dans l\'attestation, ';
print 'la ligne de prestation doit correspondre à votre catégorie SAP ou aux mots-clés configurés.';
print '</div>';

// ---- SECTION 4 : ATTESTATION ----
print '<hr><h3 id="attestation">4. Générer les attestations fiscales</h3>';
print '<p>En <strong>janvier de chaque année</strong>, générez et envoyez les attestations de l\'année précédente :</p>';
print '<ol>';
print '<li>Allez dans <strong>SAP → Générer les attestations</strong></li>';
print '<li>Sélectionnez l\'année fiscale (N-1 par défaut en janvier)</li>';
print '<li>Cliquez <strong>Générer toutes les attestations</strong></li>';
print '<li>Vérifiez les PDF générés (téléchargeables)</li>';
print '<li>Sélectionnez les clients et cliquez <strong>Envoyer</strong></li>';
print '</ol>';
print '<p>Chaque attestation PDF contient :</p>';
print '<ul>';
print '<li>Coordonnées du prestataire et du bénéficiaire</li>';
print '<li>Liste des factures avec dates, heures et montants</li>';
print '<li>Total annuel et crédit d\'impôt estimé (50%)</li>';
print '<li>Mentions légales obligatoires</li>';
print '<li>Zone de signature</li>';
print '</ul>';

// ---- SECTION 5 : WIDGET ----
print '<hr><h3 id="widget">5. Widget tableau de bord</h3>';
print '<p>Activez le widget depuis <strong>Accueil → ⚙ Configurer les widgets</strong> → "Widget SAP".</p>';
print '<p>Le widget affiche :</p>';
print '<ul>';
print '<li>🔴 <strong>Rappel en janvier</strong> pour générer les attestations</li>';
print '<li>Les 5 derniers <strong>devis SAP</strong> avec statut</li>';
print '<li>Les 5 dernières <strong>factures SAP</strong> avec montant et date</li>';
print '<li>Lien direct vers la génération des attestations</li>';
print '</ul>';

// ---- SECTION 6 : ACTIVITES ----
print '<hr><h3 id="activites">6. Activités SAP officielles</h3>';
print '<p>Le module propose les <strong>26 activités officielles</strong> du décret D.7231-1, réparties en 6 familles :</p>';

$familles = array(
    'Garde d\'enfants'              => 'Garde à domicile (<3 ans et 3 ans+), accompagnement',
    'Assistance aux personnes'      => 'Personnes âgées, handicapées, aide à la mobilité — <span style="color:#e67e22">⚠ Agrément requis</span>',
    'Entretien & vie quotidienne'   => 'Ménage, jardinage, nettoyage vitres, préparation repas',
    'Assistance technique'          => 'Informatique, administrative, animaux, maintenance résidence',
    'Soutien scolaire & cours'      => 'Cours particuliers, informatique, musique',
    'Soins & bien-être'             => 'Soins non médicaux, sport, numérique, téléassistance — <span style="color:#e67e22">⚠ Agrément requis</span>',
);
print '<table class="noborder centpercent"><tr class="liste_titre"><th>Famille</th><th>Activités</th></tr>';
$flip = false;
foreach ($familles as $f => $desc) {
    print '<tr class="'.($flip?'even':'oddeven').'"><td><strong>'.dol_escape_htmltag($f).'</strong></td><td>'.$desc.'</td></tr>';
    $flip = !$flip;
}
print '</table>';
print '<p><small class="opacitymedium">Les activités marquées ⚠ Agrément n\'apparaissent dans le formulaire que si vous avez sélectionné "Agrément préfectoral" dans la section Habilitation.</small></p>';

// ---- SECTION 7 : INTERVENANT ----
print '<hr><h3 id="intervenant">7. Gestion des intervenants</h3>';
print '<table class="noborder centpercent"><tr class="liste_titre"><th>Structure</th><th>Configuration</th></tr>';
$intervenants = array(
    'Auto-entrepreneur'    => 'Sélectionnez votre compte Dolibarr — renseignez Prénom/Nom dans votre fiche utilisateur',
    'EURL / SASU'          => 'Idem — votre compte utilisateur = l\'intervenant',
    'Société + salariés'   => 'Chaque salarié = un compte Dolibarr actif — sélectionnez le bon compte sur chaque facture',
    'Sous-traitant'        => 'Mode "Texte libre" dans Paramètres SAP — saisissez le nom directement',
);
$flip = false;
foreach ($intervenants as $struct => $conf) {
    print '<tr class="'.($flip?'even':'oddeven').'"><td><strong>'.dol_escape_htmltag($struct).'</strong></td><td>'.dol_escape_htmltag($conf).'</td></tr>';
    $flip = !$flip;
}
print '</table>';

// ---- SECTION 8 : LÉGAL ----
print '<hr><h3 id="legal">8. Conformité légale</h3>';
print '<table class="noborder centpercent"><tr class="liste_titre"><th>Texte</th><th>Objet</th><th>Où dans le module</th></tr>';
$legal = array(
    array('Art. 199 sexdecies CGI',      'Crédit d\'impôt 50% services à domicile',        'Ligne crédit d\'impôt sur factures et attestations'),
    array('Art. D.7231-1 C. travail',    'Liste officielle des 26 activités SAP',           'Cases à cocher dans Paramètres SAP'),
    array('Art. D.7233-1 C. travail',    'Mentions obligatoires sur documents SAP',         'Cadre bleu en bas des factures et devis'),
    array('Art. L.7232-1-1 C. travail',  'Délivrance attestation fiscale annuelle',         'Module Générer les attestations'),
    array('Art. 293 B CGI',              'TVA non applicable (franchise en base)',           'Mention dans le cadre SAP des factures'),
);
$flip = false;
foreach ($legal as $row) {
    print '<tr class="'.($flip?'even':'oddeven').'">';
    foreach ($row as $cell) print '<td>'.dol_escape_htmltag($cell).'</td>';
    print '</tr>';
    $flip = !$flip;
}
print '</table>';

// ---- SECTION 9 : FAQ ----
print '<hr><h3 id="faq">9. FAQ</h3>';
$faq = array(
    'Mes factures n\'apparaissent pas dans les attestations'
        => 'Vérifiez que le modèle PDF de la facture est bien <code>facture_sap_v3</code> et que les lignes correspondent à votre catégorie SAP ou mots-clés.',
    'Le modèle facture_sap_v3 affiche ":Aucun"'
        => 'Allez dans <code>tools/fix_description.php</code> pour vider le champ description en base. Ce problème survient après une ré-installation.',
    'L\'intervenant affiché est vide'
        => 'Renseignez votre Prénom et Nom dans votre fiche utilisateur Dolibarr (Utilisateurs & Groupes → votre compte → Modifier).',
    'Comment mettre à jour le module ?'
        => 'Via Git : <code>cd htdocs/custom/attestationsap && git pull origin main</code>. Puis désactivez/réactivez le module.',
    'Le widget SAP n\'apparaît pas dans la liste'
        => 'Désactivez puis réactivez le module AttestationSAP. Le widget est enregistré automatiquement à l\'activation.',
    'Quelle différence entre déclaration et agrément ?'
        => 'La déclaration préalable (NOVA) suffit pour la plupart des activités (ménage, informatique, cours). L\'agrément préfectoral est requis pour les activités auprès de personnes vulnérables (âgées, handicapées, enfants <3 ans).',
);
foreach ($faq as $q => $r) {
    print '<details style="margin-bottom:8px;border:1px solid #dee2e6;border-radius:4px">';
    print '<summary style="padding:10px 14px;cursor:pointer;font-weight:bold">'.dol_escape_htmltag($q).'</summary>';
    print '<div style="padding:10px 14px;background:#f8f9fa">'.$r.'</div>';
    print '</details>';
}

print '</div>';

// Lien paramètres
print '<br><div class="center">';
print '<a href="'.dol_buildpath('/custom/attestationsap/setup.php', 1).'" class="butAction">⚙ Ouvrir les Paramètres SAP</a>';
print ' &nbsp; ';
print '<a href="'.dol_buildpath('/custom/attestationsap/index.php?tab=generate', 1).'" class="butAction">📋 Générer les attestations</a>';
print '</div><br>';

llxFooter();
$db->close();
