<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Find boxes');

$boxdir = DOL_DOCUMENT_ROOT.'/core/boxes/';
$files = glob($boxdir.'box_*.php');
print '<p>Dossier : <code>'.htmlspecialchars($boxdir).'</code></p>';

// Chercher un box simple (pas trop long)
$best = '';
foreach ($files as $f) {
    $size = filesize($f);
    if ($size > 2000 && $size < 8000) { $best = $f; break; }
}
if (!$best) $best = $files[0];

print '<p>Exemple lu : <code>'.basename($best).'</code> ('.filesize($best).' octets)</p>';
print '<pre style="font-size:11px">'.htmlspecialchars(file_get_contents($best)).'</pre>';

// Aussi : comment un module custom déclare ses boxes
// Chercher dans un module custom
foreach (glob(DOL_DOCUMENT_ROOT.'/custom/*/core/boxes/box_*.php') as $f) {
    print '<h3>Box custom trouvée : '.htmlspecialchars($f).'</h3>';
    print '<pre style="font-size:11px">'.htmlspecialchars(file_get_contents($f)).'</pre>';
    break;
}

llxFooter();
$db->close();
