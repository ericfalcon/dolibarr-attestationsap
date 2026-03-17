<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check societe head');

$f = DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
$lines = file($f);

// Lire societe_prepare_head complète
$in = false; $braces = 0;
print '<pre style="font-size:11px;background:#f5f5f5;padding:8px;overflow:auto">';
foreach ($lines as $n => $line) {
    if (!$in && strpos($line, 'function societe_prepare_head') !== false) { $in = true; }
    if ($in) {
        print ($n+1).': '.htmlspecialchars($line);
        $braces += substr_count($line,'{') - substr_count($line,'}');
        if ($braces <= 0 && $n > 0) break;
        if ($n > 200) { print "...(tronqué)\n"; break; } // max 200 lignes
    }
}
print '</pre>';

llxFooter();
$db->close();
