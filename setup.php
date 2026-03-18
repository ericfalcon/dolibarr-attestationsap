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

function sap_get_categories_tiers($db)
{
    $out = array();
    // type=2 = catégories tiers/sociétés dans Dolibarr
    $sql = "SELECT rowid, label FROM " . MAIN_DB_PREFIX . "categorie WHERE type = 2 ORDER BY label";
    $res = $db->query($sql);
    if ($res) while ($o = $db->fetch_object($res)) $out[] = array('id' => (int)$o->rowid, 'label' => $o->label);
    return $out;
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
    $sap_cat_id      = (int)GETPOST('ATTESTATIONSAP_CATEGORY_ID', 'int');
    $sap_client_cat  = (int)GETPOST('ATTESTATIONSAP_CLIENT_CAT_ID', 'int');
    $sap_services = GETPOST('ATTESTATIONSAP_SERVICES', 'restricthtml');

    // Activités SAP officielles cochées
    $activites_arr = GETPOST('ATTESTATIONSAP_ACTIVITES', 'array');
    if (!is_array($activites_arr)) $activites_arr = array();
    $activites_csv_new = implode(',', array_map('trim', $activites_arr));

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
        $ok = $ok && (dolibarr_set_const($db, 'ATTESTATIONSAP_CATEGORY_ID',    $sap_cat_id,     'entier', 0, '', $e) !== false);
        $ok = $ok && (dolibarr_set_const($db, 'ATTESTATIONSAP_CLIENT_CAT_ID', $sap_client_cat, 'entier', 0, '', $e) !== false);
        $ok = $ok && (dolibarr_set_const($db, 'ATTESTATIONSAP_SERVICES',    $sap_services, 'chaine', 0, '', $e) !== false);
        $ok = $ok && (dolibarr_set_const($db, 'ATTESTATIONSAP_ACTIVITES',  $activites_csv_new, 'chaine', 0, '', $e) !== false);
        // Mettre à jour NATURE_SERVICE avec TOUTES les activités cochées (conformité légale)
        if (!empty($activites_arr)) {
            // Table de correspondance clé → libellé court
            $activites_labels = array(
                'garde_enfants_domicile'      => 'Garde d\'enfants à domicile (<3 ans)',
                'garde_enfants_3ans'          => 'Garde d\'enfants à domicile (3 ans et +)',
                'accompagnement_enfants'      => 'Accompagnement d\'enfants',
                'assistance_personnes_agees'  => 'Assistance personnes âgées',
                'assistance_personnes_hand'   => 'Assistance personnes handicapées',
                'aide_mobilite'               => 'Aide à la mobilité',
                'conduite_vehicule'           => 'Conduite du véhicule',
                'entretien_maison'            => 'Entretien de la maison',
                'petits_travaux_jardinage'    => 'Jardinage (petits travaux)',
                'prestations_jardinage'       => 'Jardinage (grandes surfaces)',
                'prestations_nettoyage'       => 'Nettoyage de vitres',
                'cuisine'                     => 'Préparation de repas',
                'livraison_repas'             => 'Livraison de repas',
                'collecte_livraison_linge'    => 'Livraison linge repassé',
                'assistance_informatique'     => 'Assistance informatique',
                'assistance_administrative'   => 'Assistance administrative',
                'soins_animaux'               => 'Soins et promenades d\'animaux',
                'maintenance_residence'       => 'Maintenance de la résidence',
                'gardiennage'                 => 'Gardiennage de résidence',
                'soutien_scolaire'            => 'Soutien scolaire / cours particuliers',
                'cours_informatique'          => 'Cours informatique',
                'cours_musique'               => 'Cours de musique',
                'cours_autres'                => 'Autres cours à domicile',
                'soins_domicile'              => 'Soins non médicaux à domicile',
                'aide_sport'                  => 'Activités sportives à domicile',
                'assistance_numerique'        => 'Assistance démarches numériques',
                'teleassistance'              => 'Téléassistance',
                'interpretation_langue'       => 'Interprète langue des signes',
            );
            $nature_parts = array();
            foreach ($activites_arr as $act) {
                $act = trim($act);
                if (isset($activites_labels[$act])) $nature_parts[] = $activites_labels[$act];
                elseif (!empty($act)) $nature_parts[] = $act;
            }
            $nature_auto = implode(' - ', $nature_parts);
            // Seulement si l'utilisateur n'a pas modifié manuellement le champ nature
            $nature_post = GETPOST('ATTESTATIONSAP_NATURE_SERVICE', 'alphanohtml');
            // Si le champ nature a été laissé vide ou correspond à l'ancienne valeur auto, on recalcule
            if (empty($nature_post) || $nature_post === getDolGlobalString('ATTESTATIONSAP_NATURE_SERVICE')) {
                $ok = $ok && (dolibarr_set_const($db, 'ATTESTATIONSAP_NATURE_SERVICE', $nature_auto, 'chaine', 0, '', $e) !== false);
            }
        }

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

if ($action === 'upload_signature') {
    if (!empty($_FILES['signature_sap']['tmp_name'])) {
        $upload_dir = (!empty($conf->mycompany->multidir_output[$conf->entity]) ? $conf->mycompany->multidir_output[$conf->entity] : $conf->mycompany->dir_output) . '/logos';
        if (!is_dir($upload_dir)) @mkdir($upload_dir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['signature_sap']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, array('png', 'jpg', 'jpeg', 'gif'))) {
            setEventMessages("Format non supporté (PNG, JPG uniquement).", null, 'errors');
        } else {
            $dest = $upload_dir . '/signature-sap.' . $ext;
            if (dol_move_uploaded_file($_FILES['signature_sap']['tmp_name'], $dest, 1) > 0) {
                dolibarr_set_const($db, 'ATTESTATIONSAP_SIGNATURE', 'mycompany/logos/' . basename($dest), 'chaine', 0, '', $conf->entity);
                setEventMessages('Signature uploadée avec succès.', null, 'mesgs');
            } else {
                setEventMessages("Erreur lors de l'upload.", null, 'errors');
            }
        }
    }
}
if ($action === 'delete_signature') {
    $rel = getDolGlobalString('ATTESTATIONSAP_SIGNATURE');
    if ($rel) {
        $full = DOL_DATA_ROOT . '/' . $rel;
        if (file_exists($full)) @unlink($full);
        dolibarr_del_const($db, 'ATTESTATIONSAP_SIGNATURE', $conf->entity);
        setEventMessages('Signature supprimée.', null, 'mesgs');
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
$activites_csv     = getDolGlobalString('ATTESTATIONSAP_ACTIVITES', '');
$activites_sel     = array();
foreach (explode(',', $activites_csv) as $a) { $a = trim($a); if ($a !== '') $activites_sel[$a] = true; }
$mode_intervention = getDolGlobalString('ATTESTATIONSAP_MODE', 'prestataire');
$sign_name         = getDolGlobalString('ATTESTATIONSAP_SIGN_NAME', '');
$sign_text         = getDolGlobalString('ATTESTATIONSAP_SIGN_TEXT', '');
$current_cat_id    = (int)getDolGlobalString('ATTESTATIONSAP_CATEGORY_ID', 0);
$current_cli_cat   = (int)getDolGlobalString('ATTESTATIONSAP_CLIENT_CAT_ID', 0);
$categories_tiers  = sap_get_categories_tiers($db);
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
    print '<strong style="color:#4caf50;">' . dol_escape_htmltag($intervenantActuel['fullname']) . '</strong>';
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
// SECTION 3 : Activités SAP & mode
// =====================================================================
print '<tr class="liste_titre"><td colspan="3"><strong>3 — Activités SAP & mode d\'intervention</strong> &nbsp; <small class="opacitymedium">Décret D.7231-1 du Code du travail</small></td></tr>';

// Liste officielle des activités SAP (D.7231-1)
// Structure : 'clé' => array('label' => ..., 'agrement' => bool)
// agrement=true = nécessite agrément préfectoral (art. L.7232-1 Code du travail)
// agrement=false = simple déclaration préalable suffit
$activites_sap = array(
    // Famille A : Garde d'enfants
    'A' => array(
        'label' => 'Garde d\'enfants',
        'items' => array(
            'garde_enfants_domicile' => array('label' => 'Garde d\'enfants à domicile (moins de 3 ans)',       'agrement' => true),
            'garde_enfants_3ans'     => array('label' => 'Garde d\'enfants à domicile (3 ans et plus)',         'agrement' => false),
            'accompagnement_enfants' => array('label' => 'Accompagnement d\'enfants dans les déplacements',     'agrement' => true),
        ),
    ),
    // Famille B : Assistance aux personnes âgées/handicapées
    'B' => array(
        'label' => 'Assistance aux personnes',
        'items' => array(
            'assistance_personnes_agees' => array('label' => 'Assistance aux personnes âgées (hors soins)',         'agrement' => true),
            'assistance_personnes_hand'  => array('label' => 'Assistance aux personnes handicapées (hors soins)',    'agrement' => true),
            'aide_mobilite'              => array('label' => 'Aide à la mobilité / transport accompagné',            'agrement' => true),
            'conduite_vehicule'          => array('label' => 'Conduite du véhicule de la personne',                  'agrement' => true),
        ),
    ),
    // Famille C : Entretien de la maison
    'C' => array(
        'label' => 'Entretien & vie quotidienne',
        'items' => array(
            'entretien_maison'         => array('label' => 'Entretien de la maison et travaux ménagers',             'agrement' => false),
            'petits_travaux_jardinage' => array('label' => 'Petits travaux de jardinage',                            'agrement' => false),
            'prestations_jardinage'    => array('label' => 'Prestations de jardinage (grandes surfaces)',             'agrement' => true),
            'prestations_nettoyage'    => array('label' => 'Prestations de nettoyage de vitres',                     'agrement' => false),
            'cuisine'                  => array('label' => 'Préparation de repas / livraison de courses',            'agrement' => false),
            'livraison_repas'          => array('label' => 'Livraison de repas à domicile (personnes dépendantes)',  'agrement' => true),
            'collecte_livraison_linge' => array('label' => 'Collecte et livraison du linge repassé',                 'agrement' => false),
        ),
    ),
    // Famille D : Assistance technique et administrative
    'D' => array(
        'label' => 'Assistance technique & administrative',
        'items' => array(
            'assistance_informatique'   => array('label' => 'Assistance informatique à domicile',                   'agrement' => false),
            'assistance_administrative' => array('label' => 'Assistance administrative à domicile',                  'agrement' => false),
            'soins_animaux'             => array('label' => 'Soins et promenades d\'animaux (hors vétérinaire)',     'agrement' => false),
            'maintenance_residence'     => array('label' => 'Maintenance et entretien de la résidence',              'agrement' => false),
            'gardiennage'               => array('label' => 'Gardiennage et surveillance temporaire de résidence',   'agrement' => false),
        ),
    ),
    // Famille E : Soutien scolaire / cours
    'E' => array(
        'label' => 'Soutien scolaire & cours',
        'items' => array(
            'soutien_scolaire'   => array('label' => 'Soutien scolaire à domicile / cours particuliers',             'agrement' => false),
            'cours_informatique' => array('label' => 'Cours informatique à domicile',                                'agrement' => false),
            'cours_musique'      => array('label' => 'Cours de musique à domicile',                                  'agrement' => false),
            'cours_autres'       => array('label' => 'Autres cours à domicile',                                      'agrement' => false),
        ),
    ),
    // Famille F : Soins & bien-être
    'F' => array(
        'label' => 'Soins & bien-être',
        'items' => array(
            'soins_domicile'         => array('label' => 'Soins à la personne non médicaux à domicile',              'agrement' => true),
            'aide_sport'             => array('label' => 'Activités sportives / de bien-être à domicile',            'agrement' => false),
            'assistance_numerique'   => array('label' => 'Assistance aux démarches numériques',                      'agrement' => false),
            'teleassistance'         => array('label' => 'Téléassistance et visio-assistance',                       'agrement' => true),
            'interpretation_langue'  => array('label' => 'Interprète en langue des signes',                          'agrement' => true),
        ),
    ),
);

print '<tr class="oddeven"><td valign="top" style="width:270px">Activités exercées <span class="fieldrequired">*</span><br><small class="opacitymedium">Cochez toutes vos activités</small></td><td colspan="2">';
print '<div style="display:flex;flex-wrap:wrap;gap:16px">';
foreach ($activites_sap as $famille_key => $famille) {
    print '<div style="min-width:280px;flex:1;background:#1a2535;border:1px solid #2e4060;border-radius:6px;padding:10px">';
    print '<strong style="color:#5a9fd4;display:block;margin-bottom:6px;border-bottom:1px solid #3a5a7a;padding-bottom:4px">'
         .dol_escape_htmltag($famille['label']).'</strong>';
    foreach ($famille['items'] as $key => $item) {
        $label    = $item['label'];
        $needs_ag = $item['agrement'];
        $checked  = !empty($activites_sel[$key]) ? ' checked' : '';
        // Masquer si agrement requis et type=declaration
        $hidden   = ($needs_ag && $hab_type === 'declaration') ? ' style="display:none"' : '';
        $css_ag   = $needs_ag ? ' sap-agrement-only' : '';
        print '<label class="sap-activite'.$css_ag.'"'.$hidden.' style="display:block;margin:3px 0;cursor:pointer;color:#b8d4ee">';
        print '<input type="checkbox" name="ATTESTATIONSAP_ACTIVITES[]" value="'.dol_escape_htmltag($key).'"'.$checked.'> ';
        print dol_escape_htmltag($label);
        if ($needs_ag) print ' <span style="font-size:10px;color:#e67e22;font-weight:bold" title="Nécessite un agrément préfectoral">⚠ Agr.</span>';
        print '</label>';
    }
    print '</div>';
}
print '</div>';
print '</td></tr>';

// Champ nature service (affiché automatiquement mais modifiable)
print '<tr class="oddeven"><td>Nature affichée sur les documents</td>';
print '<td><textarea name="ATTESTATIONSAP_NATURE_SERVICE" rows="3" id="nature_service_field" class="flat centpercent" style="resize:vertical;overflow-y:auto;min-height:60px" placeholder="Assistance informatique à domicile">' . dol_escape_htmltag($nature_service) . '</textarea></td>';
print '<td><small class="opacitymedium">Renseignée automatiquement avec la première activité cochée, ou personnalisable</small></td></tr>';

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

// Alerte si double critère non configuré
if ($current_cat_id === 0 || $current_cli_cat === 0) {
    print '<tr class="oddeven"><td colspan="3">';
    print '<div style="background:#fff3cd;border-left:4px solid #ffc107;padding:10px 14px;border-radius:4px">';
    print '⚠ <strong>Configuration incomplète :</strong> Pour une détection fiable des prestations SAP, ';
    print 'configurez à la fois la <strong>catégorie produit SAP</strong> ET la <strong>catégorie tiers SAP</strong>.';
    print '</div></td></tr>';
}

print '<tr class="oddeven"><td>Catégorie produit SAP</td><td>';
print '<select name="ATTESTATIONSAP_CATEGORY_ID" class="flat minwidth300">';
print '<option value="0"' . ($current_cat_id === 0 ? ' selected' : '') . '>— (aucune, utiliser les mots-clés)</option>';
foreach ($categories_ps as $cat) {
    $sel = ($cat['id'] === $current_cat_id) ? ' selected' : '';
    print '<option value="' . $cat['id'] . '"' . $sel . '>' . dol_escape_htmltag($cat['label']) . '</option>';
}
print '</select></td><td>Méthode prioritaire de détection des lignes SAP</td></tr>';

print '<tr class="oddeven"><td>Catégorie tiers SAP <span class="fieldrequired">*</span></td><td>';
print '<select name="ATTESTATIONSAP_CLIENT_CAT_ID" class="flat minwidth300">';
print '<option value="0"'.($current_cli_cat === 0 ? ' selected' : '').'>— Aucune (tous les clients)</option>';
foreach ($categories_tiers as $cat) {
    $sel = ($cat['id'] === $current_cli_cat) ? ' selected' : '';
    print '<option value="'.$cat['id'].'"'.$sel.'>'.dol_escape_htmltag($cat['label']).'</option>';
}
print '</select>';
print '</td><td>';
if ($current_cli_cat === 0) {
    print '<span style="color:#e67e22">⚠ Non configurée — toutes les attestations seront générées sans filtrage client SAP</span>';
} else {
    print '<span style="color:#1a7a2e">✓ Seuls les clients avec cette catégorie recevront une attestation</span>';
}
print '</td></tr>';

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
// SECTION 11 : Signature / cachet
// =====================================================================
print load_fiche_titre('11 — Signature et cachet (apposée automatiquement sur les attestations)', '', 'title_setup');

$rel_sign = getDolGlobalString('ATTESTATIONSAP_SIGNATURE');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="3"><strong>Image de signature / cachet</strong> &nbsp; <small class="opacitymedium">Sera intégrée automatiquement dans la zone signature des attestations envoyées par email</small></td></tr>';
print '<tr class="oddeven"><td style="width:270px">Signature actuelle</td><td colspan="2">';
if ($rel_sign) {
    $full_sign = DOL_DATA_ROOT . '/' . $rel_sign;
    if (file_exists($full_sign)) {
        $url_sign = DOL_URL_ROOT . '/viewimage.php?modulepart=mycompany&file=' . urlencode('logos/' . basename($full_sign));
        print '<img src="'.dol_escape_htmltag($url_sign).'" style="max-height:80px;max-width:250px;border:1px solid #ccc;padding:6px;background:#fff;border-radius:4px" alt="Signature"><br><br>';
    }
    print '<a href="'.dol_buildpath('/custom/attestationsap/setup.php', 1).'?action=delete_signature&token='.newToken().'" class="butActionDelete">🗑 Supprimer la signature</a>';
} else {
    print '<span class="opacitymedium">Aucune signature configurée — la zone restera vierge sur les attestations.</span>';
}
print '</td></tr>';
print '</table><br>';

print '<form method="POST" enctype="multipart/form-data" action="'.dol_buildpath('/custom/attestationsap/setup.php', 1).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="upload_signature">';
print '<table class="noborder centpercent">';
print '<tr class="oddeven"><td style="width:270px">Uploader une nouvelle signature</td>';
print '<td><input type="file" name="signature_sap" accept="image/png,image/jpeg" required>';
print ' <small class="opacitymedium">PNG transparent recommandé, ~300×100 px</small></td><td></td></tr>';
print '</table><br><div class="center"><input type="submit" class="button" value="Uploader la signature"></div>';
print '</form>';

// =====================================================================
// JAVASCRIPT
// =====================================================================
echo <<<ENDJS
<script>
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

    // Afficher/masquer les activités nécessitant un agrément
    function toggleActivitesAgrement() {
        var type = "declaration";
        document.querySelectorAll("input[name=ATTESTATIONSAP_HABILITATION_TYPE]").forEach(function(r){ if(r.checked) type = r.value; });
        document.querySelectorAll(".sap-agrement-only").forEach(function(el) {
            if (type === "agrement") {
                el.style.display = "block";
            } else {
                el.style.display = "none";
                var cb = el.querySelector("input[type=checkbox]");
                if (cb) cb.checked = false;
            }
        });
    }
    document.querySelectorAll("input[name=ATTESTATIONSAP_HABILITATION_TYPE]").forEach(function(r){ r.addEventListener("change", toggleActivitesAgrement); });
    toggleActivitesAgrement();

    // Auto-remplir le champ "Nature affichee" avec toutes les activites cochees
    var activitesLabels = {
        "garde_enfants_domicile":      "Garde d'enfants a domicile (<3 ans)",
        "garde_enfants_3ans":          "Garde d'enfants a domicile (3 ans et +)",
        "accompagnement_enfants":      "Accompagnement d'enfants",
        "assistance_personnes_agees":  "Assistance personnes agees",
        "assistance_personnes_hand":   "Assistance personnes handicapees",
        "aide_mobilite":               "Aide a la mobilite",
        "conduite_vehicule":           "Conduite du vehicule",
        "entretien_maison":            "Entretien de la maison",
        "petits_travaux_jardinage":    "Jardinage (petits travaux)",
        "prestations_jardinage":       "Jardinage (grandes surfaces)",
        "prestations_nettoyage":       "Nettoyage de vitres",
        "cuisine":                     "Preparation de repas",
        "livraison_repas":             "Livraison de repas",
        "collecte_livraison_linge":    "Livraison linge repasse",
        "assistance_informatique":     "Assistance informatique",
        "assistance_administrative":   "Assistance administrative",
        "soins_animaux":               "Soins et promenades d'animaux",
        "maintenance_residence":       "Maintenance de la residence",
        "gardiennage":                 "Gardiennage de residence",
        "soutien_scolaire":            "Soutien scolaire / cours particuliers",
        "cours_informatique":          "Cours informatique",
        "cours_musique":               "Cours de musique",
        "cours_autres":                "Autres cours a domicile",
        "soins_domicile":              "Soins non medicaux a domicile",
        "aide_sport":                  "Activites sportives a domicile",
        "assistance_numerique":        "Assistance demarches numeriques",
        "teleassistance":              "Teleassistance",
        "interpretation_langue":       "Interprete langue des signes"
    };
    function updateNatureService() {
        var checked = document.querySelectorAll("input[name=\"ATTESTATIONSAP_ACTIVITES[]\"]:checked");
        var parts = [];
        checked.forEach(function(cb) {
            if (activitesLabels[cb.value]) parts.push(activitesLabels[cb.value]);
        });
        var field = document.getElementById("nature_service_field");
        if (field) field.value = parts.join(" - ");
    }
    document.querySelectorAll("input[name=\"ATTESTATIONSAP_ACTIVITES[]\"]").forEach(function(cb) {
        cb.addEventListener("change", updateNatureService);
    });
})();
</script>
ENDJS;

llxFooter();
$db->close();
