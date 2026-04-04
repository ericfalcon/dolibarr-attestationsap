<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check PDF Viewer');

$f = DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
$lines = file($f);

// Afficher lignes 598-750 (suite de showdocuments)
print '<pre style="font-size:10px;background:#1a2535;color:#c0d0e0;padding:8px;overflow:auto;max-height:700px">';
for ($n = 598; $n < min(800, count($lines)); $n++) {
    $line = $lines[$n];
    $hi = stripos($line, 'search') !== false
       || stripos($line, 'preview') !== false
       || stripos($line, 'fa-') !== false
       || stripos($line, 'href') !== false
       || stripos($line, 'document.php') !== false
       || stripos($line, 'popup') !== false
       || stripos($line, 'modal') !== false
       || stripos($line, 'jquery') !== false
       || stripos($line, 'dialog') !== false;
    if ($hi) print '<strong style="color:#7eb8f7">';
    print ($n+1).': '.htmlspecialchars($line);
    if ($hi) print '</strong>';
}
print '</pre>';

llxFooter();
$db->close();
