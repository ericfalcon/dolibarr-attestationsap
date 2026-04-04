<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check PDF Viewer');

// Chercher getAdvancedPreviewUrl dans tous les fichiers
$f = DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
$lines = file($f);

print '<h3>Fonction getAdvancedPreviewUrl</h3>';
$in = false;
print '<pre style="font-size:10px;background:#1a2535;color:#c0d0e0;padding:8px;overflow:auto;max-height:700px">';
foreach ($lines as $n => $line) {
    if (strpos($line, 'function getAdvancedPreviewUrl') !== false) $in = true;
    if ($in) {
        print ($n+1).': '.htmlspecialchars($line);
        if ($n > 0 && trim($line) === '}' && $in) {
            static $closes = 0; $closes++;
            if ($closes > 1) { $in = false; }
        }
    }
}
print '</pre>';

// Aussi chercher showPreview dans formfile
$f2 = DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
$lines2 = file($f2);
print '<h3>Méthode showPreview dans FormFile</h3>';
$in2 = false; $cnt = 0;
print '<pre style="font-size:10px;background:#1a2535;color:#c0d0e0;padding:8px;overflow:auto;max-height:400px">';
foreach ($lines2 as $n => $line) {
    if (strpos($line, 'function showPreview') !== false) $in2 = true;
    if ($in2) {
        print ($n+1).': '.htmlspecialchars($line);
        $cnt++;
        if ($cnt > 60) { print '...(tronqué)'; break; }
    }
}
print '</pre>';

llxFooter();
$db->close();
