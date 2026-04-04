<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check PDF Viewer');

// Chercher dans le fichier card.php de facture comment la loupe est générée
// La facture AC2603-0014 est id=147
// Le HTML source contient le lien loupe - cherchons la fonction qui le génère

// Chercher dans TOUS les fichiers PHP du core "dolPopupToOpenUrl" ou "popup_pdf"
print '<h3>dolPopupToOpenUrl dans le core</h3>';
print '<pre style="font-size:10px;padding:8px;overflow:auto;max-height:400px">';
$files = array_merge(
    glob(DOL_DOCUMENT_ROOT.'/core/lib/*.php'),
    glob(DOL_DOCUMENT_ROOT.'/core/class/*.php')
);
foreach ($files as $f) {
    $c = file_get_contents($f);
    if (stripos($c, 'dolPopupToOpenUrl') !== false || stripos($c, 'popup_pdf') !== false) {
        print basename($f)."\n";
        foreach (explode("\n", $c) as $n => $line) {
            if (stripos($line, 'dolPopupToOpenUrl') !== false || stripos($line, 'popup_pdf') !== false) {
                print '  '.($n+1).': '.htmlspecialchars(trim($line))."\n";
            }
        }
    }
}
print '</pre>';

// Chercher dans le JS natif
print '<h3>dolPopupToOpenUrl dans JS</h3>';
print '<pre style="font-size:10px;padding:8px;overflow:auto;max-height:400px">';
foreach (glob(DOL_DOCUMENT_ROOT.'/core/js/*.js') as $jf) {
    $c = file_get_contents($jf);
    if (stripos($c, 'dolPopupToOpenUrl') !== false) {
        print basename($jf)."\n";
        foreach (explode("\n", $c) as $n => $line) {
            if (stripos($line, 'dolPopupToOpenUrl') !== false) {
                print '  '.($n+1).': '.htmlspecialchars(trim($line))."\n";
            }
        }
    }
}
print '</pre>';

// Regarder le HTML brut d'une facture en cherchant le lien loupe
// via la fonction dol_print_file ou équivalent
print '<h3>Appels showdocuments/dol_print_file dans compta/facture</h3>';
$factfile = DOL_DOCUMENT_ROOT.'/compta/facture/card.php';
if (file_exists($factfile)) {
    $lines = file($factfile);
    print '<pre style="font-size:10px;padding:8px;overflow:auto;max-height:400px">';
    foreach ($lines as $n => $line) {
        if (stripos($line, 'showdocuments') !== false
         || stripos($line, 'dol_print_file') !== false
         || stripos($line, 'document_list') !== false) {
            print ($n+1).': '.htmlspecialchars($line);
        }
    }
    print '</pre>';
}

llxFooter();
$db->close();
