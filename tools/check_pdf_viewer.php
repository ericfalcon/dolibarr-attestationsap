<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check PDF Viewer');

// Chercher comment le viewer PDF est généré dans showdocuments
$f = DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
$lines = file($f);
print '<h3>Viewer PDF dans functions2.lib.php</h3>';
print '<pre style="font-size:11px;background:#f5f5f5;padding:8px;overflow:auto">';
foreach ($lines as $n => $line) {
    if (stripos($line, 'fa-search') !== false
     || stripos($line, 'apercu') !== false
     || stripos($line, 'showphoto') !== false
     || stripos($line, 'pdfviewer') !== false
     || stripos($line, 'documentpreview') !== false
     || (stripos($line, 'document.php') !== false && stripos($line, 'href') !== false)) {
        print ($n+1).': '.htmlspecialchars($line);
    }
}
print '</pre>';

llxFooter();
$db->close();
