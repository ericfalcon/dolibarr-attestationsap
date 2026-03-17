<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check users 2');

// Voir les colonnes de la table user
$res = $db->query("SHOW COLUMNS FROM ".MAIN_DB_PREFIX."user");
print '<h3>Colonnes de la table user</h3><pre>';
if ($res) {
    while ($o = $db->fetch_object($res)) print $o->Field.' ('.$o->Type.")\n";
}
print '</pre>';

// Essai sans filtre extern
print '<h3>Tous les users actifs (sans filtre extern)</h3><pre>';
$res2 = $db->query("SELECT rowid, lastname, firstname, statut FROM ".MAIN_DB_PREFIX."user WHERE statut=1 ORDER BY lastname");
if ($res2) {
    while ($o = $db->fetch_object($res2)) {
        print 'id='.$o->rowid.' ['.dol_escape_htmltag($o->lastname).' '.dol_escape_htmltag($o->firstname).'] statut='.$o->statut."\n";
    }
}
print '</pre>';

llxFooter();
$db->close();
