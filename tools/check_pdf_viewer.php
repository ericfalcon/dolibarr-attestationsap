<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check PDF Viewer');

// Lire le contexte autour de pdf_preview dans files.lib.php
$f = DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
$lines = file($f);

print '<h3>Contexte pdf_preview dans files.lib.php (lignes 1820-1870)</h3>';
print '<pre style="font-size:10px;background:#f5f5f5;padding:8px;overflow:auto">';
for ($n = 1820; $n < min(1880, count($lines)); $n++) {
    print ($n+1).': '.htmlspecialchars($lines[$n]);
}
print '</pre>';

// Chercher aussi les appels à dol_print_file ou showdocuments
print '<h3>Recherche "dol_print_file\|showdocuments\|pdf_preview" dans files.lib.php</h3>';
print '<pre style="font-size:10px;background:#f5f5f5;padding:8px;overflow:auto">';
foreach ($lines as $n => $line) {
    if (stripos($line, 'pdf_preview') !== false
     || stripos($line, 'showdocuments') !== false
     || stripos($line, 'dol_print_file') !== false
     || stripos($line, 'viewimage') !== false) {
        print ($n+1).': '.htmlspecialchars($line);
    }
}
print '</pre>';

// Chercher dans document.php comment il sert les fichiers
$dp = DOL_DOCUMENT_ROOT.'/document.php';
$dplines = file($dp);
print '<h3>document.php — paramètres clés</h3>';
print '<pre style="font-size:10px;background:#f5f5f5;padding:8px;overflow:auto">';
foreach ($dplines as $n => $line) {
    if (stripos($line, 'inline') !== false
     || stripos($line, 'Content-Disposition') !== false
     || stripos($line, 'modulepart') !== false
     || stripos($line, 'attachment') !== false) {
        print ($n+1).': '.htmlspecialchars($line);
    }
}
print '</pre>';

llxFooter();
$db->close();
