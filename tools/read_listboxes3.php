<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'read listBoxes3');

$f = DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';
$lines = file($f);
$total = count($lines);

// Trouver la ligne de listBoxes
$start = 0;
foreach ($lines as $n => $line) {
    if (strpos($line, 'function listBoxes') !== false) { $start = $n; break; }
}

print '<p>listBoxes trouvée ligne : '.($start+1).' / total : '.$total.'</p>';

// Afficher 80 lignes à partir de là
print '<pre style="font-size:11px;background:#f5f5f5;padding:8px;overflow:auto">';
for ($n = $start; $n < min($start+80, $total); $n++) {
    print ($n+1).': '.htmlspecialchars($lines[$n]);
}
print '</pre>';

llxFooter();
$db->close();
