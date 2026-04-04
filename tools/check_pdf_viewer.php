<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check PDF Viewer');

// Chercher dans TOUS les fichiers lib la fonction qui génère le lien loupe
$f = DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
$lines = file($f);

// Chercher la fonction showdocuments
$in_showdoc = false;
$depth = 0;
print '<h3>Fonction showdocuments (extrait)</h3>';
print '<pre style="font-size:10px;background:#f5f5f5;padding:8px;overflow:auto;max-height:600px">';
foreach ($lines as $n => $line) {
    if (strpos($line, 'function showdocuments') !== false) {
        $in_showdoc = true;
    }
    if ($in_showdoc) {
        // Chercher les lignes avec loupe / search / preview / document.php
        if (stripos($line, 'search') !== false
         || stripos($line, 'loupe') !== false
         || stripos($line, 'document.php') !== false
         || stripos($line, 'preview') !== false
         || stripos($line, 'pdfjs') !== false
         || stripos($line, 'iframe') !== false
         || stripos($line, 'modal') !== false
         || stripos($line, 'popup') !== false
         || stripos($line, 'viewimage') !== false
         || stripos($line, 'apercu') !== false
         || stripos($line, 'fa-eye') !== false
         || stripos($line, 'fa-file-pdf') !== false) {
            print ($n+1).': '.htmlspecialchars($line);
        }
        // Arrêter après 200 lignes dans la fonction
        static $cnt = 0; $cnt++;
        if ($cnt > 300) { print "...(tronqué)...\n"; break; }
    }
}
print '</pre>';

// Chercher aussi dans index.php de Dolibarr
print '<h3>Fichiers JS qui mentionnent pdfviewer</h3>';
$jsfiles = glob(DOL_DOCUMENT_ROOT.'/core/js/*.js');
foreach ($jsfiles as $jf) {
    $c = file_get_contents($jf);
    if (stripos($c, 'pdf') !== false || stripos($c, 'preview') !== false || stripos($c, 'apercu') !== false) {
        print '<p>'.basename($jf).'</p>';
    }
}

llxFooter();
$db->close();
