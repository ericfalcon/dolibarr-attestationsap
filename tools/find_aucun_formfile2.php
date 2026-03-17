<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();

llxHeader('', 'Find Aucun formfile2');

$f = DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
$lines = file($f);
$total = count($lines);

print '<p>Total lignes : '.$total.'</p>';

// Chercher TOUTES les occurrences de "Aucun" dans tout le fichier
print '<h3>Toutes occurrences "Aucun" dans le fichier</h3>';
for ($n = 0; $n < $total; $n++) {
    if (stripos($lines[$n], 'aucun') !== false || stripos($lines[$n], ': None') !== false) {
        $start = max(0, $n-5); $end = min($total-1, $n+5);
        print '<pre style="background:#fff3cd;border:1px solid #ffc107;padding:5px;margin:5px">';
        for ($i=$start;$i<=$end;$i++) {
            $mark = ($i===$n) ? '<b style="background:yellow">' : '';
            $end_mark = ($i===$n) ? '</b>' : '';
            print $mark.($i+1).': '.htmlspecialchars($lines[$i]).$end_mark;
        }
        print '</pre>';
    }
}

// Chercher aussi le bloc qui construit le select des modèles PDF
print '<h3>Blocs "select" / "option" dans showdocuments (lignes 500-900)</h3>';
for ($n = 500; $n < min(900, $total); $n++) {
    if (stripos($lines[$n], '<select') !== false || stripos($lines[$n], '<option') !== false
        || stripos($lines[$n], 'modelselected') !== false || stripos($lines[$n], '$list') !== false
        || stripos($lines[$n], 'listofmodels') !== false || stripos($lines[$n], 'getDefaultModel') !== false) {
        print '<pre style="margin:1px;padding:2px;background:#e8f5e9;border-left:3px solid #4caf50">';
        print ($n+1).': '.htmlspecialchars($lines[$n]);
        print '</pre>';
    }
}

llxFooter();
$db->close();
