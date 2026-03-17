<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check PDF size');

$dir = getDolGlobalString('ATTESTATIONSAP_OUTPUTDIR', '');
if (empty($dir) || strpos($dir, DOL_DATA_ROOT) !== 0) {
    $dir = DOL_DATA_ROOT.'/attestationsap';
}
if (!is_dir($dir)) { print '<p>Dossier introuvable</p>'; llxFooter(); exit; }

$files = glob($dir.'/*.pdf');
rsort($files);

print '<h3>Fichiers PDF générés</h3>';
foreach (array_slice($files, 0, 5) as $f) {
    $size = filesize($f);
    print '<p><code>'.basename($f).'</code> — '.$size.' octets</p>';
    
    // Lire les premières lignes pour vérifier le format
    $handle = fopen($f, 'r');
    $header = fread($handle, 200);
    fclose($handle);
    
    // Chercher MediaBox dans le PDF (format de page)
    if (preg_match('/MediaBox\s*\[([^\]]+)\]/', $header . file_get_contents($f), $m)) {
        print '<p>→ MediaBox : ['.$m[1].']</p>';
        $parts = explode(' ', trim($m[1]));
        if (count($parts) >= 4) {
            $w = round((float)$parts[2] / 2.8346, 1);
            $h = round((float)$parts[3] / 2.8346, 1);
            print '<p>→ Dimensions : '.$w.' × '.$h.' mm';
            if ($w >= 209 && $w <= 211 && $h >= 296 && $h <= 298) {
                print ' ✓ A4</p>';
            } else {
                print ' ⚠ PAS A4 (attendu 210×297)</p>';
            }
        }
    }
}

llxFooter();
$db->close();
