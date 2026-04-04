<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check PDF Viewer');

// Chercher dans TOUS les fichiers PHP du core le mécanisme de preview PDF
print '<h3>Fichiers PHP contenant "pdfjs" ou "pdf_preview" ou "apercu"</h3>';
$dirs = array(
    DOL_DOCUMENT_ROOT.'/core/lib/',
    DOL_DOCUMENT_ROOT.'/core/class/',
);
foreach ($dirs as $dir) {
    $files = glob($dir.'*.php');
    foreach ($files as $f) {
        $c = file_get_contents($f);
        if (stripos($c, 'pdfjs') !== false
         || stripos($c, 'pdf_preview') !== false
         || stripos($c, 'fa-search') !== false) {
            print '<p><strong>'.basename($f).'</strong></p>';
            $lines = explode("\n", $c);
            print '<pre style="font-size:10px">';
            foreach ($lines as $n => $line) {
                if (stripos($line, 'pdfjs') !== false
                 || stripos($line, 'pdf_preview') !== false
                 || stripos($line, 'fa-search') !== false) {
                    print ($n+1).': '.htmlspecialchars($line)."\n";
                }
            }
            print '</pre>';
        }
    }
}

// Inspecter le HTML source d'une fiche facture directement
print '<h3>Recherche dans viewimage.php</h3>';
$vi = DOL_DOCUMENT_ROOT.'/viewimage.php';
if (file_exists($vi)) {
    print '<p>Existe</p>';
    $c = file_get_contents($vi);
    print '<p>Taille: '.strlen($c).' octets</p>';
}

// Chercher le JS qui gère le viewer
print '<h3>JS natif Dolibarr</h3>';
$jsdir = DOL_DOCUMENT_ROOT.'/core/js/';
foreach (glob($jsdir.'*.js') as $jf) {
    print '<p>'.basename($jf).'</p>';
}

llxFooter();
$db->close();
