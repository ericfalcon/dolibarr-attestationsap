<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check tabs tiers fin');

$f = DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
$lines = file($f);
// Chercher la fin de societe_prepare_head avec le hook
print '<pre style="font-size:10px;background:#1a2535;color:#c0d0e0;padding:8px;overflow:auto;max-height:600px">';
$in = false; $cnt = 0;
foreach ($lines as $n => $line) {
    if (strpos($line, 'function societe_prepare_head') !== false) $in = true;
    if ($in) {
        // Afficher seulement les lignes avec hook ou complete_head
        if (stripos($line, 'hook') !== false
         || stripos($line, 'complete_head') !== false
         || stripos($line, 'executeHooks') !== false
         || ($cnt > 60)) {
            print ($n+1).': '.htmlspecialchars($line);
        }
        $cnt++;
        if ($cnt > 200) { print '...'; break; }
    }
}
print '</pre>';

llxFooter(); $db->close();
