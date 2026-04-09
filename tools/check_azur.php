<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check pdf_azur AliasNbPages');
$f = DOL_DOCUMENT_ROOT.'/core/modules/propale/doc/pdf_azur.modules.php';
$lines = file($f);
print '<pre style="font-size:10px;background:#1a2535;color:#c0d0e0;padding:8px">';
foreach ($lines as $n => $line) {
    if (stripos($line, 'AliasNbPages') !== false || stripos($line, '_pagefoot') !== false) {
        print ($n+1).': '.htmlspecialchars($line);
    }
}
print '</pre>';
llxFooter(); $db->close();
