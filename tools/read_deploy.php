<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'read deploy');

// Lire comment Dolibarr installe un module depuis deploy
// et comment il gère les boxes à l'activation

// 1. Regarder DolibarrModules::_init() pour voir si elle copie des fichiers
$f = DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';
$lines = file($f);
$total = count($lines);
print '<p>DolibarrModules.class.php : '.$total.' lignes</p>';

// Chercher la gestion des boxes dans _init
print '<h3>Occurrences "boxes" / "copy" / "widget" dans DolibarrModules</h3>';
print '<pre style="font-size:11px;background:#f5f5f5;padding:5px;overflow:auto">';
foreach ($lines as $n => $line) {
    if (preg_match('/\bboxes\b|\bcopy\b|\bwidget\b/i', $line)) {
        print ($n+1).': '.htmlspecialchars($line);
    }
}
print '</pre>';

// 2. Regarder comment un module comme "holiday" déclare sa box
// (module natif qui a des boxes dans core/boxes/)
$holiday_mod = DOL_DOCUMENT_ROOT.'/core/modules/modHoliday.class.php';
if (file_exists($holiday_mod)) {
    print '<h3>modHoliday boxes déclaration</h3>';
    $hlines = file($holiday_mod);
    foreach ($hlines as $n => $line) {
        if (stripos($line, 'boxes') !== false || stripos($line, 'box_') !== false) {
            $start = max(0,$n-1); $end = min(count($hlines)-1,$n+3);
            print '<pre style="margin:2px;font-size:11px;background:#e8f5e9;padding:3px">';
            for ($i=$start;$i<=$end;$i++) print ($i+1).': '.htmlspecialchars($hlines[$i]);
            print '</pre>';
        }
    }
}

llxFooter();
$db->close();
