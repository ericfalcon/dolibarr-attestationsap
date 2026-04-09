<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check ligne 310-330 pdf_crabe');

$f = DOL_DOCUMENT_ROOT.'/core/modules/facture/doc/pdf_crabe.modules.php';
$lines = file($f);
print '<pre style="font-size:10px;background:#1a2535;color:#c0d0e0;padding:8px;overflow:auto">';
for ($n = 308; $n < 340; $n++) {
    print ($n+1).': '.htmlspecialchars($lines[$n]);
}
print '</pre>';
llxFooter(); $db->close();
