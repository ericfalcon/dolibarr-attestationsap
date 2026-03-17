<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check statuts');

// Vérifier les constantes de statut des factures
print '<h3>Constantes Facture::STATUS_*</h3><pre>';
if (defined('Facture::STATUS_DRAFT'))     print 'STATUS_DRAFT     = '.Facture::STATUS_DRAFT."\n";
if (defined('Facture::STATUS_VALIDATED')) print 'STATUS_VALIDATED = '.Facture::STATUS_VALIDATED."\n";
if (defined('Facture::STATUS_CLOSED'))    print 'STATUS_CLOSED    = '.Facture::STATUS_CLOSED."\n";
if (defined('Facture::STATUS_ABANDONED')) print 'STATUS_ABANDONED = '.Facture::STATUS_ABANDONED."\n";
print '</pre>';

// Vérifier les factures du tiers PAILLONCY avec leur statut
print '<h3>Factures PAILLONCY avec statut</h3>';
$sql = "SELECT f.rowid, f.ref, f.fk_statut, f.datef, f.total_ttc,
               SUM(p.amount) as montant_paye
        FROM ".MAIN_DB_PREFIX."facture f
        LEFT JOIN ".MAIN_DB_PREFIX."paiement_facture pf ON pf.fk_facture = f.rowid
        LEFT JOIN ".MAIN_DB_PREFIX."paiement p ON p.rowid = pf.fk_paiement
        WHERE f.entity = ".(int)$conf->entity."
        AND f.type = 0
        GROUP BY f.rowid, f.ref, f.fk_statut, f.datef, f.total_ttc
        ORDER BY f.datef DESC
        LIMIT 10";
$res = $db->query($sql);
print '<table class="noborder"><tr class="liste_titre"><th>Ref</th><th>Statut</th><th>Date</th><th>TTC</th><th>Payé</th></tr>';
if ($res) {
    while ($o = $db->fetch_object($res)) {
        $statuts = array(0=>'Brouillon', 1=>'Validée (non payée)', 2=>'Payée', 3=>'Abandonnée');
        $statut = $statuts[$o->fk_statut] ?? 'Inconnu ('.$o->fk_statut.')';
        $color = $o->fk_statut == 2 ? 'color:green' : ($o->fk_statut == 1 ? 'color:orange' : 'color:red');
        print '<tr class="oddeven">';
        print '<td>'.dol_escape_htmltag($o->ref).'</td>';
        print '<td style="'.$color.'"><strong>'.dol_escape_htmltag($statut).'</strong></td>';
        print '<td>'.dol_print_date($db->jdate($o->datef), 'day').'</td>';
        print '<td>'.price($o->total_ttc).'</td>';
        print '<td>'.price($o->montant_paye ?: 0).'</td>';
        print '</tr>';
    }
}
print '</table>';

print '<h3>Conclusion</h3>';
print '<p>Le code actuel filtre <code>fk_statut IN (1,2)</code> = Validées ET Payées.</p>';
print '<p><strong>Légalement</strong> (art. 199 sexdecies CGI = "sommes versées") : seul le statut <strong>2 = Payée</strong> devrait être retenu.</p>';
print '<p>Modifier en <code>fk_statut = 2</code> pour ne prendre que les factures payées.</p>';

llxFooter();
$db->close();
