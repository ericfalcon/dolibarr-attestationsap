<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check dark mode 2');

// Lire le code autour de THEME_DARKMODEENABLED dans style.css.php
$cssfile = DOL_DOCUMENT_ROOT.'/theme/eldy/style.css.php';
$lines = file($cssfile);

print '<h3>Code autour de THEME_DARKMODEENABLED</h3>';
print '<pre style="font-size:11px;background:#f5f5f5;padding:8px;overflow:auto">';
foreach ($lines as $n => $line) {
    if (stripos($line, 'DARKMODEENABLED') !== false
     || stripos($line, 'dark') !== false
     || stripos($line, 'MAIN_DARKMODE') !== false) {
        print ($n+1).': '.htmlspecialchars($line);
        // Afficher aussi les 3 lignes suivantes pour le contexte
        for ($i=1; $i<=3; $i++) {
            if (isset($lines[$n+$i])) print ($n+$i+1).':   '.htmlspecialchars($lines[$n+$i]);
        }
        print "\n";
    }
}
print '</pre>';

// Chercher dans les préférences utilisateur
print '<h3>Préférences dark mode utilisateur</h3>';
$sql = "SELECT param, value FROM ".MAIN_DB_PREFIX."user_param WHERE fk_user=".(int)$user->id." AND param LIKE '%DARK%'";
$res = $db->query($sql);
if ($res) {
    while ($o = $db->fetch_object($res)) print '<p><code>'.htmlspecialchars($o->param).' = '.$o->value.'</code></p>';
    if ($db->num_rows($res) === 0) print '<p>Aucune préférence dark mode trouvée</p>';
}

// Chercher dans les constantes globales
print '<h3>Constantes globales dark</h3>';
$sql2 = "SELECT name, value FROM ".MAIN_DB_PREFIX."const WHERE name LIKE '%DARK%'";
$res2 = $db->query($sql2);
if ($res2) {
    while ($o = $db->fetch_object($res2)) print '<p><code>'.htmlspecialchars($o->name).' = '.$o->value.'</code></p>';
    if ($db->num_rows($res2) === 0) print '<p>Aucune constante dark mode trouvée</p>';
}

llxFooter();
$db->close();
