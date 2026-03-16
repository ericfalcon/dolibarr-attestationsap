<?php
/**
 * \file        htdocs/custom/attestationsap/setup.php
 * \ingroup     attestationsap
 * \brief       Page de configuration du module AttestationSAP
 * \version     2.1.0
 *
 * Sections :
 *  1. Habilitation SAP (déclaration préalable / agrément)
 *  2. Intervenant(s) — choix du user Dolibarr ou texte libre
 *  3. Nature du service + mode d'intervention
 *  4. Signataire
 *  5. Identification des lignes SAP (catégorie + mots-clés)
 *  6. Modèles de factures pris en compte
 *  7. Modèles PDF par défaut
 *  8. Options d'affichage
 *  9. Template email
 * 10. Logo SAP (upload)
 */

require_once dirname(__FILE__).'/../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once dirname(__FILE__) . '/class/SapIntervenants.class.php';

if (!$user->admin) accessforbidden();
$langs->loadLangs(array('admin', 'other', 'bills', 'propal'));

$action = GETPOST('action', 'alpha');

// =========================================================================
// HELPERS
// =========================================================================

function sap_get_pdf_models($db, $type, $entity)
{
    $out = array();
    $sql = "SELECT nom FROM " . MAIN_DB_PREFIX . "document_model
            WHERE entity = " . (int)$entity . " AND type = '" . $db->escape($type) . "'
            ORDER BY nom";
    $res = $db->query($sql);
    if ($res) {
        while ($o = $db->fetch_object($res)) {
            if (empty($o->nom)) continue;
            $name = preg_replace('/:.+$/', '', $o->nom);
            if (strpos($name, 'template_') === 0) continue;
            $out[] = $name;
        }
        $db->free($res);
    }
    return array_values(array_unique($out));
}

function sap_get_categories_produits($db)
{
    $out = array();
    $sql = "SELECT rowid, label FROM " . MAIN_DB_PREFIX . "categorie WHERE type = 0 ORDER BY label";
    $res = $db->query($sql);
    if ($res) {
        while ($o = $db->fetch_object($res)) $out[] = array('id' => (int)$o->rowid, 'label' => $o->label);
        $db->free($res);
    }
    return $out;
}

// =========================================================================
// ACTIONS
// =========================================================================

if ($action === 'save_settings') {
    $token = GETPOST('token', 'alpha');
    if (empty($token) || $token !== $_SESSION['newtoken']) accessforbidden();

    $e = $conf->entity;

    // Habilitation
    $hab_type  = GETPOST('ATTESTATIONSAP_HABILITATION_TYPE', 'aZ09');
    $decl_num  = GETPOST('ATTESTATIONSAP_DECL_NUM', 'alphanohtml');
    $decl_date = GETPOST('ATTESTATIONSAP_DECL_DATE', 'alphanohtml');
    $agre_num  = GETPOST('ATTESTATIONSAP_AGREMENT_NUM', 'alphanohtml');
    $agre_date = GETPOST('ATTESTATIONSAP_AGREMENT_DATE', 'alphanohtml');
    $numero_agrement = ($hab_type === 'agrement') ? $agre_num : $decl_num;

    // Intervenant
    $interv_mode  = GETPOST('ATTESTATIONSAP_INTERVENANT_MODE', 'aZ09'); // 'user' | 'libre'
    $interv_uid   = (int)GETPOST('ATTESTATIONSAP_INTERVENANT_USER_ID', 'int');
    $interv_libre = GETPOST('ATTESTATIONSAP_INTERVENANT_LIBRE', 'alphanohtml');

    // Nature & mode
    $nature_service    = GETPOST('ATTESTATIONSAP_NATURE_SERVICE', 'alphanohtml');
    $mode_intervention = GETPOST('ATTESTATIONSAP_MODE', 'aZ09');

    // Signataire
    $sign_name = GETPOST('ATTESTATIONSAP_SIGN_NAME', 'alphanohtml');
    $sign_text = GETPOST('ATTESTATIONSAP_SIGN_TEXT', 'alphanohtml');

    // Identification SAP
    $sap_cat_id   = (int)GETPOST('ATTESTATIONSAP_CATEGORY_ID', 'int');
    $sap_services = GETPOST('ATTESTATIONSAP_SERVICES', 'restricthtml');

    // Modèles factures
    $models_arr = GETPOST('ATTESTATIONSAP_FACTURE_MODEL_LIST', 'array');
    if (empty($models_arr)) $models_arr = array('facture_sap_v3');
    $models_arr = array_values(array_filter(array_map('trim', (array)$models_arr), function ($v) { return $v !== ''; }));
    if (empty($models_arr)) $models_arr = array('facture_sap_v3');
    $models_csv = implode(',', array_unique($models_arr));

    // Modèles PDF Dolibarr
    $propal_pdf = GETPOST('PROPALE_ADDON_PDF', 'alphanohtml');
    $fact_pdf   = GETPOST('FACTURE_ADDON_PDF', 'alphanohtml');

    // Options affichage
    $show_credit  = GETPOST('ATTESTATIONSAP_SHOW_CREDIT_IMPOT', 'int') ? 1 : 0;
    $show_tva_exo = GETPOST('ATTESTATIONSAP_MENTION_TVA_EXONEREE', 'int') ? 1 : 0;

    // Email
    $email_subject = GETPOST('ATTESTATIONSAP_EMAIL_SUBJECT', 'alphanohtml');
    $email_body    = GETPOST('ATTESTATIONSAP_EMAIL_BODY', 'restricthtml');

    // Validations
    $error = 0;
    if (!in_array($hab_type, array('declaration', 'agrement'))) { setEventMessages("Type d'habilitation invalide.", null, 'errors'); $error++; }
    if ($hab_type === 'declaration' && empty($decl_num)) { setEventMessages("N° de déclaration SAP obligatoire.", null, 'errors'); $error++; }
    if ($hab_type === 'agrement'    && empty($agre_num)) { setEventMessages("N° d'agrément SAP obligatoire.", null, 'errors'); $error++; }
    if (empty($sign_name)) { setEventMessages("Nom du signataire obligatoire.", null, 'errors'); $error++; }
    if (!in_array($mode_intervention, array('prestataire', 'mandataire'))) $mode_intervention = 'prestataire';
    if (!in_array($interv_mode, array('user', 'libre'))) $interv_mode = 'user';

    if (!$error) {
        $ok = true;

        // Habilitation
        $ok = $ok && (dolibarr_set_const($db, 'ATTESTATIONSAP_HABILITATION_TYPE', $hab_type,        'chaine', 0, '', $e) !== false);
        $ok = $ok && (dolibarr_set_const($db, 'ATTESTATIONSAP_DECL_NUM',           $decl_num,        'chaine', 0, '', $e) !== false);
        $ok = $ok && (dolibarr_set_const($db, 'ATTESTATIONSAP_DECL_DATE',          $decl_date,       'chaine', 0, '', $e) !== false);
        $ok = $ok && (dolibarr_set_const($db, 'ATTESTATIONSAP_AGREMENT_NUM',       $agre_num,        'chaine', 0, '', $e) !== false);
        $ok = $ok && (dolibarr_set_const($db, 'ATTESTATIONSAP_AGREMENT_DATE',      $agre_date,       'chaine', 0, '', $e) !== false);
        $ok = $ok && (dolibarr_set_const($db, 'ATTESTATIONSAP_NUMERO_AGREMENT',    $numero_agrement, 'chaine', 0, '', $e) !== false);

        // Intervenant
        $ok = $ok && (dolibarr_set_const($db, 'ATTESTATIONSAP_INTERVENANT_MODE',    $interv_mode,  'chaine', 0, '', $e) !== false);
        $ok = $ok && (dolibarr_set_const($db, 'ATTESTATIONSAP_INTERVENANT_USER_ID', $interv_uid,   'entier', 0, '', $e) !== false);
        $ok = $ok && (dolibarr_set_const($db, 'ATTESTATIONSAP_INTERVENANT_LIBRE',   $interv_libre, 'chaine', 0, '', $e) !== false);

        // Nature & mode
        $ok = $ok && (dolibarr_set_const($db, 'ATTESTATIONSAP_NATURE_SERVICE', $nature_service,    'chaine', 0, '', $e) !== false);
        $ok = $ok && (dolibarr_set_const($db, 'ATTESTATIONSAP_MODE',           $mode_intervention, 'chaine', 0, '', $e) !== false);

        // Signataire
        $ok = $ok && (dolibarr_set_const($db, 'ATTESTATIONSAP_SIGN_NAME', $sign_name, 'chaine', 0, '', $e) !== false);
        $ok = $ok && (dolibarr_set_const($db, 'ATTESTATIONSAP_SIGN_TEXT', $sign_text, 'chaine', 0, '', $e) !== false);

        // Identification SAP
        $ok = $ok && (dolibarr_set_const($db, 'ATTESTATIONSAP_CATEGORY_ID', $sap_cat_id,   'entier', 0, '', $e) !== false);
        $ok = $ok && (dolibarr_set_const($db, 'ATTESTATIONSAP_SERVICES',    $sap_services, 'chaine', 0, '', $e) !== false);

        // Modèles
        $ok = $ok && (dolibarr_set_const($db, 'ATTESTATIONSAP_FACTURE_MODEL_LIST', $models_csv, 'chaine', 0, '', $e) !== false);
        dolibarr_del_const($db, 'ATTESTATIONSAP_FACTURE_MODEL_NAME', $e);

        if (!empty($propal_pdf)) $ok = $ok && (dolibarr_set_const($db, 'PROPALE_ADDON_PDF', $propal_pdf, 'chaine', 0, '', $e) !== false);
        if (!empty($fact_pdf))   $ok = $ok && (dolibarr_set_const($db, 'FACTURE_ADDON_PDF', $fact_pdf,   'chaine', 0, '', $e) !== false);

        // Options
        $ok = $ok && (dolibarr_set_const($db, 'ATTESTATIONSAP_SHOW_CREDIT_IMPOT',    $show_credit,  'entier', 0, '', $e) !== false);
        $ok = $ok && (dolibarr_set_const($db, 'ATTESTATIONSAP_MENTION_TVA_EXONEREE', $show_tva_exo, 'entier', 0, '', $e) !== false);

        // Email
        if (!empty($email_subject)) $ok = $ok && (dolibarr_set_const($db, 'ATTESTATIONSAP_EMAIL_SUBJECT', $email_subject, 'chaine', 0, '', $e) !== false);
        if (!empty($email_body))    $ok = $ok && (dolibarr_set_const($db, 'ATTESTATIONSAP_EMAIL_BODY',    $email_body,    'chaine', 0, '', $e) !== false);

        setEventMessages($ok ? 'Paramètres enregistrés avec succès.' : "Erreur lors de l'enregistrement.", null, $ok ? 'mesgs' : 'errors');
    }
}

if ($action === 'upload_logo') {
    $token = GETPOST('token', 'alpha');
    if (empty($token) || $token !== $_SESSION['newtoken']) accessforbidden();
    if (!empty($_FILES['logo_sap']['tmp_name'])) {
        $upload_dir = (!empty($conf->mycompany->multidir_output[$conf->entity]) ? $conf->mycompany->multidir_output[$conf->entity] : $conf->mycompany->dir_output) . '/logos';
        if (!dol_is_dir($upload_dir)) dol_mkdir($upload_dir);
        $ext = strtolower(pathinfo($_FILES['logo_sap']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, array('png', 'jpg', 'jpeg', 'gif'))) {
            setEventMessages('Extension non supportée (PNG, JPG, GIF).', null, 'errors');
        } else {
            $dest = $upload_dir . '/logo-sap.' . $ext;
            if (dol_move_uploaded_file($_FILES['logo_sap']['tmp_name'], $dest, 1) > 0) {
                dolChmod($dest);
                dolibarr_set_const($db, 'ATTESTATIONSAP_LOGO', 'mycompany/logos/' . basename($dest), 'chaine', 0, '', $conf->entity);
                setEventMessages('Logo uploadé avec succès.', null, 'mesgs');
            } else {
                setEventMessages("Erreur lors de l'upload.", null, 'errors');
            }
        }
    }
}

if ($action === 'delete_logo') {
    $token = GETPOST('token', 'alpha');
    if (empty($token) || $token !== $_SESSION['newtoken']) accessforbidden();
    $rel = getDolGlobalString('ATTESTATIONSAP_LOGO');
    if ($rel) {
        $path = DOL_DATA_ROOT . '/' . $rel;
        if (file_exists($path)) dol_delete_file($path);
        dolibarr_del_const($db, 'ATTESTATIONSAP_LOGO', $conf->entity);
        setEventMessages('Logo SAP supprimé.', null, 'mesgs');
    }
}

// =========================================================================
// VUE
// =========================================================================

llxHeader('', 'Configuration Attestations SAP');
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1">' . $langs->trans("BackToModuleList") . '</a>';
print load_fiche_titre('Configuration du module AttestationSAP v2.1', $linkback, 'title_setup');
print '<br>';

// Données
$propal_models  = sap_get_pdf_models($db, 'propal', $conf->entity);
$invoice_models = sap_get_pdf_models($db, 'invoice', $conf->entity);
$categories_ps  = sap_get_categories_produits($db);

// Chargement des users pour le select intervenant
$sapInter        = new SapIntervenants($db);
$usersForSelect  = $sapInter->getAllUsersForSelect($conf->entity);

// Valeurs actuelles
$hab_type          = getDolGlobalString('ATTESTATIONSAP_HABILITATION_TYPE', 'declaration');
$decl_num          = getDolGlobalString('ATTESTATIONSAP_DECL_NUM', '');
$decl_date         = getDolGlobalString('ATTESTATIONSAP_DECL_DATE', '');
$agre_num          = getDolGlobalString('ATTESTATIONSAP_AGREMENT_NUM', '');
$agre_date         = getDolGlobalString('ATTESTATIONSAP_AGREMENT_DATE', '');
$interv_mode       = getDolGlobalString('ATTESTATIONSAP_INTERVENANT_MODE', 'user');
$interv_uid        = (int)getDolGlobalString('ATTESTATIONSAP_INTERVENANT_USER_ID', 0);
$interv_libre      = getDolGlobalString('ATTESTATIONSAP_INTERVENANT_LIBRE', '');
$nature_service    = getDolGlobalString('ATTESTATIONSAP_NATURE_SERVICE', 'Assistance informatique à domicile');
$mode_intervention = getDolGlobalString('ATTESTATIONSAP_MODE', 'prestataire');
$sign_name         = getDolGlobalString('ATTESTATIONSAP_SIGN_NAME', '');
$sign_text         = getDolGlobalString('ATTESTATIONSAP_SIGN_TEXT', '');
$current_cat_id    = (int)getDolGlobalString('ATTESTATIONSAP_CATEGORY_ID', 0);
$sap_services      = getDolGlobalString('ATTESTATIONSAP_SERVICES', '');
$models_csv        = getDolGlobalString('ATTESTATIONSAP_FACTURE_MODEL_LIST', 'facture_sap_v3');
$models_sel        = array();
foreach (preg_split('/[,;]+/', $models_csv) as $m) { $m = trim($m); if ($m !== '') $models_sel[$m] = true; }
$cur_propal_pdf    = getDolGlobalString('PROPALE_ADDON_PDF', '');
$cur_fact_pdf      = getDolGlobalString('FACTURE_ADDON_PDF', '');
$show_credit_impot = getDolGlobalInt('ATTESTATIONSAP_SHOW_CREDIT_IMPOT', 1);
$show_tva_exo      = getDolGlobalInt('ATTESTATIONSAP_MENTION_TVA_EXONEREE', 1);
$email_subject     = getDolGlobalString('ATTESTATIONSAP_EMAIL_SUBJECT', 'Attestation fiscale SAP {YEAR}');
$email_body        = getDolGlobalString('ATTESTATIONSAP_EMAIL_BODY', "Bonjour,\n\nVeuillez trouver ci-joint votre attestation fiscale pour l'année {YEAR}.\n\nCordialement,\n{COMPANY}");

// ------ Aperçu de l'intervenant actuel ------
$intervenantActuel = $sapInter->getIntervenantDefaut($conf->entity);

print '<form method="POST" action="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '" enctype="multipart/form-data">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="save_settings">';

print '<table class="noborder centpercent">';

// =====================================================================
// SECTION 1 : Habilitation SAP
// =====================================================================
print '<tr class="liste_titre"><td colspan="3"><strong>1 — Habilitation SAP</strong> &nbsp; <small class="opacitymedium">Obligatoire pour la conformité légale</small></td></tr>';

print '<tr class="oddeven"><td style="width:270px">Type d\'habilitation <span class="fieldrequired">*</span></td><td>';
print '<label><input type="radio" name="ATTESTATIONSAP_HABILITATION_TYPE" value="declaration"' . ($hab_type === 'declaration' ? ' checked' : '') . '> Déclaration préalable (NOVA)</label> &nbsp; ';
print '<label><input type="radio" name="ATTESTATIONSAP_HABILITATION_TYPE" value="agrement"' . ($hab_type === 'agrement' ? ' checked' : '') . '> Agrément préfectoral</label>';
print '</td><td>Selon votre statut auprès des services de l\'État</td></tr>';

print '<tr class="oddeven row-declaration"><td>N° de déclaration SAP <span class="fieldrequired">*</span></td>';
print '<td><input type="text" name="ATTESTATIONSAP_DECL_NUM" value="' . dol_escape_htmltag($decl_num) . '" class="minwidth250" placeholder="SAP00000000000"></td>';
print '<td>Affiché sur toutes les factures et attestations</td></tr>';

print '<tr class="oddeven row-declaration"><td>Date de déclaration</td>';
print '<td><input type="date" name="ATTESTATIONSAP_DECL_DATE" value="' . dol_escape_htmltag($decl_date) . '"></td><td></td></tr>';

print '<tr class="oddeven row-agrement"><td>N° d\'agrément SAP <span class="fieldrequired">*</span></td>';
print '<td><input type="text" name="ATTESTATIONSAP_AGREMENT_NUM" value="' . dol_escape_htmltag($agre_num) . '" class="minwidth250"></td>';
print '<td>Affiché sur toutes les factures et attestations</td></tr>';

print '<tr class="oddeven row-agrement"><td>Date d\'agrément</td>';
print '<td><input type="date" name="ATTESTATIONSAP_AGREMENT_DATE" value="' . dol_escape_htmltag($agre_date) . '"></td><td></td></tr>';

// =====================================================================
// SECTION 2 : Intervenant(s)
// =====================================================================
print '<tr class="liste_titre"><td colspan="3"><strong>2 — Intervenant(s)</strong> &nbsp; <small class="opacitymedium">Nom affiché sur les factures et attestations (art. D.7233-1 Code du travail)</small></td></tr>';

// Info contextuelle
print '<tr class="oddeven"><td colspan="3">';
print '<div style="background:#f0f8ff;border-left:4px solid #4a90d9;padding:10px 14px;border-radius:4px;font-size:13px;">';
print '<strong>Pour vous (auto-entrepreneur) :</strong> sélectionnez simplement votre propre compte utilisateur Dolibarr ci-dessous. ';
print 'Pour une société avec salariés, chaque salarié doit avoir un compte utilisateur Dolibarr actif.<br>';
print '<strong>Intervenant actuellement affiché :</strong> ';
if ($intervenantActuel) {
    print '<strong style="color:#1a7a2e;">' . dol_escape_htmltag($intervenantActuel['fullname']) . '</strong>';
    print ' <small class="opacitymedium">(source : ' . dol_escape_htmltag($intervenantActuel['source']) . ')</small>';
} else {
    print '<span style="color:#c0392b;">⚠ Aucun intervenant configuré</span>';
}
print '</div>';
print '</td></tr>';

print '<tr class="oddeven"><td>Mode de sélection</td><td>';
print '<label><input type="radio" name="ATTESTATIONSAP_INTERVENANT_MODE" value="user" id="interv_mode_user"' . ($interv_mode === 'user' ? ' checked' : '') . '>';
print ' Utilisateur Dolibarr</label> &nbsp; ';
print '<label><input type="radio" name="ATTESTATIONSAP_INTERVENANT_MODE" value="libre" id="interv_mode_libre"' . ($interv_mode === 'libre' ? ' checked' : '') . '>';
print ' Texte libre (sous-traitant ponctuel)</label>';
print '</td><td>Choisissez "Utilisateur Dolibarr" dans la plupart des cas</td></tr>';

// Select user Dolibarr
print '<tr class="oddeven row-interv-user"><td>Utilisateur Dolibarr par défaut</td><td>';
print '<select name="ATTESTATIONSAP_INTERVENANT_USER_ID" class="flat minwidth300">';
print '<option value="0"' . ($interv_uid === 0 ? ' selected' : '') . '>— Premier utilisateur actif (automatique)</option>';
foreach ($usersForSelect as $u) {
    $sel = ($u['id'] === $interv_uid) ? ' selected' : '';
    print '<option value="' . $u['id'] . '"' . $sel . '>';
    print dol_escape_htmltag($u['fullname']);
    if (!empty($u['email'])) print ' (' . dol_escape_htmltag($u['email']) . ')';
    print '</option>';
}
print '</select>';
print '</td><td>Sera affiché comme intervenant sur les documents SAP</td></tr>';

// Texte libre
print '<tr class="oddeven row-interv-libre"><td>Nom de l\'intervenant (texte libre)</td>';
print '<td><input type="text" name="ATTESTATIONSAP_INTERVENANT_LIBRE" value="' . dol_escape_htmltag($interv_libre) . '" class="minwidth300" placeholder="Prénom NOM"></td>';
print '<td>Utilisé si "Texte libre" est sélectionné</td></tr>';

// =====================================================================
// SECTION 3 : Nature & mode
// =====================================================================
print '<tr class="liste_titre"><td colspan="3"><strong>3 — Nature des services & mode d\'intervention</strong></td></tr>';

print '<tr class="oddeven"><td>Nature du service</td>';
print '<td><input type="text" name="ATTESTATIONSAP_NATURE_SERVICE" value="' . dol_escape_htmltag($nature_service) . '" class="minwidth350" placeholder="Assistance informatique à domicile"></td>';
print '<td>Affiché dans le cadre SAP des factures et sur l\'attestation</td></tr>';

print '<tr class="oddeven"><td>Mode d\'intervention</td><td>';
print '<label><input type="radio" name="ATTESTATIONSAP_MODE" value="prestataire"' . ($mode_intervention === 'prestataire' ? ' checked' : '') . '> Mode prestataire</label> &nbsp; ';
print '<label><input type="radio" name="ATTESTATIONSAP_MODE" value="mandataire"' . ($mode_intervention === 'mandataire' ? ' checked' : '') . '> Mode mandataire</label>';
print '</td><td>Mention obligatoire sur tous les documents</td></tr>';

// =====================================================================
// SECTION 4 : Signataire
// =====================================================================
print '<tr class="liste_titre"><td colspan="3"><strong>4 — Signataire des attestations</strong></td></tr>';

print '<tr class="oddeven"><td>Nom du signataire <span class="fieldrequired">*</span></td>';
print '<td><input type="text" name="ATTESTATIONSAP_SIGN_NAME" value="' . dol_escape_htmltag($sign_name) . '" class="minwidth300" required></td>';
print '<td>Affiché en bas de l\'attestation fiscale</td></tr>';

print '<tr class="oddeven"><td>Fonction</td>';
print '<td><input type="text" name="ATTESTATIONSAP_SIGN_TEXT" value="' . dol_escape_htmltag($sign_text) . '" class="minwidth300" placeholder="Dirigeant / Gérant"></td>';
print '<td>Optionnel</td></tr>';

// =====================================================================
// SECTION 5 : Identification des lignes SAP
// =====================================================================
print '<tr class="liste_titre"><td colspan="3"><strong>5 — Identification des prestations SAP</strong></td></tr>';

print '<tr class="oddeven"><td>Catégorie produit SAP</td><td>';
print '<select name="ATTESTATIONSAP_CATEGORY_ID" class="flat minwidth300">';
print '<option value="0"' . ($current_cat_id === 0 ? ' selected' : '') . '>— (aucune, utiliser les mots-clés)</option>';
foreach ($categories_ps as $cat) {
    $sel = ($cat['id'] === $current_cat_id) ? ' selected' : '';
    print '<option value="' . $cat['id'] . '"' . $sel . '>' . dol_escape_htmltag($cat['label']) . '</option>';
}
print '</select></td><td>Méthode prioritaire de détection des lignes SAP</td></tr>';

print '<tr class="oddeven"><td>Mots-clés SAP (fallback)</td>';
print '<td><textarea name="ATTESTATIONSAP_SERVICES" rows="4" class="flat minwidth300" placeholder="assistance informatique&#10;dépannage domicile">' . dol_escape_htmltag($sap_services) . '</textarea></td>';
print '<td>1 mot-clé par ligne</td></tr>';

// =====================================================================
// SECTION 6 : Modèles factures
// =====================================================================
print '<tr class="liste_titre"><td colspan="3"><strong>6 — Modèles de factures pris en compte pour les attestations</strong></td></tr>';

print '<tr class="oddeven"><td>Modèles de facture SAP</td><td>';
print '<select name="ATTESTATIONSAP_FACTURE_MODEL_LIST[]" class="flat minwidth300" multiple size="' . max(4, count($invoice_models)) . '">';
if (empty($invoice_models)) {
    print '<option value="facture_sap_v3" selected>facture_sap_v3</option>';
} else {
    foreach ($invoice_models as $m) {
        print '<option value="' . dol_escape_htmltag($m) . '"' . (!empty($models_sel[$m]) ? ' selected' : '') . '>' . dol_escape_htmltag($m) . '</option>';
    }
}
print '</select><br><small class="opacitymedium">Ctrl/Cmd + clic pour sélection multiple.</small>';
print '</td><td>Seules ces factures déclenchent une attestation</td></tr>';

// =====================================================================
// SECTION 7 : Modèles PDF par défaut
// =====================================================================
print '<tr class="liste_titre"><td colspan="3"><strong>7 — Modèles PDF par défaut (Dolibarr)</strong></td></tr>';

print '<tr class="oddeven"><td>Modèle PDF — Devis</td><td>';
print '<select name="PROPALE_ADDON_PDF" class="flat minwidth250"><option value="">(inchangé)</option>';
foreach ($propal_models as $m) print '<option value="' . dol_escape_htmltag($m) . '"' . ($m === $cur_propal_pdf ? ' selected' : '') . '>' . dol_escape_htmltag($m) . '</option>';
print '</select></td><td>Recommandé : <code>devis_sap_v2</code></td></tr>';

print '<tr class="oddeven"><td>Modèle PDF — Facture</td><td>';
print '<select name="FACTURE_ADDON_PDF" class="flat minwidth250"><option value="">(inchangé)</option>';
foreach ($invoice_models as $m) print '<option value="' . dol_escape_htmltag($m) . '"' . ($m === $cur_fact_pdf ? ' selected' : '') . '>' . dol_escape_htmltag($m) . '</option>';
print '</select></td><td>Recommandé : <code>facture_sap_v3</code></td></tr>';

// =====================================================================
// SECTION 8 : Options affichage
// =====================================================================
print '<tr class="liste_titre"><td colspan="3"><strong>8 — Options d\'affichage dans les PDF</strong></td></tr>';

print '<tr class="oddeven"><td>Afficher le crédit d\'impôt 50 %</td>';
print '<td><label><input type="checkbox" name="ATTESTATIONSAP_SHOW_CREDIT_IMPOT" value="1"' . ($show_credit_impot ? ' checked' : '') . '> Oui</label></td>';
print '<td>Ligne "Crédit d\'impôt : X €" dans les totaux des factures</td></tr>';

print '<tr class="oddeven"><td>Mention TVA non applicable</td>';
print '<td><label><input type="checkbox" name="ATTESTATIONSAP_MENTION_TVA_EXONEREE" value="1"' . ($show_tva_exo ? ' checked' : '') . '> Oui</label></td>';
print '<td>Art. 293 B du CGI dans le cadre SAP</td></tr>';

// =====================================================================
// SECTION 9 : Email
// =====================================================================
print '<tr class="liste_titre"><td colspan="3"><strong>9 — Template d\'email d\'envoi des attestations</strong><br><small class="opacitymedium">Variables : <code>{YEAR}</code> <code>{CLIENT}</code> <code>{COMPANY}</code></small></td></tr>';

print '<tr class="oddeven"><td>Objet</td><td colspan="2"><input type="text" name="ATTESTATIONSAP_EMAIL_SUBJECT" value="' . dol_escape_htmltag($email_subject) . '" class="flat centpercent"></td></tr>';
print '<tr class="oddeven"><td>Corps</td><td colspan="2"><textarea name="ATTESTATIONSAP_EMAIL_BODY" rows="5" class="flat centpercent">' . dol_escape_htmltag($email_body) . '</textarea></td></tr>';

print '</table>';
print '<br><div class="center"><input type="submit" class="button button-save" value="' . $langs->trans('Save') . '"></div>';
print '</form>';

// =====================================================================
// SECTION 10 : Logo SAP
// =====================================================================
print '<br><br>';
print load_fiche_titre('10 — Logo SAP (cadre "Mentions obligatoires" sur les factures)', '', 'title_setup');

$rel_logo = getDolGlobalString('ATTESTATIONSAP_LOGO');
print '<div style="border:1px solid #ccc;padding:16px;background:#f9f9f9;border-radius:6px;margin:8px 0;">';
if ($rel_logo) {
    $full = DOL_DATA_ROOT . '/' . $rel_logo;
    if (file_exists($full)) {
        $url = DOL_URL_ROOT . '/viewimage.php?modulepart=mycompany&file=' . urlencode('logos/' . basename($full));
        print '<div style="display:flex;align-items:center;gap:16px;margin-bottom:10px;">';
        print '<img src="' . $url . '" style="max-height:70px;max-width:180px;border:1px solid #ddd;padding:5px;background:#fff;">';
        print '<span class="opacitymedium">' . dol_escape_htmltag($rel_logo) . '</span>';
        print '</div>';
        print '<a class="butActionDelete" href="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '?action=delete_logo&token=' . newToken() . '">Supprimer ce logo</a>';
    } else {
        print '<p class="warning">Logo configuré mais introuvable : <code>' . dol_escape_htmltag($full) . '</code></p>';
    }
} else {
    print '<p class="opacitymedium">Aucun logo SAP configuré. Un logo générique sera utilisé si présent dans <code>/custom/attestationsap/img/</code>.</p>';
}
print '</div>';

print '<form method="POST" action="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '" enctype="multipart/form-data">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="upload_logo">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="3">Uploader un nouveau logo SAP</td></tr>';
print '<tr class="oddeven"><td style="width:270px">Fichier image</td>';
print '<td><input type="file" name="logo_sap" accept="image/png,image/jpeg,image/gif" required>';
print ' <small class="opacitymedium">PNG ou JPG, ~200×80 px recommandé</small></td><td></td></tr>';
print '</table><br><div class="center"><input type="submit" class="button" value="Uploader le logo"></div>';
print '</form>';

// =====================================================================
// JAVASCRIPT
// =====================================================================
print '<script>
(function () {
    // Toggle déclaration / agrément
    function toggleHab() {
        var t = "declaration";
        document.querySelectorAll("input[name=ATTESTATIONSAP_HABILITATION_TYPE]").forEach(function(r){ if(r.checked) t = r.value; });
        document.querySelectorAll(".row-declaration").forEach(function(el){ el.style.display = t==="declaration" ? "table-row" : "none"; });
        document.querySelectorAll(".row-agrement").forEach(function(el){ el.style.display = t==="agrement" ? "table-row" : "none"; });
    }
    document.querySelectorAll("input[name=ATTESTATIONSAP_HABILITATION_TYPE]").forEach(function(r){ r.addEventListener("change", toggleHab); });
    toggleHab();

    // Toggle intervenant user / libre
    function toggleInterv() {
        var mode = "user";
        document.querySelectorAll("input[name=ATTESTATIONSAP_INTERVENANT_MODE]").forEach(function(r){ if(r.checked) mode = r.value; });
        document.querySelectorAll(".row-interv-user").forEach(function(el){ el.style.display = mode==="user" ? "table-row" : "none"; });
        document.querySelectorAll(".row-interv-libre").forEach(function(el){ el.style.display = mode==="libre" ? "table-row" : "none"; });
    }
    document.querySelectorAll("input[name=ATTESTATIONSAP_INTERVENANT_MODE]").forEach(function(r){ r.addEventListener("change", toggleInterv); });
    toggleInterv();
})();
</script>';

llxFooter();
$db->close();
