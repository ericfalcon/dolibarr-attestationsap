<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check attestation');

$f = DOL_DOCUMENT_ROOT.'/custom/attestationsap/core/modules/attestationsap/pdf_attestation_sap.modules.php';
print '<p>Fichier : <code>'.htmlspecialchars($f).'</code></p>';
print '<p>Existe : '.(file_exists($f)?'OUI':'NON').'</p>';

if (file_exists($f)) {
    $content = file_get_contents($f);
    print '<p>Taille : '.strlen($content).' octets</p>';
    // Chercher des marqueurs de version
    preg_match('/version.*?([0-9.]+)/i', $content, $m);
    print '<p>Version détectée : '.($m[1] ?? 'inconnue').'</p>';
    
    // Chercher "Libellé" qui est dans l'ancienne version
    print '<p>"Libellé" présent : '.(strpos($content, 'Libellé') !== false ? 'OUI (ancienne version)' : 'NON (nouvelle version)').'</p>';
    print '<p>"RÉCAPITULATIF DES FACTURES" présent : '.(strpos($content, 'RÉCAPITULATIF') !== false ? 'OUI' : 'NON').'</p>';
    print '<p>"colWidths" présent : '.(strpos($content, 'colWidths') !== false || strpos($content, 'colW') !== false ? 'OUI' : 'NON').'</p>';
}

llxFooter();
$db->close();
