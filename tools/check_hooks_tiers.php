<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check hooks tiers');

// 1. Voir quels hooks sont déclarés pour le module
print '<h3>Hooks déclarés dans modAttestationSap</h3>';
$f = DOL_DOCUMENT_ROOT.'/custom/attestationsap/core/modules/modAttestationSap.class.php';
$lines = file($f);
foreach ($lines as $n => $line) {
    if (stripos($line, 'hook') !== false) print '<p>'.($n+1).': <code>'.htmlspecialchars(trim($line)).'</code></p>';
}

// 2. Voir comment societe_prepare_head fonctionne
print '<h3>Méthode correcte pour ajouter un onglet tiers</h3>';
$f2 = DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
$lines2 = file($f2);
$in = false; $braces = 0;
print '<pre style="font-size:11px;background:#f5f5f5;padding:8px;overflow:auto">';
foreach ($lines2 as $n => $line) {
    if (!$in && strpos($line, 'function societe_prepare_head') !== false) $in = true;
    if ($in) {
        print ($n+1).': '.htmlspecialchars($line);
        $braces += substr_count($line,'{') - substr_count($line,'}');
        if ($braces <= 0 && $n > 0) break;
    }
}
print '</pre>';

// 3. Chercher les hooks utilisés pour les onglets tiers
print '<h3>Hooks pour onglets tiers dans Dolibarr</h3>';
$results = array();
foreach (glob(DOL_DOCUMENT_ROOT.'/custom/*/class/actions_*.php') as $f3) {
    $c = file_get_contents($f3);
    if (strpos($c, 'societe_prepare_head') !== false || strpos($c, 'addMoreTabsLinks') !== false) {
        $results[] = basename(dirname(dirname($f3))).' → '.basename($f3);
        // Extraire la méthode
        preg_match('/function\s+(addMoreTabsLinks|completeTabsHead)[^{]*{.*?return[^;]*;/s', $c, $m);
        if ($m) $results[] = '  → '.substr($m[0], 0, 100).'...';
    }
}
foreach ($results as $r) print '<p><code>'.htmlspecialchars($r).'</code></p>';
if (empty($results)) print '<p>Aucun exemple trouvé dans les modules custom</p>';

llxFooter();
$db->close();
