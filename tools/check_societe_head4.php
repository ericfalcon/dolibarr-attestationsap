<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check societe head 4');

$f = DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
$lines = file($f);

print '<pre style="font-size:11px;background:#f5f5f5;padding:8px;overflow:auto">';
for ($n = 320; $n < min(450, count($lines)); $n++) {
    $line = $lines[$n];
    $hi = strpos($line,'hook') !== false || strpos($line,'complete') !== false
       || strpos($line,'executeHook') !== false || strpos($line,'return') !== false
       || strpos($line,'module') !== false;
    if ($hi) print '<strong style="background:#ffffaa">';
    print ($n+1).': '.htmlspecialchars($line);
    if ($hi) print '</strong>';
}
print '</pre>';

llxFooter();
$db->close();
