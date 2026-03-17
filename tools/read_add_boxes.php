<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'read add_boxes');

$f = DOL_DOCUMENT_ROOT.'/core/class/modules/DolibarrModules.class.php';
if (!file_exists($f)) $f = DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';
$lines = file($f);

// Lignes 1434 à 1535 (add_boxes)
print '<pre style="font-size:11px;background:#f5f5f5;padding:8px;overflow:auto">';
for ($n = 1433; $n < min(1535, count($lines)); $n++) {
    print ($n+1).': '.htmlspecialchars($lines[$n]);
}
print '</pre>';

llxFooter();
$db->close();
