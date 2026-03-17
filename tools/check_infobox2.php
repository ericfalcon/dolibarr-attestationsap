<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check InfoBox 2');

// Lire uniquement le code de listBoxes sans l'exécuter
$f = DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';
$lines = file($f);

print '<h3>Fonction listBoxes() — code source</h3>';
$in = false; $braces = 0;
print '<pre style="font-size:11px;background:#f5f5f5;padding:8px;overflow:auto">';
foreach ($lines as $n => $line) {
    if (!$in && strpos($line, 'function listBoxes') !== false) $in = true;
    if ($in) {
        print ($n+1).': '.htmlspecialchars($line);
        $braces += substr_count($line,'{') - substr_count($line,'}');
        if ($braces <= 0 && $n > 0) break;
    }
}
print '</pre>';

// Aussi : comment le fichier box est-il cherché ?
// Chercher "buildpath" ou "custom" dans modules_boxes.php
print '<h3>Occurrences "buildpath" / "custom" / "boxes" dans modules_boxes.php</h3>';
print '<pre style="font-size:11px;background:#f5f5f5;padding:8px">';
foreach ($lines as $n => $line) {
    if (stripos($line,'buildpath')!==false || stripos($line,'custom')!==false
        || stripos($line,'scan')!==false || stripos($line,'glob')!==false
        || stripos($line,'include')!==false || stripos($line,'require')!==false) {
        print ($n+1).': '.htmlspecialchars($line);
    }
}
print '</pre>';

llxFooter();
$db->close();
