<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check dark mode 3');

$f = DOL_DOCUMENT_ROOT.'/theme/eldy/style.css.php';
$lines = file($f);
print '<pre style="font-size:11px;background:#f5f5f5;padding:8px;overflow:auto">';
for ($n=164; $n<min(230,count($lines)); $n++) {
    $line = $lines[$n];
    $hi = stripos($line,'dark')!==false;
    if ($hi) print '<strong style="background:#ffffaa">';
    print ($n+1).': '.htmlspecialchars($line);
    if ($hi) print '</strong>';
}
print '</pre>';

// Chercher les fichiers ajax qui gèrent dark
foreach (glob(DOL_DOCUMENT_ROOT.'/core/ajax/*.php') as $af) {
    $c = file_get_contents($af);
    if (stripos($c,'dark') !== false) {
        print '<p>✓ <code>'.basename($af).'</code> mentionne dark</p>';
        foreach (file($af) as $n => $l) {
            if (stripos($l,'dark') !== false) print '<p>'.($n+1).': <code>'.htmlspecialchars(trim($l)).'</code></p>';
        }
    }
}

llxFooter();
$db->close();
