<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check boxes 2');

// 1. Voir comment Dolibarr cherche les boxes des modules externes
// Le fichier clé est index.php de l'accueil ou admin/boxes.php
$f = DOL_DOCUMENT_ROOT.'/admin/boxes.php';
print '<h3>Extrait de admin/boxes.php (recherche des boxes)</h3>';
$lines = file($f);
$total = count($lines);
print '<p>Total lignes : '.$total.'</p>';

// Chercher la logique de chargement des boxes custom
print '<pre style="font-size:11px;background:#f5f5f5;padding:8px;overflow:auto">';
for ($n = 0; $n < $total; $n++) {
    $line = $lines[$n];
    if (stripos($line, 'modules_parts') !== false
        || stripos($line, 'boxes') !== false
        || stripos($line, 'custom') !== false
        || stripos($line, 'dol_buildpath') !== false
        || stripos($line, 'scandir') !== false
        || stripos($line, 'glob') !== false
        || stripos($line, 'core/boxes') !== false) {
        print ($n+1).': '.htmlspecialchars($line);
    }
}
print '</pre>';

// 2. Voir le contenu de $conf->modules_parts après activation
print '<h3>2. conf->modules_parts complet</h3>';
print '<pre>';
foreach ((array)$conf->modules_parts as $k => $v) {
    print $k.' => ';
    if (is_array($v)) print implode(', ', array_keys($v))."\n";
    else print $v."\n";
}
print '</pre>';

// 3. Est-ce que le module est bien activé ?
print '<h3>3. Module attestationsap activé ?</h3>';
print '<p>$conf->attestationsap->enabled = <code>'.(empty($conf->attestationsap->enabled)?'NON':'OUI').'</code></p>';

llxFooter();
$db->close();
