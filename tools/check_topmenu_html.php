<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check topmenu HTML');

// Chercher dans le fichier de layout du thème eldy
// le HTML de la barre du haut
$f = DOL_DOCUMENT_ROOT.'/theme/eldy/tpl/top_menu.tpl.php';
if (!file_exists($f)) $f = DOL_DOCUMENT_ROOT.'/theme/eldy/top_menu.php';
if (!file_exists($f)) {
    // Chercher tous les fichiers top_menu
    $found = glob(DOL_DOCUMENT_ROOT.'/theme/eldy/*.php');
    print '<p>Fichiers theme/eldy/*.php :</p><pre>';
    foreach ($found as $ff) print basename($ff)."\n";
    print '</pre>';
    
    // Chercher dans le menu.lib.php la structure HTML du top
    $mf = DOL_DOCUMENT_ROOT.'/core/lib/menu.lib.php';
    $mlines = file($mf);
    print '<h3>Structure HTML top bar dans menu.lib.php</h3>';
    print '<pre style="font-size:11px;background:#f5f5f5;padding:8px;overflow:auto">';
    $found_top = false;
    foreach ($mlines as $n => $line) {
        if (!$found_top && (strpos($line, 'id="tmenu"') !== false 
            || strpos($line, 'topmenu') !== false
            || strpos($line, 'id="dol_loginbar"') !== false
            || strpos($line, 'rightmenu') !== false)) {
            $found_top = true;
        }
        if ($found_top) {
            print ($n+1).': '.htmlspecialchars($line);
            if ($n > 200) { print '...(tronqué)'; break; }
        }
    }
    print '</pre>';
}

llxFooter();
$db->close();
