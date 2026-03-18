<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check topmenu 2');

// Lire menu.lib.php et chercher la structure div du top
$mf = DOL_DOCUMENT_ROOT.'/core/lib/menu.lib.php';
$lines = file($mf);
$total = count($lines);
print '<p>Total lignes menu.lib.php : '.$total.'</p>';

// Chercher les IDs/classes de la barre du haut
print '<h3>IDs et classes de la barre du haut</h3>';
print '<pre style="font-size:11px;background:#f5f5f5;padding:8px;overflow:auto">';
foreach ($lines as $n => $line) {
    if (strpos($line, 'id=') !== false || strpos($line, 'class=') !== false) {
        if (strpos($line, 'tmenu') !== false
         || strpos($line, 'login') !== false
         || strpos($line, 'right') !== false
         || strpos($line, 'top') !== false
         || strpos($line, 'search') !== false
         || strpos($line, 'ico') !== false
         || strpos($line, 'dol_') !== false) {
            print ($n+1).': '.htmlspecialchars(trim($line))."\n";
        }
    }
}
print '</pre>';

llxFooter();
$db->close();
