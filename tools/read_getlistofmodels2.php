<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'getListOfModels full');

$f = DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
$lines = file($f);

print '<pre style="background:#f5f5f5;padding:10px;overflow:auto">';
$braces = 0;
$started = false;
for ($n = 1966; $n < count($lines); $n++) {
    $line = $lines[$n];
    print ($n+1).': '.htmlspecialchars($line);
    $braces += substr_count($line, '{') - substr_count($line, '}');
    if (!$started && $braces > 0) $started = true;
    if ($started && $braces <= 0) break;
}
print '</pre>';

llxFooter();
$db->close();
