<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();

llxHeader('', 'Source Aucun');

// Chercher "Aucun" dans html.form.class.php
$f = DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
$lines = file($f);
foreach ($lines as $n => $line) {
    if (stripos($line, 'aucun') !== false || stripos($line, ': None') !== false || stripos($line, 'nomodel') !== false) {
        // Afficher aussi les 5 lignes autour
        $start = max(0, $n - 3);
        $end   = min(count($lines)-1, $n + 3);
        print '<hr><strong>'.basename($f).' ligne '.($n+1).'</strong><pre>';
        for ($i = $start; $i <= $end; $i++) {
            $marker = ($i === $n) ? '>>> ' : '    ';
            print $marker.($i+1).': '.htmlspecialchars($lines[$i]);
        }
        print '</pre>';
    }
}

// Chercher aussi dans document.lib.php
$f2 = DOL_DOCUMENT_ROOT.'/core/lib/document.lib.php';
if (file_exists($f2)) {
    $lines2 = file($f2);
    foreach ($lines2 as $n => $line) {
        if (stripos($line, 'aucun') !== false || stripos($line, 'nomodel') !== false) {
            print '<hr><strong>document.lib.php ligne '.($n+1).'</strong><pre>';
            $start = max(0, $n-3); $end = min(count($lines2)-1, $n+3);
            for ($i=$start;$i<=$end;$i++) {
                $marker = ($i===$n)?'>>> ':'    ';
                print $marker.($i+1).': '.htmlspecialchars($lines2[$i]);
            }
            print '</pre>';
        }
    }
}

llxFooter();
$db->close();
