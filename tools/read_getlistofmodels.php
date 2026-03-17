<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'getListOfModels');

$f = DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
$lines = file($f);

// Trouver et afficher getListOfModels
print '<pre style="background:#f5f5f5;padding:10px;overflow:auto">';
$in_func = false;
$braces = 0;
for ($n = 0; $n < count($lines); $n++) {
    $line = $lines[$n];
    if (!$in_func && strpos($line, 'function getListOfModels') !== false) {
        $in_func = true;
    }
    if ($in_func) {
        print ($n+1).': '.htmlspecialchars($line);
        $braces += substr_count($line, '{') - substr_count($line, '}');
        if ($braces <= 0 && $n > 0) break;
    }
}
print '</pre>';

llxFooter();
$db->close();
