<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check hauteur tableau pdf_crabe');

$f = DOL_DOCUMENT_ROOT.'/core/modules/facture/doc/pdf_crabe.modules.php';
$lines = file($f);

// Chercher la hauteur minimale du tableau et page footer
print '<h3>Hauteur minimale tableau dans pdf_crabe</h3>';
print '<pre style="font-size:10px;background:#1a2535;color:#c0d0e0;padding:8px;overflow:auto;max-height:500px">';
foreach ($lines as $n => $line) {
    if (stripos($line, 'hauteur') !== false
     || stripos($line, 'height') !== false
     || stripos($line, 'marge_basse') !== false
     || stripos($line, 'heightforinfotab') !== false
     || stripos($line, 'tab_top') !== false
     || stripos($line, 'tab_height') !== false
     || stripos($line, 'bottom') !== false) {
        print ($n+1).': '.htmlspecialchars($line);
    }
}
print '</pre>';

// Chercher aussi le texte libre {nb}
print '<h3>Texte libre INVOICE_FREE_TEXT dans conf</h3>';
print '<p>INVOICE_FREE_TEXT = <code>'.htmlspecialchars(getDolGlobalString('INVOICE_FREE_TEXT', '(vide)')).'</code></p>';
print '<p>MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS = <code>'.getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS').'</code></p>';

llxFooter();
$db->close();
