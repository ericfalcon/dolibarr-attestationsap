<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Diagnostic footer');

print '<h3>Valeurs de conf</h3>';
print '<p>MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS = <strong>'.getDolGlobalString('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS').'</strong> (int: '.getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS').')</p>';
print '<p>INVOICE_FREE_TEXT = <strong>'.htmlspecialchars(getDolGlobalString('INVOICE_FREE_TEXT')).'</strong></p>';
print '<p>MAIN_PDF_FREETEXT_HEIGHT = <strong>'.getDolGlobalInt('MAIN_PDF_FREETEXT_HEIGHT', 5).'</strong></p>';

// Vérifier que notre _pagefoot est bien chargé
require_once DOL_DOCUMENT_ROOT.'/custom/attestationsap/core/modules/facture/doc/pdf_facture_sap_v3.modules.php';
$r = new ReflectionClass('pdf_facture_sap_v3');
$method = $r->getMethod('_pagefoot');
print '<h3>_pagefoot défini dans</h3>';
print '<p>'.$method->getDeclaringClass()->getName().' — fichier: '.$method->getFileName().' ligne '.$method->getStartLine().'</p>';

// Lire les 5 premières lignes de la méthode
$file = file($method->getFileName());
print '<pre style="font-size:10px;background:#1a2535;color:#c0d0e0;padding:8px">';
for ($i = $method->getStartLine()-1; $i < min($method->getEndLine(), $method->getStartLine()+15); $i++) {
    print ($i+1).': '.htmlspecialchars($file[$i]);
}
print '</pre>';

llxFooter(); $db->close();
