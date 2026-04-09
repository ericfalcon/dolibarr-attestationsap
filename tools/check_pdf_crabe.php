<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check pdf_crabe');

$f = DOL_DOCUMENT_ROOT.'/core/modules/facture/doc/pdf_crabe.modules.php';
$lines = file($f);
print '<h3>PageNo et nb dans pdf_crabe</h3>';
print '<pre style="font-size:10px;background:#1a2535;color:#c0d0e0;padding:8px;overflow:auto">';
foreach ($lines as $n => $line) {
    if (stripos($line, 'PageNo') !== false
     || stripos($line, 'AliasNb') !== false
     || stripos($line, '{nb}') !== false
     || stripos($line, 'getNb') !== false
     || stripos($line, '_pagefoot') !== false) {
        print ($n+1).': '.htmlspecialchars($line);
    }
}
print '</pre>';

llxFooter();
$db->close();
