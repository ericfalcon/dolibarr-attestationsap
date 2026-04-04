<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check PDF Viewer');

$f = DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
$lines = file($f);

// Chercher directement les lignes avec href et document.php dans tout le fichier
print '<h3>Toutes les lignes avec "document.php" dans html.formfile.class.php</h3>';
print '<pre style="font-size:10px;background:#1a2535;color:#c0d0e0;padding:8px;overflow:auto;max-height:700px">';
foreach ($lines as $n => $line) {
    if (stripos($line, 'document.php') !== false || stripos($line, 'fa-search') !== false || stripos($line, '_preview') !== false) {
        // Afficher 3 lignes de contexte
        for ($i = max(0,$n-1); $i <= min(count($lines)-1,$n+3); $i++) {
            print ($i+1).': '.htmlspecialchars($lines[$i]);
        }
        print "---\n";
    }
}
print '</pre>';

llxFooter();
$db->close();
