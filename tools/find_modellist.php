<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();

llxHeader('', 'Find modellist');

$f = DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
$lines = file($f);

// Afficher les lignes 550-846 qui construisent $modellist
print '<h3>Construction de $modellist (lignes 550-846)</h3>';
for ($n = 550; $n < 847; $n++) {
    $line = $lines[$n];
    if (stripos($line, 'modellist') !== false
        || stripos($line, 'class_path') !== false
        || stripos($line, 'classname') !== false
        || stripos($line, 'new $') !== false
        || stripos($line, '->name') !== false
        || stripos($line, 'include') !== false
        || stripos($line, 'require') !== false
        || stripos($line, '$obj') !== false
        || stripos($line, 'conf->modules') !== false
        || stripos($line, 'docmodels') !== false
        || stripos($line, 'module_parts') !== false
        || stripos($line, 'dirmodels') !== false
        || stripos($line, 'description') !== false) {
        print '<pre style="margin:1px;padding:2px;background:#f5f5f5;border-left:3px solid #2196F3">';
        print ($n+1).': '.htmlspecialchars($line);
        print '</pre>';
    }
}

// Afficher aussi en brut les lignes 700-846
print '<h3>Lignes 700-846 (brut)</h3><pre>';
for ($n = 700; $n < 847; $n++) {
    print ($n+1).': '.htmlspecialchars($lines[$n]);
}
print '</pre>';

llxFooter();
$db->close();
