<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();

$entity = (int)$conf->entity;

llxHeader('', 'Fix description');
print '<h2>Fix champ description dans document_model</h2>';

// Le problème : description non vide → Dolibarr croit que c'est un modèle ODT
// Solution : vider description pour tous nos modèles PDF SAP

$models = array('facture_sap_v3', 'devis_sap_v2', 'devis_sap');

foreach ($models as $nom) {
    // Lire valeur actuelle
    $res = $db->query("SELECT rowid, description FROM ".MAIN_DB_PREFIX."document_model
                       WHERE nom='".$db->escape($nom)."' AND entity=".$entity);
    if ($res && $obj = $db->fetch_object($res)) {
        print '<p>Avant : <code>'.$nom.'</code> → description=['.htmlspecialchars($obj->description).']</p>';
        // Vider description
        $upd = "UPDATE ".MAIN_DB_PREFIX."document_model SET description=''
                WHERE rowid=".(int)$obj->rowid;
        if ($db->query($upd)) {
            print '<p class="ok">✓ description vidée pour <code>'.$nom.'</code></p>';
        } else {
            print '<p class="error">✗ '.$db->lasterror().'</p>';
        }
    }
}

// Vérification
print '<h3>État après correction</h3><pre>';
$res = $db->query("SELECT nom, libelle, description FROM ".MAIN_DB_PREFIX."document_model
                   WHERE entity=".$entity." AND type IN ('invoice','propal') ORDER BY nom");
while ($o = $db->fetch_object($res)) {
    print 'nom=['.$o->nom.'] libelle=['.$o->libelle.'] description=['.$o->description."]\n";
}
print '</pre>';
print '<p><strong>Faites maintenant Ctrl+F5 sur votre page de facture.</strong></p>';

llxFooter();
$db->close();
