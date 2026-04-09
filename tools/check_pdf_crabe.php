<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check pdf_pagefoot affichage lignes');
$f = DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
$lines = file($f);
print '<pre style="font-size:10px;background:#1a2535;color:#c0d0e0;padding:8px;overflow:auto;max-height:500px">';
for ($n = 1320; $n < 1420; $n++) {
    print ($n+1).': '.htmlspecialchars($lines[$n]);
}
print '</pre>';
llxFooter(); $db->close();
