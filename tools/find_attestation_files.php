<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Find attestation files');

// Chercher tous les fichiers pdf_attestation_sap sur le serveur
$found = array();
$dirs = array(
    DOL_DOCUMENT_ROOT.'/custom/attestationsap/',
    DOL_DOCUMENT_ROOT.'/custom/',
    DOL_DOCUMENT_ROOT.'/core/modules/',
);
foreach ($dirs as $dir) {
    if (!is_dir($dir)) continue;
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iter as $file) {
        if (strpos($file->getFilename(), 'attestation_sap') !== false && $file->getExtension() === 'php') {
            $found[] = $file->getPathname();
        }
    }
}

print '<h3>Fichiers pdf_attestation_sap trouvés</h3>';
foreach ($found as $f) {
    $size = filesize($f);
    $content = file_get_contents($f);
    $hasLibelle = strpos($content, 'Libellé') !== false;
    $hasRecap   = strpos($content, 'RÉCAPITULATIF') !== false;
    print '<p'.($hasLibelle?' style="background:#ffe0e0"':'').'>'.htmlspecialchars($f).' ('.$size.' o) — '.
          ($hasLibelle ? '⚠ ANCIENNE version (Libellé)' : '✓ Nouvelle version').'</p>';
}

// Aussi vérifier quel fichier est utilisé lors de la génération
print '<h3>Classe utilisée lors de la génération</h3>';
$f2 = DOL_DOCUMENT_ROOT.'/custom/attestationsap/index.php';
if (file_exists($f2)) {
    $lines = file($f2);
    foreach ($lines as $n => $line) {
        if (stripos($line, 'pdf_attestation_sap') !== false || stripos($line, 'write_file') !== false || stripos($line, 'require') !== false) {
            print '<p>Ligne '.($n+1).': <code>'.htmlspecialchars(trim($line)).'</code></p>';
        }
    }
}

llxFooter();
$db->close();
