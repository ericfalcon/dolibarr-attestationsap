<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check toolbar 2');

// Chercher tous les hooks disponibles dans Dolibarr 22
// en listant les executeHooks dans les fichiers de layout
$topfile = DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
$lines = file($topfile);
print '<h3>Tous les executeHooks dans functions2.lib.php</h3>';
print '<pre style="font-size:11px;background:#f5f5f5;padding:8px;overflow:auto">';
foreach ($lines as $n => $line) {
    if (strpos($line, 'executeHooks') !== false) {
        print ($n+1).': '.htmlspecialchars($line);
    }
}
print '</pre>';

// Chercher dans le fichier de layout principal
$layout = DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
$lines2 = file($layout);
print '<h3>executeHooks dans functions.lib.php</h3>';
print '<pre style="font-size:11px;background:#f5f5f5;padding:8px;overflow:auto">';
foreach ($lines2 as $n => $line) {
    if (strpos($line, 'executeHooks') !== false) {
        print ($n+1).': '.htmlspecialchars($line);
    }
}
print '</pre>';

llxFooter();
$db->close();
