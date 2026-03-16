<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();

llxHeader('', 'Trouver Aucun');

// Chercher où Dolibarr affiche "Aucun" dans les sources
$files = array(
    DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php',
    DOL_DOCUMENT_ROOT.'/core/lib/document.lib.php',
    DOL_DOCUMENT_ROOT.'/compta/facture/card.php',
);

foreach ($files as $f) {
    if (!file_exists($f)) { print '<p>Absent: '.basename($f).'</p>'; continue; }
    $lines = file($f);
    foreach ($lines as $n => $line) {
        // Chercher le mot "Aucun" ou la logique qui l'affiche
        if (stripos($line, 'aucun') !== false || stripos($line, 'nomodel') !== false 
            || (stripos($line, 'none') !== false && stripos($line, 'pdf') !== false)) {
            print '<p><strong>'.basename($f).'</strong> ligne '.($n+1).': <code>'.dol_escape_htmltag(trim($line)).'</code></p>';
        }
    }
}

// Aussi chercher dans les modules facture
$mf = DOL_DOCUMENT_ROOT.'/core/modules/facture/modules_facture.php';
if (file_exists($mf)) {
    $lines = file($mf);
    foreach ($lines as $n => $line) {
        if (stripos($line, 'aucun') !== false || stripos($line, 'getlist') !== false
            || stripos($line, 'showdoc') !== false || stripos($line, 'class') !== false) {
            if ($n < 100) // seulement le début
            print '<p><strong>modules_facture.php</strong> ligne '.($n+1).': <code>'.dol_escape_htmltag(trim($line)).'</code></p>';
        }
    }
}

llxFooter();
$db->close();
