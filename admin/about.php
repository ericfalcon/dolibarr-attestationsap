<?php
/**
 * \file        htdocs/custom/attestationsap/about.php
 * \ingroup     attestationsap
 * \brief       Page À propos / Aide — AttestationSAP v2.1
 */

// Chargement de main.inc.php (méthode robuste standard Dolibarr)
$res = 0;
$dir = dirname(__FILE__);
$dirs = array($dir.'/../../../main.inc.php', $dir.'/../../../../main.inc.php', $dir.'/../../../../../main.inc.php');
foreach ($dirs as $f) {
    if (!$res && @file_exists($f)) { $res = @include $f; }
}
if (!$res) die('Include of main fails');
if (empty($conf->attestationsap->enabled)) accessforbidden();
if (empty($user->rights->attestationsap->read)) accessforbidden();
$langs->loadLangs(array('main', 'admin'));

llxHeader('', 'AttestationSAP — À propos');
print load_fiche_titre('AttestationSAP v2.1 — À propos & Aide', '', 'help');
print '<div style="max-width:900px;">';

// Bloc version
print '<div style="background:#f0f8ff;border:1px solid #b0cfe8;border-radius:8px;padding:20px;margin-bottom:20px;">';
print '<h2 style="margin-top:0;color:#003d7a;">AttestationSAP <span style="font-size:13px;font-weight:normal;color:#888;">v2.1.0</span></h2>';
print '<p>Plugin Dolibarr pour la gestion complète des <strong>Services à la Personne (SAP)</strong> :</p>';
print '<ul>
  <li>Devis SAP avec cadre mentions obligatoires</li>
  <li>Factures SAP avec crédit d\'impôt 50 % et nom de l\'intervenant</li>
  <li>Attestations fiscales annuelles conformes (détail par facture, intervenant, D.7233-1)</li>
  <li>Envoi par email avec suivi</li>
  <li>Compatible auto-entrepreneurs, EURL, SASU, sociétés avec salariés</li>
</ul>';
print '</div>';

// Cadre légal
print '<h3 style="color:#003d7a;">Conformité légale</h3>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>Texte</th><th>Objet</th></tr>';
$legal = array(
    array('Art. 199 sexdecies CGI',        'Crédit d\'impôt 50% pour services à domicile'),
    array('Art. D.7233-1 Code du travail', 'Mentions obligatoires dont nom de l\'intervenant'),
    array('Art. L.7232-1-1 Code du travail','Délivrance de l\'attestation fiscale annuelle'),
    array('Art. 293 B CGI',               'Exonération de TVA (franchise en base)'),
    array('Décret n°2005-1698',           'Agrément et déclaration préalable NOVA'),
);
$f = false;
foreach ($legal as $r) {
    print '<tr class="'.($f?'even':'oddeven').'"><td><code>'.dol_escape_htmltag($r[0]).'</code></td><td>'.dol_escape_htmltag($r[1]).'</td></tr>';
    $f = !$f;
}
print '</table><br>';

// Guide rapide
print '<h3 style="color:#003d7a;">Démarrage rapide</h3>';
print '<ol style="line-height:2.2;">';
print '<li>Activez le module : <strong>Configuration → Modules → AttestationSAP</strong></li>';
print '<li>Renseignez votre <strong>N° de déclaration SAP (NOVA)</strong> dans <strong>SAP → Paramètres SAP</strong></li>';
print '<li>Sélectionnez votre <strong>compte utilisateur Dolibarr</strong> comme intervenant par défaut</li>';
print '<li>Renseignez la <strong>nature du service</strong> et le <strong>mode d\'intervention</strong></li>';
print '<li>Créez vos <strong>devis</strong> et <strong>factures</strong> via le menu "SAP"</li>';
print '<li>En fin d\'année : <strong>SAP → Générer les attestations</strong> → sélectionner → Envoyer</li>';
print '</ol>';

// Paramètres actuels
print '<h3 style="color:#003d7a;">Paramètres actuels</h3>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>Paramètre</th><th>Valeur</th></tr>';
$params = array(
    'ATTESTATIONSAP_NUMERO_AGREMENT'      => 'N° déclaration/agrément SAP',
    'ATTESTATIONSAP_NATURE_SERVICE'       => 'Nature du service',
    'ATTESTATIONSAP_MODE'                 => 'Mode d\'intervention',
    'ATTESTATIONSAP_INTERVENANT_MODE'     => 'Mode intervenant',
    'ATTESTATIONSAP_FACTURE_MODEL_LIST'   => 'Modèles de factures SAP',
    'ATTESTATIONSAP_SHOW_CREDIT_IMPOT'    => 'Crédit d\'impôt affiché',
    'ATTESTATIONSAP_MENTION_TVA_EXONEREE' => 'Mention TVA non applicable',
);
$f = false;
foreach ($params as $k => $label) {
    $val = getDolGlobalString($k);
    $display = !empty($val) ? dol_escape_htmltag($val) : '<span style="color:#c0392b;">Non défini</span>';
    print '<tr class="'.($f?'even':'oddeven').'"><td>'.dol_escape_htmltag($label).' <small class="opacitymedium">('.$k.')</small></td><td>'.$display.'</td></tr>';
    $f = !$f;
}
print '</table><br>';

// Changelog
print '<h3 style="color:#003d7a;">Historique</h3>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>Version</th><th>Date</th><th>Nouveautés</th></tr>';
$log = array(
    array('2.1.0', '2026-03', 'Intervenants via users Dolibarr (SapIntervenants), colonne par facture dans l\'attestation, conformité D.7233-1 complète'),
    array('2.0.0', '2026-02', 'Refonte : attestation avec détail par facture, table SQL de suivi, droits granulaires, template email, Dolibarr 22'),
    array('1.0.0', '2025',    'Version initiale : modèles devis/facture SAP, génération attestation, envoi email'),
);
foreach ($log as $entry) {
    print '<tr class="oddeven"><td><strong>v'.dol_escape_htmltag($entry[0]).'</strong></td><td>'.dol_escape_htmltag($entry[1]).'</td><td>'.dol_escape_htmltag($entry[2]).'</td></tr>';
}
print '</table>';
print '</div>';

llxFooter();
$db->close();
