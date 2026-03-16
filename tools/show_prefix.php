<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();

llxHeader('', 'Préfixe + état modèles');

// Préfixe réel
print '<h3>Préfixe de tables</h3>';
print '<p><code>MAIN_DB_PREFIX</code> = <code>'.MAIN_DB_PREFIX.'</code></p>';
print '<p>Entité courante : <code>'.(int)$conf->entity.'</code></p>';

$e = (int)$conf->entity;

// Toutes les lignes invoice dans document_model
print '<h3>Lignes invoice dans '.MAIN_DB_PREFIX.'document_model</h3>';
$resql = $db->query("SELECT rowid, nom, entity FROM ".MAIN_DB_PREFIX."document_model WHERE type='invoice' ORDER BY entity, nom");
if ($resql) {
    print '<pre>';
    while ($o = $db->fetch_object($resql)) {
        $marker = ($o->entity == $e) ? ' ← votre entité' : '';
        print 'rowid='.$o->rowid.' entity='.$o->entity.' nom=['.addslashes($o->nom).']'.$marker."\n";
    }
    print '</pre>';
    $db->free($resql);
} else {
    print '<p class="error">'.$db->lasterror().'</p>';
}

// Constantes
print '<h3>Constante FACTURE_ADDON_PDF</h3>';
$resql2 = $db->query("SELECT name, value, entity FROM ".MAIN_DB_PREFIX."const WHERE name LIKE 'FACTURE_ADDON%' OR name LIKE 'ATTESTATIONSAP%FACTURE%' ORDER BY name");
if ($resql2) {
    print '<pre>';
    while ($o = $db->fetch_object($resql2)) {
        print $o->name.' (entity='.$o->entity.') = ['.$o->value."]\n";
    }
    print '</pre>';
    $db->free($resql2);
}

llxFooter();
$db->close();
