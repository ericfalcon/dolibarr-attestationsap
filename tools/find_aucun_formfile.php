<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();

llxHeader('', 'Find Aucun formfile');

$f = DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
$lines = file($f);
$total = count($lines);

// Chercher autour de la logique du select des modèles
// On cherche : le code qui construit les options du select modele
// et qui affiche le nom du modèle (avec éventuellement ": Aucun")
$terms = array('Aucun', 'aucun', 'nomodel', 'option', 'class_path', 'pdf_', '$obj->name', 'classname', 'new $class');

print '<h3>Lignes contenant les termes clés (450-850)</h3>';
for ($n = 450; $n < min(850, $total); $n++) {
    $line = $lines[$n];
    foreach ($terms as $term) {
        if (stripos($line, $term) !== false) {
            print '<pre style="margin:1px;padding:2px;background:#f9f9f9;border-left:3px solid #999">';
            print '<b>'.($n+1).':</b> '.htmlspecialchars($line);
            print '</pre>';
            break;
        }
    }
}

llxFooter();
$db->close();
