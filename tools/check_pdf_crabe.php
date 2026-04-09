<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check pdf_pagefoot showdetails=0');

$f = DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
$lines = file($f);
// Lignes 1187 à 1320 (suite de pdf_pagefoot)
print '<pre style="font-size:10px;background:#1a2535;color:#c0d0e0;padding:8px;overflow:auto;max-height:600px">';
for ($n = 1186; $n < 1320; $n++) {
    $line = $lines[$n];
    $hi = stripos($line, 'profession') !== false
       || stripos($line, 'idprof') !== false
       || stripos($line, 'showdetails') !== false
       || stripos($line, 'line3') !== false
       || stripos($line, 'line4') !== false
       || stripos($line, 'NAF') !== false;
    if ($hi) print '<strong style="color:#7eb8f7">';
    print ($n+1).': '.htmlspecialchars($line);
    if ($hi) print '</strong>';
}
print '</pre>';
llxFooter(); $db->close();
