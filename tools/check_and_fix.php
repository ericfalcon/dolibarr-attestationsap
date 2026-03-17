<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
$entity = (int)$conf->entity;
llxHeader('', 'Check and fix');

// Lire l'état exact
print '<h3>État actuel en base</h3><pre>';
$res = $db->query("SELECT rowid, nom, libelle, description, entity, type FROM ".MAIN_DB_PREFIX."document_model
                   WHERE type IN ('invoice','propal') ORDER BY type, nom");
while ($o = $db->fetch_object($res)) {
    print 'rowid='.$o->rowid.' entity='.$o->entity.' type='.$o->type.' nom=['.$o->nom.'] libelle=['.$o->libelle.'] description=['.htmlspecialchars($o->description)."]\n";
}
print '</pre>';

// Forcer UPDATE direct sur toutes les lignes invoice/propal de cette entité
print '<h3>Fix forcé (UPDATE direct)</h3>';
$sql = "UPDATE ".MAIN_DB_PREFIX."document_model SET description='' 
        WHERE type IN ('invoice','propal') AND entity=".$entity;
if ($db->query($sql)) {
    print '<p class="ok">✓ UPDATE OK — '.$db->affected_rows(null).' ligne(s) modifiée(s)</p>';
} else {
    print '<p class="error">✗ '.$db->lasterror().'</p>';
}

// Vérifier après
print '<h3>État après</h3><pre>';
$res = $db->query("SELECT rowid, nom, libelle, description FROM ".MAIN_DB_PREFIX."document_model
                   WHERE type IN ('invoice','propal') AND entity=".$entity." ORDER BY nom");
while ($o = $db->fetch_object($res)) {
    print 'nom=['.$o->nom.'] description=['.htmlspecialchars($o->description)."]\n";
}
print '</pre>';
print '<p><strong>Ctrl+F5 maintenant.</strong></p>';

llxFooter();
$db->close();
