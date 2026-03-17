<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();

llxHeader('', 'liste_modeles');

$f = DOL_DOCUMENT_ROOT.'/core/modules/facture/modules_facture.php';
print '<p>Fichier : <code>'.htmlspecialchars($f).'</code></p>';

$lines = file($f);
$total = count($lines);
print '<p>Total lignes : '.$total.'</p>';

// Afficher la fonction liste_modeles en entier
print '<h3>Fonction liste_modeles()</h3><pre style="background:#f5f5f5;padding:10px;overflow:auto">';
$in_func = false;
$braces = 0;
for ($n = 0; $n < $total; $n++) {
    $line = $lines[$n];
    if (!$in_func && stripos($line, 'function liste_modeles') !== false) {
        $in_func = true;
    }
    if ($in_func) {
        print ($n+1).': '.htmlspecialchars($line);
        $braces += substr_count($line, '{') - substr_count($line, '}');
        if ($braces <= 0 && $n > 0) break;
    }
}
print '</pre>';

// Aussi afficher le résultat réel de liste_modeles pour nos modèles
print '<h3>Résultat réel de ModelePDFFactures::liste_modeles()</h3>';
require_once DOL_DOCUMENT_ROOT.'/core/modules/facture/modules_facture.php';
$list = ModelePDFFactures::liste_modeles($db);
print '<pre>';
var_dump($list);
print '</pre>';

llxFooter();
$db->close();
