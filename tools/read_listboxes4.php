<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'read listBoxes4');

// Chercher le fichier qui contient listBoxes
$candidates = array(
    DOL_DOCUMENT_ROOT.'/core/class/infobox.class.php',
    DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php',
    DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php',
);
foreach ($candidates as $f) {
    if (!file_exists($f)) { print '<p>Absent: '.basename($f).'</p>'; continue; }
    $content = file_get_contents($f);
    if (strpos($content, 'function listBoxes') !== false) {
        print '<p class="ok">✓ Trouvé dans : <code>'.htmlspecialchars($f).'</code></p>';
        
        $lines = file($f);
        $start = 0;
        foreach ($lines as $n => $line) {
            if (strpos($line, 'function listBoxes') !== false) { $start = $n; break; }
        }
        print '<p>Ligne : '.($start+1).'</p>';
        print '<pre style="font-size:11px;background:#f5f5f5;padding:8px;overflow:auto">';
        for ($i = $start; $i < min($start+100, count($lines)); $i++) {
            print ($i+1).': '.htmlspecialchars($lines[$i]);
        }
        print '</pre>';
    }
}

// Chercher dans tous les fichiers core/class/
foreach (glob(DOL_DOCUMENT_ROOT.'/core/class/*.php') as $f) {
    $content = file_get_contents($f);
    if (strpos($content, 'function listBoxes') !== false) {
        print '<p class="ok">✓ Aussi dans : <code>'.htmlspecialchars(basename($f)).'</code></p>';
    }
}

llxFooter();
$db->close();
