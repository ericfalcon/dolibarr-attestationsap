<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'read listBoxes complet');

$f = DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';
$lines = file($f);

// Trouver listBoxes
print '<pre style="font-size:11px;background:#f5f5f5;padding:8px;overflow:auto">';
$in = false; $braces = 0;
foreach ($lines as $n => $line) {
    if (!$in && strpos($line, 'function listBoxes') !== false) $in = true;
    if ($in) {
        print ($n+1).': '.htmlspecialchars($line);
        $braces += substr_count($line,'{') - substr_count($line,'}');
        if ($braces <= 0 && $n > 0) break;
    }
}
print '</pre>';

llxFooter();
$db->close();
