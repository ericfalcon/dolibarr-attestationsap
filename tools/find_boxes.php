<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Find boxes');

// Trouver un exemple de box
$boxdir = DOL_DOCUMENT_ROOT.'/core/boxes/';
$files = glob($boxdir.'box_*.php');
print '<p>Dossier boxes : <code>'.htmlspecialchars($boxdir).'</code></p>';
print '<p>Exemples :</p><ul>';
foreach (array_slice($files, 0, 5) as $f) print '<li><code>'.basename($f).'</code></li>';
print '</ul>';

// Lire un exemple simple
$example = $boxdir.'box_invoices.php';
if (!file_exists($example)) $example = $files[0];
print '<h3>Exemple : '.basename($example).'</h3>';
print '<pre>'.htmlspecialchars(file_get_contents($example)).'</pre>';

llxFooter();
$db->close();
