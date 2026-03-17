<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'liste_modeles code');

$f = DOL_DOCUMENT_ROOT.'/core/modules/facture/modules_facture.php';
$lines = file($f);

// Afficher depuis ligne 119 jusqu'à la fin de la fonction
print '<pre style="background:#f5f5f5;padding:10px;overflow:auto">';
$in_func = false;
$braces = 0;
for ($n = 118; $n < count($lines); $n++) {
    $line = $lines[$n];
    if (!$in_func) { $in_func = true; }
    print ($n+1).': '.htmlspecialchars($line);
    $braces += substr_count($line, '{') - substr_count($line, '}');
    if ($in_func && $braces <= 0 && $n > 120) break;
}
print '</pre>';

llxFooter();
$db->close();
