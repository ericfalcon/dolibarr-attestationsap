<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check pdf_pagefoot');

// Chercher la fonction pdf_pagefoot
$f = DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
$lines = file($f);
print '<h3>pdf_pagefoot dans pdf.lib.php</h3>';
$in = false; $cnt = 0;
print '<pre style="font-size:10px;background:#1a2535;color:#c0d0e0;padding:8px;overflow:auto;max-height:400px">';
foreach ($lines as $n => $line) {
    if (strpos($line, 'function pdf_pagefoot') !== false) $in = true;
    if ($in) {
        print ($n+1).': '.htmlspecialchars($line);
        $cnt++;
        if ($cnt > 60) { print '...'; break; }
    }
}
print '</pre>';

llxFooter();
$db->close();
