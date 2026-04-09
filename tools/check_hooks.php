<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check tabs fin');

$f = DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
$lines = file($f);
// Lignes 280 à 380 (fin de societe_prepare_head)
print '<pre style="font-size:10px;background:#1a2535;color:#c0d0e0;padding:8px;overflow:auto;max-height:500px">';
for ($n = 270; $n < 380; $n++) {
    if (!isset($lines[$n])) break;
    print ($n+1).': '.htmlspecialchars($lines[$n]);
}
print '</pre>';

llxFooter(); $db->close();
