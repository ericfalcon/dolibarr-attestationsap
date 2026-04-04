<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check PDF Viewer');

// Chercher getAdvancedPreviewUrl dans TOUS les fichiers PHP du core
print '<h3>Où est définie getAdvancedPreviewUrl ?</h3>';
$dirs = glob(DOL_DOCUMENT_ROOT.'/core/lib/*.php');
foreach ($dirs as $f) {
    $c = file_get_contents($f);
    if (strpos($c, 'function getAdvancedPreviewUrl') !== false) {
        print '<p><strong>Trouvé dans : '.basename($f).'</strong></p>';
        $lines = file($f);
        $in = false; $cnt = 0;
        print '<pre style="font-size:10px;background:#1a2535;color:#c0d0e0;padding:8px;overflow:auto;max-height:500px">';
        foreach ($lines as $n => $line) {
            if (strpos($line, 'function getAdvancedPreviewUrl') !== false) $in = true;
            if ($in) {
                print ($n+1).': '.htmlspecialchars($line);
                $cnt++;
                if ($cnt > 80) { print '...(tronqué)'; break; }
            }
        }
        print '</pre>';
    }
}

// Test direct - appeler la fonction sur un PDF
print '<h3>Test getAdvancedPreviewUrl sur un PDF SAP</h3>';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
$attDir = DOL_DATA_ROOT.'/attestationsap';
$pdfs = glob($attDir.'/*.pdf');
if ($pdfs) {
    $bn = basename($pdfs[0]);
    $result = getAdvancedPreviewUrl('attestationsap', $bn, 1, '');
    print '<pre>'.print_r($result, true).'</pre>';
} else {
    print '<p>Aucun PDF trouvé dans '.$attDir.'</p>';
}

llxFooter();
$db->close();
