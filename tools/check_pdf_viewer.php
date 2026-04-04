<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check PDF Viewer');

// Chercher la méthode showdocuments dans FormFile
$f = DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
$lines = file($f);
$total = count($lines);
print '<p>Total lignes html.formfile.class.php : '.$total.'</p>';

// Trouver la méthode showdocuments
$start = 0;
foreach ($lines as $n => $line) {
    if (strpos($line, 'function showdocuments') !== false) {
        $start = $n;
        break;
    }
}
print '<p>showdocuments commence à la ligne '.($start+1).'</p>';

// Afficher 150 lignes depuis le début de la méthode
print '<pre style="font-size:10px;background:#1a2535;color:#c0d0e0;padding:8px;overflow:auto;max-height:600px">';
for ($n = $start; $n < min($start + 150, $total); $n++) {
    $line = $lines[$n];
    // Surligner les lignes importantes
    $hi = stripos($line, 'search') !== false
       || stripos($line, 'loupe') !== false
       || stripos($line, 'preview') !== false
       || stripos($line, 'fa-') !== false
       || stripos($line, 'href') !== false
       || stripos($line, 'document.php') !== false
       || stripos($line, 'popup') !== false
       || stripos($line, 'modal') !== false
       || stripos($line, 'iframe') !== false;
    if ($hi) print '<strong style="background:#0d3a5a">';
    print ($n+1).': '.htmlspecialchars($line);
    if ($hi) print '</strong>';
}
print '</pre>';

llxFooter();
$db->close();
