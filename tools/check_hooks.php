<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check addMoreTabsLinks');

// Chercher addMoreTabsLinks dans Dolibarr
$files = array(
    DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php',
    DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php',
    DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php',
);
print '<pre style="font-size:10px;background:#1a2535;color:#c0d0e0;padding:8px;overflow:auto">';
foreach ($files as $f) {
    $c = file_get_contents($f);
    if (stripos($c, 'addMoreTabsLinks') !== false || stripos($c, 'complete_head') !== false) {
        print '<strong>'.basename($f).'</strong>'."\n";
        foreach (file($f) as $n => $line) {
            if (stripos($line, 'addMoreTabsLinks') !== false || stripos($line, 'complete_head') !== false) {
                print ($n+1).': '.htmlspecialchars($line);
            }
        }
        print "\n";
    }
}
print '</pre>';

llxFooter(); $db->close();
