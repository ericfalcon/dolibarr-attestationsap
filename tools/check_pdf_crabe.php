<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check showdetails dans pdf_crabe');

$f = DOL_DOCUMENT_ROOT.'/core/modules/facture/doc/pdf_crabe.modules.php';
$lines = file($f);
print '<pre style="font-size:10px;background:#1a2535;color:#c0d0e0;padding:8px;overflow:auto;max-height:400px">';
foreach ($lines as $n => $line) {
    if (stripos($line, 'showdetails') !== false || stripos($line, 'SHOW_FOOT') !== false) {
        print ($n+1).': '.htmlspecialchars($line);
    }
}
print '</pre>';
llxFooter(); $db->close();
