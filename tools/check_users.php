<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check users');

$entity = (int)$conf->entity;

// Requête actuelle dans SapIntervenants
print '<h3>Tous les utilisateurs actifs internes</h3>';
$sql = "SELECT rowid, lastname, firstname, email, statut, extern, employee 
        FROM ".MAIN_DB_PREFIX."user 
        WHERE entity IN (0,".$entity.") AND statut = 1 AND extern = 0
        ORDER BY lastname ASC";
$res = $db->query($sql);
print '<pre>';
if ($res) {
    while ($o = $db->fetch_object($res)) {
        print 'id='.$o->rowid.' name=['.dol_escape_htmltag($o->lastname).'] firstname=['.dol_escape_htmltag($o->firstname).'] statut='.$o->statut.' extern='.$o->extern.' employee='.$o->employee."\n";
    }
} else {
    print 'Erreur : '.$db->lasterror();
}
print '</pre>';

// Vérifier la version du fichier SapIntervenants sur le serveur
print '<h3>Version SapIntervenants.class.php sur le serveur</h3>';
$f = DOL_DOCUMENT_ROOT.'/custom/attestationsap/class/SapIntervenants.class.php';
$lines = file($f);
foreach ($lines as $n => $line) {
    if (stripos($line, 'employee') !== false || stripos($line, 'extern') !== false || stripos($line, 'y compris') !== false) {
        print '<p>Ligne '.($n+1).': <code>'.htmlspecialchars(trim($line)).'</code></p>';
    }
}

llxFooter();
$db->close();
