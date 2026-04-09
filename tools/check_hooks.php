<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check tabs tiers');

// Chercher societe_prepare_head
$f = DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
$lines = file($f);
print '<h3>societe_prepare_head dans company.lib.php</h3>';
$in = false; $cnt = 0;
print '<pre style="font-size:10px;background:#1a2535;color:#c0d0e0;padding:8px;overflow:auto;max-height:500px">';
foreach ($lines as $n => $line) {
    if (strpos($line, 'function societe_prepare_head') !== false) $in = true;
    if ($in) {
        print ($n+1).': '.htmlspecialchars($line);
        $cnt++;
        if ($cnt > 80) { print '...(tronqué)'; break; }
    }
}
print '</pre>';

llxFooter(); $db->close();
