<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check InfoBox');

include_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';

// Ce qu'admin/boxes.php fait exactement
$boxtoadd = InfoBox::listBoxes($db, 'available', -1, null, array());

print '<h3>Boxes disponibles selon InfoBox::listBoxes()</h3>';
print '<p>Nombre : '.count($boxtoadd).'</p>';
$found_sap = false;
foreach ($boxtoadd as $box) {
    $mark = (strpos($box->box_id ?? '', 'attestationsap') !== false
          || strpos($box->file ?? '', 'attestationsap') !== false) ? ' <b style="color:green">← SAP</b>' : '';
    if ($mark) $found_sap = true;
    print '<p>file=['.htmlspecialchars($box->file ?? '').'] box_id=['.($box->box_id ?? '').']'.$mark.'</p>';
}
if (!$found_sap) print '<p class="error">✗ Notre box SAP n\'apparaît PAS dans la liste disponible</p>';

// Regarder le code de listBoxes
print '<h3>Code de InfoBox::listBoxes()</h3>';
$f = DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';
$lines = file($f);
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

llxFooter();
$db->close();
