<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'read listBoxes');

$f = DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';
$lines = file($f);

// Lignes 490 à 560
print '<pre style="font-size:11px;background:#f5f5f5;padding:8px;overflow:auto">';
for ($n = 489; $n < min(560, count($lines)); $n++) {
    print ($n+1).': '.htmlspecialchars($lines[$n]);
}
print '</pre>';

llxFooter();
$db->close();
