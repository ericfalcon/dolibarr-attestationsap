
<?php
// htdocs/custom/attestationsap/index.php
// Complet : Générer (lot + suivi), Attestations existantes (filtres), Tableau de bord (devis/factures/attestations),
// et journalisation Agenda (apparait dans “10 derniers événements” du tiers).

require_once dirname(__FILE__).'/../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';

// Agenda (pour journaliser l'envoi)
if (!empty($conf->agenda->enabled)) {
    require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
}

if (empty($conf->attestationsap->enabled)) accessforbidden();
if (empty($user) || empty($user->rights->attestationsap->read)) accessforbidden();

$langs->loadLangs(array('bills','propal','companies','main'));

$action = GETPOST('action','alpha');
$tab    = GETPOST('tab','alpha') ?: 'generate';
$debug  = (int) GETPOST('debug','int');

// Dossier data unique pour écrire & lister
$attDir = !empty($conf->attestationsap->dir_output) ? $conf->attestationsap->dir_output : DOL_DATA_ROOT.'/attestationsap';
if (!dol_is_dir($attDir)) dol_mkdir($attDir);

// document.php mapping
if (!isset($conf->modules_parts) || !is_array($conf->modules_parts)) $conf->modules_parts = array();
if (!isset($conf->modules_parts['document']) || !is_array($conf->modules_parts['document'])) $conf->modules_parts['document'] = array();
$conf->modules_parts['document']['attestationsap'] = $attDir;

// Module PDF pour attestation
$attPdf      = DOL_DOCUMENT_ROOT.'/custom/attestationsap/core/modules/attestationsap/pdf_attestation_sap.modules.php';
$attPdfClass = 'pdf_attestation_sap';
if (file_exists($attPdf)) require_once $attPdf;

/* ===================== Métadonnées envoi (.sent.json) ===================== */
function att_sent_meta_path($attDir, $bn) { return rtrim($attDir, '/').'/'.$bn.'.sent.json'; }

function att_is_sent($attDir, $bn) {
    $meta = att_sent_meta_path($attDir, $bn);
    if (is_file($meta) && is_readable($meta)) {
        $raw = @file_get_contents($meta);
        $info = @json_decode($raw, true);
        if (is_array($info)) {
            $info['sent'] = true;
            if (empty($info['date_ts'])) $info['date_ts'] = @filemtime($meta);
            if (empty($info['date_txt']) && !empty($info['date_ts'])) $info['date_txt'] = dol_print_date($info['date_ts'], 'dayhour');
            return $info;
        } else {
            return array('sent'=>true,'date_ts'=>@filemtime($meta),'date_txt'=>dol_print_date(@filemtime($meta),'dayhour'));
        }
    }
    return null;
}

function att_mark_sent($attDir, $bn, $socid, $email, $year, $user) {
    $meta = att_sent_meta_path($attDir, $bn);
    $now  = dol_now();
    $payload = array(
        'sent'=>true,'date_ts'=>$now,'date_txt'=>dol_print_date($now,'dayhour'),
        'socid'=>(int)$socid,'email'=>(string)$email,'year'=>(int)$year,
        'user_id'=>(int)$user->id,'user_login'=>(string)$user->login
    );
    @file_put_contents($meta, json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
    dolChmod($meta);
}

function att_delete_with_meta($attDir, $bn) {
    $full = rtrim($attDir,'/').'/'.$bn;
    $ok1 = true; $ok2 = true;
    if (is_file($full)) $ok1 = @unlink($full);
    $meta = att_sent_meta_path($attDir, $bn);
    if (is_file($meta)) $ok2 = @unlink($meta);
    return ($ok1 && $ok2);
}

/* ===================== Année fiscale ===================== */
$yearParam = GETPOST('year','int');
if (!empty($yearParam)) {
    $year = (int) $yearParam;
} else {
    if (class_exists($attPdfClass) && method_exists($attPdfClass, 'resolveFiscalYear')) {
        $year = $attPdfClass::resolveFiscalYear(null);
    } else {
        $month = (int) date('n');
        $year  = (int) date('Y') - ($month <= 1 ? 1 : 0);
    }
}

$url_propal  = DOL_URL_ROOT.'/comm/propal/card.php?action=create&sap_mode=1&model=devis_sap_v2&modelpdf=devis_sap_v2&doctemplate=';
$url_facture = DOL_URL_ROOT.'/compta/facture/card.php?action=create&sap_mode=1&model=facture_sap_v3&modelpdf=facture_sap_v3&doctemplate=';

/* ===================== Sélection des factures (modèles STRICTS) ===================== */
function sap_parse_models($rawCsv) {
    $out = array();
    $parts = preg_split('/[,;]+/', (string)$rawCsv);
    for ($i=0; $i<count($parts); $i++) {
        $m = trim($parts[$i]);
        if ($m !== '') $out[] = $m;
    }
    if (empty($out)) $out = array('facture_sap_v3');
    $out = array_unique($out);
    return array_values($out);
}

function sap_build_model_where($db, $models) {
    $parts = array();
    for ($i=0; $i<count($models); $i++) {
        $m = $db->escape($models[$i]);
        $parts[] = "(f.model_pdf = '".$m."' OR f.model_pdf LIKE '".$m."%')";
    }
    return $parts ? '('.implode(' OR ', $parts).')' : '';
}

/** Factures candidates année $year (option socid) — STRICT sur modèles cochés */
function sap_find_factures_for_year($db, $year, $socid = 0) {
    global $conf;

    $out = array();
    if ($year < 1900) return $out;

    $tsStart = dol_get_first_day($year, 1, true);
    $tsEnd   = dol_get_last_day($year, 12, true);
    $ds = "'".$db->idate($tsStart)."'";
    $de = "'".$db->idate($tsEnd)."'";

    $rawList = getDolGlobalString('ATTESTATIONSAP_FACTURE_MODEL_LIST','');
    if ($rawList === '') $rawList = getDolGlobalString('ATTESTATIONSAP_FACTURE_MODEL_NAME','facture_sap_v3'); // compat
    $models = sap_parse_models($rawList);
    $wModel = sap_build_model_where($db, $models);
    if ($wModel === '') return $out;

    $wSoc = ($socid > 0) ? " AND f.fk_soc = ".((int)$socid) : "";

    $sql = "SELECT f.rowid, f.ref, f.fk_soc, f.datef, f.date_valid, f.total_ttc, f.model_pdf
            FROM ".MAIN_DB_PREFIX."facture f
            WHERE f.entity = ".((int)$conf->entity)."
              AND f.type = 0
              AND f.fk_statut = 2
              AND COALESCE(f.datef, f.date_valid) BETWEEN $ds AND $de
              AND $wModel
              $wSoc
            ORDER BY COALESCE(f.datef, f.date_valid) ASC";

    $res = $db->query($sql);
    if (!$res) {
        if (!empty($_GET['debug'])) print '<pre class="opacitymedium">SQL ERROR: '.$db->lasterror()."\n".$sql.'</pre>';
        return $out;
    }
    while ($o = $db->fetch_object($res)) $out[] = $o;
    $db->free($res);

    if (!empty($_GET['debug'])) {
        print '<pre class="opacitymedium">DEBUG entity='.$conf->entity.' year='.$year.' models='.implode(',',$models).' date_start='.$ds.' date_end='.$de.' found='.count($out)."</pre>";
    }
    return $out;
}

/* ============== Repérage lignes SAP & regroupement par client ============== */
function sap_is_line_sap($db, $ln, $sap_cat_id, $needles) {
    // Option D : si catégorie produit SAP configurée → critère OBLIGATOIRE
    if ($sap_cat_id > 0) {
        if ((int)$ln->fk_product > 0) {
            $q = "SELECT 1 FROM ".MAIN_DB_PREFIX."categorie_product WHERE fk_product=".(int)$ln->fk_product." AND fk_categorie=".(int)$sap_cat_id." LIMIT 1";
            $r = $db->query($q);
            return ($r && $db->fetch_object($r)) ? true : false;
        }
        // Produit sans fk_product → fallback mots-clés
    }
    // Fallback mots-clés
    if (!empty($needles)) {
        $txt = dol_strtolower(dol_string_unaccent(($ln->label?:'').' '.($ln->descs?:'')));
        for ($i=0; $i<count($needles); $i++) {
            $n = $needles[$i];
            if ($n !== '' && strpos($txt, $n) !== false) return true;
        }
    }
    // Fallback générique
    $txt2 = dol_strtolower(dol_string_unaccent(($ln->label?:'').' '.($ln->descs?:'')));
    if (strpos($txt2, 'service a la personne') !== false || strpos($txt2, ' sap ') !== false) return true;
    return false;
}

function sap_is_client_sap($db, $socid, $client_cat_id) {
    // Si catégorie tiers SAP configurée → vérifier que le client en fait partie
    if ($client_cat_id <= 0) return true; // pas de filtre
    $q = "SELECT 1 FROM ".MAIN_DB_PREFIX."categorie_societe WHERE fk_soc=".(int)$socid." AND fk_categorie=".(int)$client_cat_id." LIMIT 1";
    $r = $db->query($q);
    return ($r && $db->fetch_object($r)) ? true : false;
}

function sap_group_clients_from_invoices($db, $invoices, $sap_cat_id, $services_fallback, $client_cat_id = 0) {
    $needles = array();
    $lines = preg_split('/\r?\n/', (string)$services_fallback);
    for ($i=0; $i<count($lines); $i++) {
        $s = trim(dol_string_unaccent($lines[$i]));
        if ($s !== '') $needles[] = dol_strtolower($s);
    }

    $byClient = array();
    $names = array();

    if (!empty($invoices)) {
        $socids = array();
        for ($i=0; $i<count($invoices); $i++) $socids[(int)$invoices[$i]->fk_soc] = true;
        if (!empty($socids)) {
            $ids = implode(',', array_keys($socids));
            $res = $db->query("SELECT rowid, nom FROM ".MAIN_DB_PREFIX."societe WHERE rowid IN (".$ids.")");
            if ($res) while ($o = $db->fetch_object($res)) $names[(int)$o->rowid] = $o->nom;
        }
    }

    for ($i=0; $i<count($invoices); $i++) {
        $f = $invoices[$i];
        $socid = (int)$f->fk_soc;
        if (!isset($byClient[$socid])) {
            $byClient[$socid] = array(
                'client'    => isset($names[$socid]) ? $names[$socid] : ('Client #'.$socid),
                'socid'     => $socid,
                'hours'     => 0.0,
                'total_ttc' => 0.0,
                'invoices'  => array()
            );
        }
        $byClient[$socid]['invoices'][] = $f;

        $sql = "SELECT fd.fk_product, fd.qty, fd.total_ttc, fd.label, fd.description AS descs
                FROM ".MAIN_DB_PREFIX."facturedet fd
                WHERE fd.fk_facture = ".((int)$f->rowid);
        $res = $db->query($sql);
        if (!$res) continue;
        while ($ln = $db->fetch_object($res)) {
            if (sap_is_line_sap($db, $ln, $sap_cat_id, $needles)) {
                $byClient[$socid]['total_ttc'] += (float)$ln->total_ttc;
                $byClient[$socid]['hours']     += (float)$ln->qty;
            }
        }
    }

    foreach ($byClient as $k => $c) {
        $byClient[$k]['total_ttc'] = round($c['total_ttc'], 2);
        $byClient[$k]['hours']     = round($c['hours'], 2);
    }
    return $byClient;
}

/* ===================== UI : Header + actions ===================== */
llxHeader('', 'Attestations fiscales SAP');
print '<style>
.actions-bar{ display:flex; flex-wrap:wrap; align-items:center; gap:10px; justify-content:flex-start; margin:12px 0 14px 0; }
.actions-bar .cta, .actions-bar .cta-secondary, .button, .butAction, .butActionDelete{
  font-size:14px; font-weight:600; line-height:1.15; padding:10px 16px; border-radius:8px;
  text-decoration:none; display:inline-flex; align-items:center; gap:8px; box-shadow:0 1px 2px rgba(0,0,0,.06);
}
.info{ padding:10px 12px; border-radius:6px; margin:8px 0 18px 0; background:#f9f9ff; }
.badge-sent{background:#e6ffed;color:#136c2e;border:1px solid #a3d9a5;padding:2px 6px;border-radius:12px;font-size:12px}
.badge-not{background:#fff3cd;color:#856404;border:1px solid #ffeeba;padding:2px 6px;border-radius:12px;font-size:12px}
.checkbox-col{text-align:center;width:36px}
.bulk-toolbar{display:flex;gap:8px;align-items:center;justify-content:flex-start;margin:8px 0}
.filterbar{display:flex;gap:8px;align-items:center;justify-content:space-between;margin:8px 0}
</style>';

print '<div class="actions-bar">';
print '  <a class="cta butAction" href="'.htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES).'?tab=dashboard">TABLEAU DE BORD</a>';
print '  <a class="cta butAction" href="'.htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES).'?tab=generate&year='.(int)$year.'">GÉNÉRER LES ATTESTATIONS</a>';
print '  <a class="cta-secondary button" href="'.htmlspecialchars($url_propal, ENT_QUOTES).'">NOUVEAU DEVIS SAP</a>';
print '  <a class="cta-secondary button" href="'.htmlspecialchars($url_facture, ENT_QUOTES).'">NOUVELLE FACTURE SAP</a>';
print '</div>';

print '<div class="info"><strong>Rappel :</strong> seules les factures émises avec le(s) modèle(s) configuré(s) (ex. <code>facture_sap_v3</code>) sont prises en compte. Ne coche pas <code>crabe</code> si tu veux exclure les entreprises.</div>';

/* ===================== ACTIONS : email / suppression ===================== */

// Journaliser un envoi dans Agenda
function sap_log_send_event($db, $socid, $bn, $year, $user) {
    global $conf;
    if (empty($conf->agenda->enabled)) return; // Agenda désactivé
    $socid = (int)$socid; if ($socid <= 0) return;

    $ac = new ActionComm($db);
    $ac->type_code    = 'AC_EMAIL';
    $ac->label        = 'Attestation fiscale SAP '.$year.' envoyée';
    $ac->note_private = 'Fichier : '.$bn."
".'Envoyé depuis le module AttestationSAP.';
    $ac->datep        = dol_now();
    $ac->datef        = $ac->datep;
    $ac->durationp    = 0;
    $ac->fk_soc       = $socid;
    $ac->socpeopleassigned = array();
    $ac->authorid     = $user->id;
    $ac->userownerid  = $user->id;
    $ac->percentage   = 100;
    // Lier à la société pour apparaître dans les événements du tiers
    $ac->fk_element   = $socid;
    $ac->elementtype  = 'societe';

    $res = $ac->create($user);
    // pas de setEventMessages ici pour éviter le bruit, c'est une aide silencieuse.
}

// Envoi unitaire
if ($action === 'sendmail') {
    $socid = GETPOST('socid','int');
    $fileb = GETPOST('file','alpha');
    if ($socid > 0 && !empty($fileb) && $year > 0) {
        $file = $attDir.'/'.$fileb;
        $soc = new Societe($db);

        if (!file_exists($file)) {
            setEventMessages('Le fichier PDF n\'existe pas ('.$fileb.').', null, 'errors');
        } elseif ($soc->fetch($socid) <= 0) {
            setEventMessages('Client introuvable (ID='.$socid.').', null, 'errors');
        } elseif (empty($soc->email)) {
            setEventMessages('Le client n\'a pas d\'adresse email configurée.', null, 'warnings');
        } else {
            $subject = 'Attestation fiscale SAP '.$year;
            $from = getDolGlobalString('MAIN_INFO_SOCIETE_MAIL');
            if (empty($from)) $from = 'noreply@'.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost');
            $message = "Bonjour,\n\nVeuillez trouver ci-joint votre attestation fiscale pour l'année ".$year.".\n\n"
                     . "Cette attestation est à conserver avec votre déclaration de revenus.\n\n"
                     . "Cordialement,\n".getDolGlobalString('MAIN_INFO_SOCIETE_NOM');

            $mail = new CMailFile($subject, $soc->email, $from, $message, array($file));
            if ($mail->sendfile()) {
                att_mark_sent($attDir, $fileb, $socid, $soc->email, $year, $user);
                sap_log_send_event($db, $socid, $fileb, $year, $user);
                setEventMessages('Email envoyé avec succès à '.$soc->email, null, 'mesgs');
            } else {
                setEventMessages('Erreur lors de l\'envoi : '.$mail->error, null, 'errors');
            }
        }
    } else {
        setEventMessages('Paramètres invalides pour l\'envoi.', null, 'errors');
    }
}

// Envoi / suppression en lot
if ($action === 'sendmail_bulk') {
    $token = GETPOST('token','alpha');
    if (empty($token) || $token !== $_SESSION['newtoken']) accessforbidden();

    $items = GETPOST('bulk_items', 'array');
    if (empty($items) && !empty($_POST['bulk_items']) && is_array($_POST['bulk_items'])) {
        $items = $_POST['bulk_items'];
    }

    $doDelete = false;
    if (function_exists('GETPOSTISSET')) {
        if (GETPOSTISSET('do_delete_bulk')) $doDelete = true;
    } else {
        $doDelete = (GETPOST('do_delete_bulk','alpha') !== '' || isset($_POST['do_delete_bulk']));
    }

    if (empty($items)) {
        setEventMessages('Aucun élément sélectionné.', null, 'warnings');
    } else if ($doDelete) {
        if (empty($user->admin)) accessforbidden();

        $deleted = 0; $skipped = 0;
        for ($i=0; $i<count($items); $i++) {
            $it = $items[$i];
            $parts = explode('|', $it, 2);
            $fileb = (count($parts) === 2) ? $parts[1] : $parts[0];

            $ok = preg_match('/^attestation_sap_(\d{4})\-([A-Z0-9\-]+)\-ATT(\d+)\.pdf$/i', $fileb)
               || preg_match('/^attestation_sap_(\d+)_\d{4}\.pdf$/i', $fileb);
            if (!$ok) { $skipped++; continue; }

            if (att_delete_with_meta($attDir, $fileb)) $deleted++; else $skipped++;
        }
        $msg = 'Suppression en lot : '.$deleted.' fichier(s) supprimé(s), '.$skipped.' ignoré(s).';
        if ($skipped > 0) setEventMessages($msg, null, 'warnings'); else setEventMessages($msg, null, 'mesgs');
    } else {
        $from = getDolGlobalString('MAIN_INFO_SOCIETE_MAIL');
        if (empty($from)) $from = 'noreply@'.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost');
        $subject = 'Attestation fiscale SAP '.$year;

        $ok = 0; $ko = 0; $skip = 0;

        for ($i=0; $i<count($items); $i++) {
            $it = $items[$i];
            $parts = explode('|', $it, 2);
            if (count($parts) !== 2) { $skip++; continue; }
            $socid = (int) $parts[0];
            $fileb = $parts[1];
            if ($socid <= 0 || empty($fileb)) { $skip++; continue; }

            $file = $attDir.'/'.$fileb;
            if (!file_exists($file)) { $ko++; continue; }

            $soc = new Societe($db);
            if ($soc->fetch($socid) <= 0 || empty($soc->email)) { $skip++; continue; }

            $message = "Bonjour,\n\nVeuillez trouver ci-joint votre attestation fiscale pour l'année ".$year.".\n\n"
                     . "Cette attestation est à conserver avec votre déclaration de revenus.\n\n"
                     . "Cordialement,\n".getDolGlobalString('MAIN_INFO_SOCIETE_NOM');

            $mail = new CMailFile($subject, $soc->email, $from, $message, array($file));
            if ($mail->sendfile()) { 
                att_mark_sent($attDir, $fileb, $socid, $soc->email, $year, $user);
                sap_log_send_event($db, $socid, $fileb, $year, $user);
                $ok++; 
            } else $ko++;
        }

        $summary = 'Envoi en lot terminé : '.$ok.' succès, '.$ko.' échecs, '.$skip.' ignorés.';
        if ($ko > 0) setEventMessages($summary, null, 'warnings');
        else setEventMessages($summary, null, 'mesgs');
    }
}

// Suppression unitaire
if ($action === 'delete') {
    if (empty($user->admin)) accessforbidden();
    $token = GETPOST('token','alpha');
    if (empty($token) || $token !== $_SESSION['newtoken']) accessforbidden();

    $fileb = GETPOST('file', 'alpha');
    $ok = preg_match('/^attestation_sap_(\d{4})\-([A-Z0-9\-]+)\-ATT(\d+)\.pdf$/i', $fileb)
       || preg_match('/^attestation_sap_(\d+)_\d{4}\.pdf$/i', $fileb);
    if (!$ok) {
        setEventMessages('Nom de fichier non autorisé.', null, 'errors');
    } else {
        if (att_delete_with_meta($attDir, $fileb)) setEventMessages('Attestation supprimée : '.$fileb, null, 'mesgs');
        else setEventMessages('Échec de la suppression (droits ?).', null, 'errors');
    }
}

/* ===================== Onglet : Générer ===================== */
if ($tab === 'generate') {
    global $db;

    print load_fiche_titre('GÉNÉRATION DES ATTESTATIONS FISCALES', '', 'title_generic');

    print '<form method="GET" action="'.htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES).'" class="nocellnopadd">';
    print '<input type="hidden" name="tab" value="generate">';
    print '<table class="noborder centpercent">';
    print '  <tr class="liste_titre"><th colspan="4">Paramètres</th></tr>';
    print '  <tr class="oddeven">';
    print '    <td style="width:180px;">Année fiscale :</td>';
    print '    <td style="width:200px;"><input type="number" name="year" value="'.(int)$year.'" min="2000" max="2100" class="flat" style="width:110px;"></td>';
    print '    <td><input type="submit" class="button" value="Actualiser"></td>';
    print '    <td style="text-align:right;"><a class="butAction" href="'.htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES).'?tab=generate&year='.(int)$year.'&action=generate">Générer toutes les attestations</a></td>';
    print '  </tr>';
    print '</table>';
    print '</form><br>';

    if ($action === 'generate' && $year) {

        print load_fiche_titre('Résultats de la génération ('.$year.')', '', 'title_generic');

        $invoices = sap_find_factures_for_year($db, $year, 0);

        if ($debug) {
            print '<pre class="opacitymedium">';
            print "DEBUG: Found ".count($invoices)." candidate invoice(s) for year ".$year."\n";
            for ($i=0; $i<min(10,count($invoices)); $i++) {
                $t = $invoices[$i];
                $d = $db->jdate($t->datef ?: $t->date_valid);
                print " - ".$t->ref." | model_pdf=".$t->model_pdf." | socid=".$t->fk_soc." | date=".($d?dol_print_date($d,'day'):'-')."\n";
            }
            print "</pre>";
        }

        if (empty($invoices)) {
            print '<div class="opacitymedium">Aucune facture SAP trouvée pour l\'année '.$year.' avec le(s) modèle(s) configuré(s).</div>';
        } else {
            $sap_cat_id       = (int) getDolGlobalString('ATTESTATIONSAP_CATEGORY_ID', 0);
            $client_cat_id    = (int) getDolGlobalString('ATTESTATIONSAP_CLIENT_CAT_ID', 0);
            $services_fallback= getDolGlobalString('ATTESTATIONSAP_SERVICES', '');
            $byClient         = sap_group_clients_from_invoices($db, $invoices, $sap_cat_id, $services_fallback, $client_cat_id);

            if (empty($byClient)) {
                print '<div class="opacitymedium">Des factures ont été trouvées, mais aucune ligne n\'est reconnue SAP (catégorie/mots‑clés). Vérifiez vos paramètres.</div>';
            } else {
                print '<form id="formGen" method="POST" action="'.htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES).'?tab=generate&year='.(int)$year.'">';
                print '<input type="hidden" name="action" value="sendmail_bulk">';
                print '<input type="hidden" name="token" value="'.newToken().'">';

                print '<div class="bulk-toolbar">';
                print '  <button type="button" class="button" onclick="toggleAllByClass(\'bulkgen\', true)">Sélectionner tout</button>';
                print '  <button type="button" class="button" onclick="toggleAllByClass(\'bulkgen\', false)">Tout désélectionner</button>';
                print '  <button type="submit" name="do_send_bulk" value="1" class="butAction">Envoyer la sélection</button>';
                if (!empty($user->admin)) {
                    print '  <button type="submit" name="do_delete_bulk" value="1" class="butActionDelete" onclick="return confirm(\'Supprimer tous les fichiers sélectionnés ?\')">Supprimer la sélection</button>';
                }
                print '</div>';

                print '<table class="noborder centpercent">';
                print '<tr class="liste_titre"><th class="checkbox-col"><input type="checkbox" onclick="checkAllInClass(this, \'bulkgen\')"></th><th>Client</th><th>Heures</th><th>Montant TTC</th><th>Statut PDF</th><th>Statut envoi</th><th>Actions</th></tr>';

                foreach ($byClient as $socid => $sum) {
                    $soc = new Societe($db);
                    $soc->fetch($socid);

                    $generatedPath = pdf_attestation_sap::write_file($soc, $sum['total_ttc'], $sum['hours'], $year, $sum['invoices']);

                    // Fallback motif si path non retourné
                    if (!is_string($generatedPath) || empty($generatedPath)) {
                        $clientName = strtoupper(trim(preg_replace('/[^A-Z0-9\-]+/', '-', dol_sanitizeFileName(dol_string_unaccent($soc->name))), '-'));
                        $tryNew = glob($attDir.'/attestation_sap_'.$year.'-'.$clientName.'-ATT*.pdf');
                        if ($tryNew) {
                            usort($tryNew, function($a,$b){ $da=@filemtime($a); $dbb=@filemtime($b); return ($dbb<$da)?-1:(($dbb>$da)?1:0); });
                            $generatedPath = $tryNew[0];
                        }
                    }

                    $checkbox = '<span class="opacitymedium" title="Email manquant">—</span>';
                    $download = 'N/A';
                    $statusPdf = '✖ Erreur de génération';
                    $statusSend = '<span class="badge-not">Non envoyée</span>';
                    $actions = 'N/A';

                    if ($generatedPath && file_exists($generatedPath)) {
                        $filesize = filesize($generatedPath);
                        $bn = basename($generatedPath);
                        $dlUrl = DOL_URL_ROOT.'/document.php?modulepart=attestationsap&file='.urlencode($bn);

                        $statusPdf = '✔ Généré ('.number_format($filesize / 1024, 1, ',', ' ').' Ko)';
                        $download  = '<a class="button" href="'.$dlUrl.'" target="_blank">Télécharger</a>';

                        $sentInfo = att_is_sent($attDir, $bn);
                        if ($sentInfo) {
                            $who = !empty($sentInfo['email']) ? $sentInfo['email'] : '';
                            $when = !empty($sentInfo['date_txt']) ? $sentInfo['date_txt'] : '';
                            $statusSend = '<span class="badge-sent">Envoyée'.($when?' le '.$when:'').($who?' à '.$who:'').'</span>';
                        }

                        if (!empty($soc->email)) {
                            $checkbox = '<input type="checkbox" class="bulkgen" name="bulk_items[]" value="'.$socid.'|'.htmlspecialchars($bn, ENT_QUOTES).'">';
                        }

                        $actions  = '<a class="butAction" href="'.htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES).'?tab=generate&year='.$year.'&action=sendmail&socid='.$socid.'&file='.urlencode($bn).'&token='.newToken().'">Envoyer</a> ';
                        if (!empty($user->admin)) {
                            $actions .= '<a class="butActionDelete" href="'.htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES).'?tab=generate&year='.$year.'&action=delete&file='.urlencode($bn).'&token='.newToken().'" onclick="return confirm(\'Supprimer cette attestation ?\')">Supprimer</a>';
                        }
                    }

                    print '<tr class="oddeven">';
                    print '  <td class="checkbox-col">'.$checkbox.'</td>';
                    print '  <td>'.dol_escape_htmltag($sum['client']).'</td>';
                    print '  <td>'.number_format((float)$sum['hours'], 2, ',', ' ').' h</td>';
                    print '  <td>'.price((float)$sum['total_ttc']).' €</td>';
                    print '  <td>'.$statusPdf.'</td>';
                    print '  <td>'.$statusSend.'</td>';
                    print '  <td>'.$download.' '.$actions.'</td>';
                    print '</tr>';
                }

                print '</table><br>';
                print '</form><br>';
            }
        }
    }

    // === ATTESTATIONS EXISTANTES (ANNÉE XXXX) — avec filtres & lot ===
    print load_fiche_titre('ATTESTATIONS EXISTANTES (ANNÉE '.(int)$year.')', '', 'title_generic');

    $filter_exist_sent = GETPOST('filter_exist_sent','alpha'); // '', 'sent', 'unsent'
    print '<form method="GET" action="'.htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES).'" class="nocellnopadd">';
    print '<input type="hidden" name="tab" value="generate">';
    print '<input type="hidden" name="year" value="'.(int)$year.'">';
    print '<div class="filterbar">';
    print '  <div>';
    print '    <label for="filter_exist_sent"><strong>Filtrer :</strong></label> ';
    print '    <select id="filter_exist_sent" name="filter_exist_sent" class="flat">';
    $opts = array(''=>'Toutes','sent'=>'Déjà envoyées','unsent'=>'Non envoyées');
    foreach ($opts as $k=>$lab) {
        $sel = ($filter_exist_sent===$k)?' selected':'';
        print '      <option value="'.dol_escape_htmltag($k).'"'.$sel.'>'.dol_escape_htmltag($lab).'</option>';
    }
    print '    </select> ';
    print '    <button type="submit" class="button">Appliquer</button>';
    print '  </div>';
    print '  <div class="opacitymedium">Astuce : filtrez pour cibler les relances non envoyées.</div>';
    print '</div>';
    print '</form>';

    $rows = array();

    // Nouveau motif
    $patternNew = rtrim($attDir, '/').'/attestation_sap_'.((int)$year).'-*-ATT*.pdf';
    $filesNew = glob($patternNew); if (!$filesNew) $filesNew = array();
    for ($i=0; $i<count($filesNew); $i++) {
        $full = $filesNew[$i]; if (!is_file($full)) continue;
        $bn = basename($full);
        if (preg_match('/^attestation_sap_(\d{4})\-([A-Z0-9\-]+)\-ATT(\d+)\.pdf$/i', $bn, $m)) {
            $pretty = str_replace('-', ' ', $m[2]);
            $socid = 0; $email = '';
            $sql = "SELECT rowid, email FROM ".MAIN_DB_PREFIX."societe WHERE UPPER(nom)='".$db->escape(strtoupper($pretty))."' LIMIT 1";
            $res = $db->query($sql);
            if ($res && $o = $db->fetch_object($res)) { $socid = (int)$o->rowid; $email = (string)$o->email; }

            $rows[] = array(
                'basename' => $bn,
                'fullpath' => $full,
                'client'   => $pretty,
                'size'     => @filesize($full),
                'date'     => @filemtime($full),
                'socid'    => $socid,
                'email'    => $email
            );
        }
    }

    // Ancien motif
    $patternOld = rtrim($attDir, '/').'/attestation_sap_*_'.((int)$year).'.pdf';
    $filesOld = glob($patternOld); if (!$filesOld) $filesOld = array();
    for ($i=0; $i<count($filesOld); $i++) {
        $full = $filesOld[$i]; if (!is_file($full)) continue;
        $bn = basename($full);
        if (preg_match('/^attestation_sap_(\d+)_'.((int)$year).'\.pdf$/i', $bn, $m)) {
            $socidF = (int)$m[1];
            $soc = new Societe($db);
            $clientLabel = 'Client #'.$socidF; $email = '';
            if ($soc->fetch($socidF) > 0) { $clientLabel = $soc->name; $email = $soc->email; }

            $rows[] = array(
                'basename' => $bn,
                'fullpath' => $full,
                'client'   => $clientLabel,
                'size'     => @filesize($full),
                'date'     => @filemtime($full),
                'socid'    => $socidF,
                'email'    => $email
            );
        }
    }

    if (!empty($filter_exist_sent)) {
        $tmp = array();
        for ($i=0; $i<count($rows); $i++) {
            $r = $rows[$i];
            $sent = att_is_sent($attDir, $r['basename']) ? true : false;
            if ($filter_exist_sent === 'sent' && $sent) $tmp[] = $r;
            if ($filter_exist_sent === 'unsent' && !$sent) $tmp[] = $r;
            if ($filter_exist_sent === '') $tmp[] = $r;
        }
        $rows = $tmp;
    }

    if (empty($rows)) {
        print '<div class="opacitymedium">Aucune attestation trouvée pour '.(int)$year.'.</div>';
    } else {
        usort($rows, function($a,$b){ $da=isset($a['date'])?$a['date']:0; $dbb=isset($b['date'])?$b['date']:0; return ($dbb<$da)?-1:(($dbb>$da)?1:0); });

        print '<form id="formExist" method="POST" action="'.htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES).'?tab=generate&year='.(int)$year.'">';
        print '<input type="hidden" name="action" value="sendmail_bulk">';
        print '<input type="hidden" name="token" value="'.newToken().'">';

        print '<div class="bulk-toolbar">';
        print '  <button type="button" class="button" onclick="toggleAllByClass(\'bulkexist\', true)">Sélectionner tout</button>';
        print '  <button type="button" class="button" onclick="toggleAllByClass(\'bulkexist\', false)">Tout désélectionner</button>';
        print '  <button type="submit" name="do_send_bulk" value="1" class="butAction">Envoyer la sélection</button>';
        if (!empty($user->admin)) {
            print '  <button type="submit" name="do_delete_bulk" value="1" class="butActionDelete" onclick="return confirm(\'Supprimer tous les fichiers sélectionnés ?\')">Supprimer la sélection</button>';
        }
        print '</div>';

        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre"><th class="checkbox-col"><input type="checkbox" onclick="checkAllInClass(this, \'bulkexist\')"></th><th>Client</th><th>Fichier</th><th>Taille (Ko)</th><th>Créé le</th><th>Statut envoi</th><th>Actions</th></tr>';

        for ($i=0; $i<count($rows); $i++) {
            $r = $rows[$i];
            $bn = $r['basename'];
            $dlUrl = DOL_URL_ROOT.'/document.php?modulepart=attestationsap&file='.urlencode($bn);
            $sizeKo = ($r['size'] ? number_format($r['size'] / 1024, 1, ',', ' ').' Ko' : '-');

            if (!empty($r['socid']) && !empty($r['email'])) {
                $checkbox = '<input type="checkbox" class="bulkexist" name="bulk_items[]" value="'.$r['socid'].'|'.htmlspecialchars($bn, ENT_QUOTES).'">';
                $sendlink = '<a class="butAction" href="'.htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES).'?tab=generate&year='.$year.'&action=sendmail&socid='.$r['socid'].'&file='.urlencode($bn).'&token='.newToken().'">Envoyer</a>';
            } else {
                $checkbox = '<span class="opacitymedium">—</span>';
                $sendlink = '<span class="opacitymedium">Impossible d\'envoyer</span>';
            }

            $sentInfo = att_is_sent($attDir, $bn);
            $statusSend = '<span class="badge-not">Non envoyée</span>';
            if ($sentInfo) {
                $who = !empty($sentInfo['email']) ? $sentInfo['email'] : '';
                $when = !empty($sentInfo['date_txt']) ? $sentInfo['date_txt'] : '';
                $statusSend = '<span class="badge-sent">Envoyée'.($when?' le '.$when:'').($who?' à '.$who:'').'</span>';
            }

            $deletelink = '';
            if (!empty($user->admin)) {
                $deletelink = ' <a class="butActionDelete" href="'.htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES).'?tab=generate&year='.$year.'&action=delete&file='.urlencode($bn).'&token='.newToken().'" onclick="return confirm(\'Supprimer cette attestation ?\')">Supprimer</a>';
            }

            print '<tr class="oddeven">';
            print '  <td class="checkbox-col">'.$checkbox.'</td>';
            print '  <td>'.dol_escape_htmltag($r['client']).'</td>';
            print '  <td>'.dol_escape_htmltag($bn).'</td>';
            print '  <td>'.$sizeKo.'</td>';
            print '  <td>'.($r['date'] ? dol_print_date($r['date'],'dayhour') : '-').'</td>';
            print '  <td>'.$statusSend.'</td>';
            print '  <td><a class="button" href="'.$dlUrl.'" target="_blank">Télécharger</a> '.$sendlink.$deletelink.'</td>';
            print '</tr>';
        }

        print '</table><br>';
        print '</form><br>';
    }

    if ($debug) {
        $rawList = getDolGlobalString('ATTESTATIONSAP_FACTURE_MODEL_LIST','');
        if ($rawList === '') $rawList = getDolGlobalString('ATTESTATIONSAP_FACTURE_MODEL_NAME','(legacy) facture_sap_v3');
        print '<pre class="opacitymedium">';
        print "DEBUG:\n";
        print "attDir = ".$attDir." (exists=". (file_exists($attDir)?'yes':'no') .")\n";
        print "attPdf = ".$attPdf." (exists=". (file_exists($attPdf)?'yes':'no') .")\n";
        print "year = ".$year."\n";
        print "models(list) = ".$rawList."\n";
        print "</pre>";
    }
}

/* ===================== Onglet : Tableau de bord ===================== */
if ($tab === 'dashboard') {
    global $db, $conf;

    // Devis SAP récents
    print load_fiche_titre('DEVIS SAP RÉCENTS', '', 'title_generic');
    $sql = "SELECT p.rowid, p.ref, p.total_ttc, p.datec, s.nom as client
            FROM ".MAIN_DB_PREFIX."propal as p
            LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON p.fk_soc = s.rowid
            WHERE p.model_pdf IN ('devis_sap','devis_sap_v2')
            ORDER BY p.datec DESC
            LIMIT 5";
    $resql = $db->query($sql);
    if ($resql && $db->num_rows($resql) > 0) {
        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre"><th>Référence</th><th>Client</th><th>Date</th><th>Montant TTC</th></tr>';
        while ($obj = $db->fetch_object($resql)) {
            $datec_ts = $db->jdate($obj->datec);
            print '<tr class="oddeven">';
            print '  <td><a href="'.DOL_URL_ROOT.'/comm/propal/card.php?id='.$obj->rowid.'">'.dol_escape_htmltag($obj->ref).'</a></td>';
            print '  <td>'.dol_escape_htmltag($obj->client).'</td>';
            print '  <td>'.($datec_ts ? dol_print_date($datec_ts, 'day') : '-').'</td>';
            print '  <td>'.price($obj->total_ttc).' €</td>';
            print '</tr>';
        }
        print '</table><br>';
        $db->free($resql);
    } else {
        print '<div class="opacitymedium">Aucun devis SAP récent trouvé.</div>';
    }

    // Factures SAP récentes (modèles cochés)
    $rawList = getDolGlobalString('ATTESTATIONSAP_FACTURE_MODEL_LIST','');
    if ($rawList === '') $rawList = getDolGlobalString('ATTESTATIONSAP_FACTURE_MODEL_NAME','facture_sap_v3');
    $models = sap_parse_models($rawList);
    $parts = array();
    for ($i=0; $i<count($models); $i++) $parts[] = "(f.model_pdf = '".$db->escape($models[$i])."' OR f.model_pdf LIKE '".$db->escape($models[$i])."%')";
    $wModel = $parts ? ' AND ('.implode(' OR ', $parts).')' : '';

    print load_fiche_titre('FACTURES SAP RÉCENTES', '', 'title_generic');
    $sql = "SELECT f.rowid, f.ref, f.total_ttc, COALESCE(f.datef, f.date_valid) AS dref, s.nom as client, f.model_pdf
            FROM ".MAIN_DB_PREFIX."facture as f
            LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON f.fk_soc = s.rowid
            WHERE f.entity=".(int)$conf->entity." AND f.type=0 ".$wModel."
            ORDER BY COALESCE(f.datef, f.date_valid) DESC
            LIMIT 5";
    $resql = $db->query($sql);
    if ($resql && $db->num_rows($resql) > 0) {
        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre"><th>Référence</th><th>Client</th><th>Date</th><th>Modèle</th><th>Montant TTC</th></tr>';
        while ($obj = $db->fetch_object($resql)) {
            $dts = $db->jdate($obj->dref);
            print '<tr class="oddeven">';
            print '  <td><a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$obj->rowid.'">'.dol_escape_htmltag($obj->ref).'</a></td>';
            print '  <td>'.dol_escape_htmltag($obj->client).'</td>';
            print '  <td>'.($dts ? dol_print_date($dts, 'day') : '-').'</td>';
            print '  <td>'.dol_escape_htmltag($obj->model_pdf).'</td>';
            print '  <td>'.price($obj->total_ttc).' €</td>';
            print '</tr>';
        }
        print '</table><br>';
        $db->free($resql);
    } else {
        print '<div class="opacitymedium">Aucune facture récente trouvée pour le(s) modèle(s) sélectionné(s).</div>';
    }

    // *** NOUVEAU : Attestations existantes (année courante) + envoi ***
    print load_fiche_titre('ATTESTATIONS EXISTANTES (ANNÉE '.(int)$year.')', '', 'title_generic');
    $recent = array();
    $gl = glob(rtrim($attDir,'/').'/attestation_sap_'.((int)$year).'-*-ATT*.pdf'); if (!$gl) $gl=array();
    for ($i=0; $i<count($gl); $i++) {
        $full = $gl[$i]; if (!is_file($full)) continue;
        $bn = basename($full);
        $client = '';
        if (preg_match('/^attestation_sap_(\d{4})\-([A-Z0-9\-]+)\-ATT(\d+)\.pdf$/i',$bn,$m)) $client=str_replace('-',' ',$m[2]);
        $recent[] = array(
            'basename'=>$bn,
            'client'=>$client,
            'date'=>@filemtime($full),
            'size'=>@filesize($full)
        );
    }
    if (empty($recent)) {
        print '<div class="opacitymedium">Aucune attestation trouvée pour '.(int)$year.'.</div>';
    } else {
        usort($recent,function($a,$b){ $da=$a['date']?:0; $dbb=$b['date']?:0; return ($dbb<$da)?-1:(($dbb>$da)?1:0); });
        $recent = array_slice($recent, 0, 10);
        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre"><th>Client</th><th>Fichier</th><th>Taille (Ko)</th><th>Créé le</th><th>Statut envoi</th><th>Actions</th></tr>';
        for ($i=0; $i<count($recent); $i++) {
            $r = $recent[$i];
            $bn = $r['basename'];
            $dlUrl = DOL_URL_ROOT.'/document.php?modulepart=attestationsap&file='.urlencode($bn);
            $sizeKo = ($r['size'] ? number_format($r['size'] / 1024, 1, ',', ' ').' Ko' : '-');

            // Tentative d’association au tiers
            $socid=0; $email='';
            if (!empty($r['client'])) {
                $sql="SELECT rowid,email FROM ".MAIN_DB_PREFIX."societe WHERE UPPER(nom)='".$db->escape(strtoupper($r['client']))."' LIMIT 1";
                $res=$db->query($sql); if($res && $o=$db->fetch_object($res)){ $socid=(int)$o->rowid; $email=(string)$o->email; }
            }

            $sentInfo = att_is_sent($attDir, $bn);
            $statusSend = '<span class="badge-not">Non envoyée</span>';
            if ($sentInfo) {
                $who = !empty($sentInfo['email']) ? $sentInfo['email'] : '';
                $when = !empty($sentInfo['date_txt']) ? $sentInfo['date_txt'] : '';
                $statusSend = '<span class="badge-sent">Envoyée'.($when?' le '.$when:'').($who?' à '.$who:'').'</span>';
            }

            $sendlink = '<span class="opacitymedium">Impossible d\'envoyer</span>';
            if ($socid>0 && !empty($email)) {
                $sendlink = '<a class="butAction" href="'.htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES).'?tab=dashboard&year='.$year.'&action=sendmail&socid='.$socid.'&file='.urlencode($bn).'&token='.newToken().'">Envoyer</a>';
            }

            print '<tr class="oddeven">';
            print '  <td>'.dol_escape_htmltag($r['client']).'</td>';
            print '  <td>'.dol_escape_htmltag($bn).'</td>';
            print '  <td>'.$sizeKo.'</td>';
            print '  <td>'.($r['date'] ? dol_print_date($r['date'],'dayhour') : '-').'</td>';
            print '  <td>'.$statusSend.'</td>';
            print '  <td><a class="button" href="'.$dlUrl.'" target="_blank">Télécharger</a> '.$sendlink.'</td>';
            print '</tr>';
        }
        print '</table><br>';
    }
}

llxFooter();
$db->close();
?>
<script>
// Coche/décoche toutes les cases d'une classe selon l'état d'une case maître
function checkAllInClass(master, cls) {
  var boxes = document.querySelectorAll('.' + cls);
  for (var i = 0; i < boxes.length; i++) boxes[i].checked = master.checked;
}
// Sélectionner tout / rien par classe (boutons)
function toggleAllByClass(cls, all) {
  var boxes = document.querySelectorAll('.' + cls);
  for (var i = 0; i < boxes.length; i++) boxes[i].checked = !!all;
}
</script>
