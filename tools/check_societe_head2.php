<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check societe head 2');

$f = DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
$lines = file($f);
$total = count($lines);
print '<p>Total lignes : '.$total.'</p>';

// Afficher lignes 43 à 200
print '<pre style="font-size:11px;background:#f5f5f5;padding:8px;overflow:auto">';
for ($n = 42; $n < min(200, $total); $n++) {
    $line = $lines[$n];
    // Surligner les lignes intéressantes
    $hi = (strpos($line,'hook') !== false || strpos($line,'module') !== false
        || strpos($line,'tab') !== false || strpos($line,'head[') !== false);
    if ($hi) print '<strong style="background:#ffffaa">';
    print ($n+1).': '.htmlspecialchars($line);
    if ($hi) print '</strong>';
}
print '</pre>';

llxFooter();
$db->close();
