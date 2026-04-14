<?php
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) { $i--; $j--; }
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) $res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) $res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");
require_once DOL_DOCUMENT_ROOT.'/custom/attestationsap/class/attestationsap.class.php';

if (!$user->admin) accessforbidden();

$action = GETPOST('action', 'alpha');
$facid = GETPOST('facid', 'int');

llxHeader('', 'Gestion des factures SAP');

print load_fiche_titre('Gestion des factures SAP', '', 'title_setup');

$att = new AttestationSAP($db);

// Traitement : Normaliser toutes les factures SAP
if ($action == 'normalize') {
    if (!verifCsrfToken()) {
        accessforbidden();
    }
    
    $nb = $att->normalizeExistingSapInvoices();
    
    if ($nb >= 0) {
        setEventMessages($nb.' facture(s) normalisée(s) avec succès', null, 'mesgs');
    } else {
        setEventMessages('Erreur lors de la normalisation : '.$att->error, null, 'errors');
    }
}

// Traitement : Marquer une facture
if ($action == 'mark' && $facid > 0) {
    if (!verifCsrfToken()) {
        accessforbidden();
    }
    
    $result = $att->markInvoiceAsSap($facid);
    
    if ($result > 0) {
        setEventMessages('Facture #'.$facid.' marquée comme SAP avec succès', null, 'mesgs');
    } else {
        setEventMessages('Erreur : '.$att->error, null, 'errors');
    }
}

// SECTION 1 : Normalisation des factures existantes
print '<div class="info">';
print '<h3>🔧 Normalisation des factures SAP existantes</h3>';
print '<p>Cette action va corriger automatiquement les factures qui ont :</p>';
print '<ul>';
print '<li><code>facture_sap_V3</code> (V majuscule) → <code>facture_sap_v3</code> (v minuscule)</li>';
print '<li>Toute variante de "facture_sap" → <code>facture_sap_v3</code></li>';
print '</ul>';

$sql_check = "SELECT COUNT(*) as nb
              FROM ".MAIN_DB_PREFIX."facture
              WHERE LOWER(model_pdf) LIKE '%facture_sap%'
              AND model_pdf != 'facture_sap_v3'";
$resql_check = $db->query($sql_check);
$obj_check = $db->fetch_object($resql_check);

if ($obj_check && $obj_check->nb > 0) {
    print '<p class="warning"><strong>'.$obj_check->nb.' facture(s) à normaliser</strong></p>';
    print '<form method="POST">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="normalize">';
    print '<input type="submit" class="button" value="Normaliser maintenant">';
    print '</form>';
} else {
    print '<p class="ok">✓ Toutes les factures SAP sont déjà normalisées</p>';
}
print '</div><br>';

// SECTION 2 : Factures SAP actuelles
print '<h3>✅ Factures SAP détectées</h3>';

$sql_sap = "SELECT f.rowid, f.ref, f.datef, f.total_ttc, f.model_pdf, f.paye, f.fk_statut, s.nom as client
            FROM ".MAIN_DB_PREFIX."facture f
            LEFT JOIN ".MAIN_DB_PREFIX."societe s ON f.fk_soc = s.rowid
            WHERE (LOWER(f.model_pdf) LIKE '%facture_sap%' OR LOWER(f.model_pdf) LIKE '%sap%')
            ORDER BY f.datef DESC
            LIMIT 50";

$resql_sap = $db->query($sql_sap);

if ($resql_sap && $db->num_rows($resql_sap) > 0) {
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<th>Ref</th>';
    print '<th>Client</th>';
    print '<th>Date</th>';
    print '<th>Montant TTC</th>';
    print '<th>model_pdf</th>';
    print '<th>Payée</th>';
    print '<th>Statut</th>';
    print '</tr>';
    
    while ($obj = $db->fetch_object($resql_sap)) {
        $is_paid = ($obj->paye == 1 && $obj->fk_statut == 2);
        $row_class = $is_paid ? 'oddeven' : 'oddeven opacitymedium';
        
        print '<tr class="'.$row_class.'">';
        print '<td><a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$obj->rowid.'" target="_blank">'.$obj->ref.'</a></td>';
        print '<td>'.$obj->client.'</td>';
        print '<td>'.dol_print_date($obj->datef, 'day').'</td>';
        print '<td align="right">'.price($obj->total_ttc).' €</td>';
        print '<td>';
        
        // Affichage avec code couleur
        if ($obj->model_pdf == 'facture_sap_v3') {
            print '<span style="color: green;">✓ '.$obj->model_pdf.'</span>';
        } else {
            print '<span style="color: orange;">⚠ '.$obj->model_pdf.'</span>';
        }
        print '</td>';
        print '<td>'.($obj->paye ? '<span style="color: green;">✓ Oui</span>' : '<span style="color: red;">✗ Non</span>').'</td>';
        print '<td>'.$obj->fk_statut.'</td>';
        print '</tr>';
    }
    
    print '</table>';
} else {
    print '<p class="warning">Aucune facture SAP trouvée. Utilisez la section ci-dessous pour marquer vos factures.</p>';
}

print '<br><br>';

// SECTION 3 : Marquer des factures comme SAP
print '<h3>➕ Marquer des factures existantes comme SAP</h3>';

$sql_other = "SELECT f.rowid, f.ref, f.datef, f.total_ttc, s.nom as client, f.model_pdf, f.paye, f.fk_statut
              FROM ".MAIN_DB_PREFIX."facture f
              LEFT JOIN ".MAIN_DB_PREFIX."societe s ON f.fk_soc = s.rowid
              WHERE f.paye = 1 
              AND f.fk_statut = 2
              AND f.type = 0
              AND (f.model_pdf NOT LIKE '%sap%' OR f.model_pdf IS NULL OR f.model_pdf = '')
              ORDER BY f.datef DESC
              LIMIT 50";

$resql_other = $db->query($sql_other);

if ($resql_other && $db->num_rows($resql_other) > 0) {
    print '<p>Factures payées non marquées comme SAP :</p>';
    
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<th>Ref</th>';
    print '<th>Client</th>';
    print '<th>Date</th>';
    print '<th>Montant TTC</th>';
    print '<th>Model actuel</th>';
    print '<th>Action</th>';
    print '</tr>';
    
    while ($obj = $db->fetch_object($resql_other)) {
        print '<tr class="oddeven">';
        print '<td><a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$obj->rowid.'" target="_blank">'.$obj->ref.'</a></td>';
        print '<td>'.$obj->client.'</td>';
        print '<td>'.dol_print_date($obj->datef, 'day').'</td>';
        print '<td align="right">'.price($obj->total_ttc).' €</td>';
        print '<td>'.($obj->model_pdf ?: '<em style="color: #999;">vide</em>').'</td>';
        print '<td>';
        print '<a href="?action=mark&facid='.$obj->rowid.'&token='.newToken().'" class="button smallpaddingimp">Marquer comme SAP</a>';
        print '</td>';
        print '</tr>';
    }
    
    print '</table>';
} else {
    print '<p class="opacitymedium">Aucune facture payée non-SAP trouvée.</p>';
}

print '<br><br>';

// SECTION 4 : Statistiques
print '<h3>📊 Statistiques</h3>';

$stats_year = date('Y');
$stats = $att->getYearStats($stats_year);

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">Année '.$stats_year.'</th></tr>';
print '<tr class="oddeven"><td>Nombre de clients</td><td><strong>'.$stats['total_clients'].'</strong></td></tr>';
print '<tr class="oddeven"><td>Nombre de factures SAP payées</td><td><strong>'.$stats['total_invoices'].'</strong></td></tr>';
print '<tr class="oddeven"><td>Total TTC</td><td><strong>'.price($stats['total_ttc']).' €</strong></td></tr>';
print '<tr class="oddeven"><td>Total heures</td><td><strong>'.number_format($stats['total_hours'], 2, ',', ' ').' h</strong></td></tr>';
print '</table>';

llxFooter();
?>