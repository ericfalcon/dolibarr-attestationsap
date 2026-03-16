<?php
/**
 * Supprime l'entrée orpheline 'facture_sap' de document_model
 */
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();

$entity = (int)$conf->entity;

llxHeader('', 'Suppression facture_sap orphelin');

$sql = "DELETE FROM ".MAIN_DB_PREFIX."document_model
        WHERE entity = ".$entity."
        AND type = 'invoice'
        AND nom = 'facture_sap'";

$resql = $db->query($sql);

if ($resql) {
    $nb = $db->affected_rows($resql);
    print '<p class="ok">✓ Supprimé '.$nb.' ligne(s) <code>facture_sap</code></p>';
} else {
    print '<p class="error">✗ Erreur : '.$db->lasterror().'</p>';
}

// Vérification
print '<h3>État après suppression</h3>';
$resql2 = $db->query("SELECT rowid, nom FROM ".MAIN_DB_PREFIX."document_model WHERE entity=".$entity." AND type='invoice' ORDER BY nom");
if ($resql2) {
    print '<pre>';
    while ($o = $db->fetch_object($resql2)) {
        print 'rowid='.$o->rowid.' nom=['.dol_escape_htmltag($o->nom)."]\n";
    }
    print '</pre>';
    $db->free($resql2);
}

print '<p><strong>Faites maintenant Ctrl+F5 sur votre page de facture.</strong></p>';

llxFooter();
$db->close();
