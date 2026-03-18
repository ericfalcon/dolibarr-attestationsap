<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check toolbar');

// Chercher le hook pour la barre d'outils top
// et comment les icônes sont ajoutées dans le menu du haut
$f = DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
$lines = file($f);
print '<h3>Hooks barre outils / top menu</h3>';
print '<pre style="font-size:11px;background:#f5f5f5;padding:8px;overflow:auto">';
foreach ($lines as $n => $line) {
    if (stripos($line, 'topBarLeft\|topBarRight\|topmenu\|printTopRight\|executeHooks.*top') !== false
     || stripos($line, 'topBar') !== false
     || stripos($line, 'printTopRight') !== false) {
        print ($n+1).': '.htmlspecialchars($line);
    }
}
print '</pre>';

// Chercher dans menu.lib.php
$f2 = DOL_DOCUMENT_ROOT.'/core/lib/menu.lib.php';
if (file_exists($f2)) {
    $lines2 = file($f2);
    print '<h3>menu.lib.php - top bar</h3>';
    print '<pre style="font-size:11px;background:#f5f5f5;padding:8px;overflow:auto">';
    foreach ($lines2 as $n => $line) {
        if (stripos($line, 'topbar\|top_bar\|printTop\|hook.*top') !== false
         || stripos($line, 'topbar') !== false) {
            print ($n+1).': '.htmlspecialchars($line);
        }
    }
    print '</pre>';
}

llxFooter();
$db->close();
