<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check PDF Viewer');

// Capturer le HTML généré par showdocuments pour une vraie facture
// en cherchant dans le HTML la loupe
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

// Chercher dans files.lib.php la fonction qui génère le HTML de la liste de documents
$f = DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
$lines = file($f);

// Chercher autour de "fa-search" ou "loupe" ou le lien vers document.php dans un contexte de liste
print '<h3>Contexte HTML viewer dans files.lib.php</h3>';
print '<pre style="font-size:10px;background:#1a2535;color:#c0d0e0;padding:8px;overflow:auto;max-height:500px">';
foreach ($lines as $n => $line) {
    if (stripos($line, 'fa-search') !== false
     || stripos($line, 'fa-eye') !== false
     || stripos($line, 'pdfjs') !== false
     || stripos($line, 'viewimage') !== false
     || (stripos($line, 'document.php') !== false)
     || stripos($line, 'dolPopupToOpenUrl') !== false
     || stripos($line, 'popup') !== false) {
        // Afficher aussi les 3 lignes autour
        $start = max(0, $n - 2);
        $end = min(count($lines)-1, $n + 2);
        for ($i = $start; $i <= $end; $i++) {
            print ($i+1).': '.htmlspecialchars($lines[$i]);
        }
        print "---\n";
    }
}
print '</pre>';

llxFooter();
$db->close();
