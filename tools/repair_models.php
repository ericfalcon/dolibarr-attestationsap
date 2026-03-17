<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();

$entity = (int)$conf->entity;

llxHeader('', 'Réparation modèles');
print '<h2>Réparation des modèles de documents</h2>';

// Modèles à s'assurer qu'ils existent
$needed = array(
    array('nom' => 'crabe',         'type' => 'invoice', 'libelle' => 'crabe'),
    array('nom' => 'sponge',        'type' => 'invoice', 'libelle' => 'sponge'),
    array('nom' => 'facture_sap_v3','type' => 'invoice', 'libelle' => 'facture_sap_v3'),
    array('nom' => 'devis_sap_v2',  'type' => 'propal',  'libelle' => 'devis_sap_v2'),
    array('nom' => 'devis_sap',     'type' => 'propal',  'libelle' => 'devis_sap'),
);

foreach ($needed as $m) {
    // Vérifier si existe
    $res = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."document_model
                       WHERE nom='".$db->escape($m['nom'])."' AND entity=".$entity." AND type='".$db->escape($m['type'])."'");
    if ($res && $db->num_rows($res) > 0) {
        print '<p>✓ Déjà présent : <code>'.$m['nom'].'</code> ('.$m['type'].')</p>';
    } else {
        $ins = "INSERT INTO ".MAIN_DB_PREFIX."document_model (nom, entity, type, libelle, description)
                VALUES ('".$db->escape($m['nom'])."', ".$entity.", '".$db->escape($m['type'])."',
                        '".$db->escape($m['libelle'])."', 'Réparé auto')";
        if ($db->query($ins)) {
            print '<p class="ok">✓ Inséré : <code>'.$m['nom'].'</code> ('.$m['type'].')</p>';
        } else {
            print '<p class="error">✗ Erreur : '.$db->lasterror().'</p>';
        }
    }
}

// État final
print '<h3>État final</h3><pre>';
$res = $db->query("SELECT nom, type FROM ".MAIN_DB_PREFIX."document_model WHERE entity=".$entity." AND type IN ('invoice','propal') ORDER BY type,nom");
while ($o = $db->fetch_object($res)) print $o->type.' : '.$o->nom."\n";
print '</pre>';
print '<p><strong>Faites Ctrl+F5 sur votre page de facture.</strong></p>';

llxFooter();
$db->close();
