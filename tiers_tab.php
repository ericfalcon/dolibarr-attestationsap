<?php
/**
 * Onglet Attestations SAP sur la fiche tiers
 */

$res = 0;
if (!$res && file_exists(__DIR__ . '/../../main.inc.php'))    $res = @include __DIR__ . '/../../main.inc.php';
if (!$res && file_exists(__DIR__ . '/../../../main.inc.php')) $res = @include __DIR__ . '/../../../main.inc.php';
if (!$res) { header('HTTP/1.1 500'); exit; }

require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';

$socid = GETPOST('socid', 'int');
if ($socid <= 0) accessforbidden();
if (empty($user->rights->societe->lire) && empty($user->admin)) accessforbidden();

$soc = new Societe($db);
if ($soc->fetch($socid) <= 0) {
    header('Location: '.DOL_URL_ROOT.'/societe/list.php');
    exit;
}

$langs->load('companies');

// Onglets de la fiche tiers
$head = societe_prepare_head($soc);

llxHeader('', 'Attestations SAP — '.$soc->name);

print dol_get_fiche_head($head, 'attestationsap', $langs->trans('ThirdParty'), -1, 'company');

// Bannière tiers
$linkback = '<a href="'.DOL_URL_ROOT.'/societe/list.php">'.$langs->trans('BackToList').'</a>';
$morehtmlref = '';
dol_banner_tab($soc, 'socid', $linkback, 1, 'rowid', 'nom', $morehtmlref);

print '<div class="fichecenter">';
print '</div>';
print dol_get_fiche_end();

// ============================================================
// Liste des attestations pour ce tiers
// ============================================================

$outputdir = getDolGlobalString('ATTESTATIONSAP_OUTPUTDIR', '');
if (empty($outputdir) || strpos($outputdir, DOL_DATA_ROOT) !== 0) {
    $outputdir = DOL_DATA_ROOT.'/attestationsap';
}

// Chercher tous les PDFs de ce tiers
$clean = preg_replace('/[^A-Za-z0-9_-]/', '', strtoupper($soc->name));
$pattern = $outputdir.'/attestation_sap_*-'.$clean.'-ATT*.pdf';
$files = is_dir($outputdir) ? glob($pattern) : array();
// Fallback avec dol_string_nospecial
if (empty($files)) {
    $clean2 = preg_replace('/[^A-Za-z0-9_-]/', '', strtoupper(dol_string_nospecial($soc->name)));
    $files = is_dir($outputdir) ? glob($outputdir.'/attestation_sap_*-'.$clean2.'-ATT*.pdf') : array();
}
if (!$files) $files = array();
rsort($files);

print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>Fichier</th>';
print '<th>Année</th>';
print '<th class="center">Taille</th>';
print '<th>Créé le</th>';
print '<th>Statut envoi</th>';
print '<th class="center">Actions</th>';
print '</tr>';

if (empty($files)) {
    print '<tr class="oddeven"><td colspan="6" class="center opacitymedium">Aucune attestation générée pour ce client.</td></tr>';
} else {
    $flip = false;
    foreach ($files as $filepath) {
        $bn    = basename($filepath);
        $size  = round(filesize($filepath) / 1024, 1);
        $mtime = filemtime($filepath);
        $css   = $flip ? 'even' : 'oddeven';
        $flip  = !$flip;

        // Extraire l'année depuis le nom de fichier
        preg_match('/attestation_sap_(\d{4})/', $bn, $m);
        $year = $m[1] ?? '—';

        // Lire le statut d'envoi
        $sentFile = $outputdir.'/'.$bn.'.sent.json';
        $sentInfo = '';
        if (file_exists($sentFile)) {
            $data = @json_decode(file_get_contents($sentFile), true);
            if (!empty($data['sent_at']) && !empty($data['email'])) {
                $dt = dol_print_date(strtotime($data['sent_at']), 'dayhour');
                $sentInfo = '<span class="badge badge-status4 badge-status">Envoyée le '.$dt.' à '.dol_escape_htmltag($data['email']).'</span>';
            }
        }
        if (!$sentInfo) {
            $sentInfo = '<span class="badge badge-status0 badge-status">Non envoyée</span>';
        }

        // URL téléchargement
        $dlUrl = DOL_URL_ROOT.'/document.php?modulepart=attestationsap&file='.urlencode($bn);

        print '<tr class="'.$css.'">';
        print '<td><a href="'.dol_escape_htmltag($dlUrl).'" target="_blank">'.dol_escape_htmltag($bn).'</a></td>';
        print '<td class="center">'.$year.'</td>';
        print '<td class="center">'.$size.' Ko</td>';
        print '<td>'.dol_print_date($mtime, 'dayhour').'</td>';
        print '<td>'.$sentInfo.'</td>';
        print '<td class="center">';
        print '<a href="'.dol_buildpath('/custom/attestationsap/index.php', 1).'?tab=generate&year='.$year.'" class="butAction">Gérer</a>';
        print '</td>';
        print '</tr>';
    }
}
print '</table>';
print '</div>';

// Lien vers la génération
print '<br><div class="center">';
print '<a href="'.dol_buildpath('/custom/attestationsap/index.php', 1).'?tab=generate" class="butAction">📋 Générer / Envoyer les attestations</a>';
print '</div>';

llxFooter();
$db->close();
