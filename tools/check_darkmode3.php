<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check dark mode 3');

$cssfile = DOL_DOCUMENT_ROOT.'/theme/eldy/style.css.php';
$lines = file($cssfile);

// Chercher comment DARKMODEENABLED est utilisé (valeurs, conditions)
print '<pre style="font-size:11px;background:#f5f5f5;padding:8px;overflow:auto">';
foreach ($lines as $n => $line) {
    if (stripos($line, 'DARKMODEENABLED') !== false
     || stripos($line, 'darkmode') !== false
     || stripos($line, 'dark-mode') !== false
     || stripos($line, 'colorscheme') !== false) {
        print ($n+1).': '.htmlspecialchars($line);
    }
}
print '</pre>';

// Chercher aussi dans les préférences utilisateur comment c'est sauvegardé
$f2 = DOL_DOCUMENT_ROOT.'/core/ajax/ajaxdirector.php';
if (file_exists($f2)) {
    $c = file_get_contents($f2);
    if (strpos($c, 'dark') !== false || strpos($c, 'DARK') !== false) {
        print '<h3>ajaxdirector.php - dark mode</h3><pre style="font-size:11px">';
        foreach (explode("\n", $c) as $n => $line) {
            if (stripos($line, 'dark') !== false) print ($n+1).': '.htmlspecialchars($line)."\n";
        }
        print '</pre>';
    }
}

llxFooter();
$db->close();
