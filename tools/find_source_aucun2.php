<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();

llxHeader('', 'Source Aucun 2');

function searchInFile($filepath, $terms) {
    if (!file_exists($filepath)) { print '<p>Absent: '.htmlspecialchars($filepath).'</p>'; return; }
    $lines = file($filepath);
    $found = false;
    foreach ($lines as $n => $line) {
        foreach ($terms as $term) {
            if (stripos($line, $term) !== false) {
                if (!$found) { print '<h3>'.htmlspecialchars(basename($filepath)).'</h3>'; $found = true; }
                $start = max(0, $n-2); $end = min(count($lines)-1, $n+4);
                print '<pre style="background:#f5f5f5;border:1px solid #ccc;padding:5px">';
                for ($i=$start;$i<=$end;$i++) {
                    $m = ($i===$n)?'<b style="background:yellow">>>> '.($i+1).': '.htmlspecialchars($lines[$i]).'</b>':
                                   '    '.($i+1).': '.htmlspecialchars($lines[$i]);
                    print $m;
                }
                print '</pre>';
                break;
            }
        }
    }
}

$terms = array('Aucun', ': None', 'nomodel', 'showDocuments', 'document_model');

// Fichiers à inspecter
$files = array(
    DOL_DOCUMENT_ROOT.'/compta/facture/card.php',
    DOL_DOCUMENT_ROOT.'/core/modules/facture/modules_facture.php',
    DOL_DOCUMENT_ROOT.'/core/lib/document.lib.php',
    DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php',
);

foreach ($files as $f) searchInFile($f, $terms);

// Chercher aussi dans tous les fichiers du dossier modules/facture
$dir = DOL_DOCUMENT_ROOT.'/core/modules/facture/';
foreach (glob($dir.'*.php') as $f) searchInFile($f, array('Aucun', 'nomodel', 'document_model'));

llxFooter();
$db->close();
