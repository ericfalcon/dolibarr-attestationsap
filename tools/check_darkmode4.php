<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check dark mode 4');

// Test : est-ce que THEME_DARKMODEENABLED=1 dans l'URL active vraiment le dark mode ?
print '<h3>Test activation dark mode</h3>';
print '<p>THEME_DARKMODEENABLED actuel = <code>'.(getDolGlobalInt('THEME_DARKMODEENABLED', 0)).'</code></p>';
print '<p>conf->global->THEME_DARKMODEENABLED = <code>'.($conf->global->THEME_DARKMODEENABLED ?? 'non défini').'</code></p>';

// Chercher dans user_param si la préférence est stockée par utilisateur
$sql = "SELECT param, value FROM ".MAIN_DB_PREFIX."user_param WHERE fk_user=".(int)$user->id;
$res = $db->query($sql);
print '<h3>Préférences utilisateur (user_param)</h3><pre>';
if ($res) while ($o = $db->fetch_object($res)) print htmlspecialchars($o->param).' = '.$o->value."\n";
print '</pre>';

// Chercher dans les constantes globales liées au thème
$sql2 = "SELECT name, value FROM ".MAIN_DB_PREFIX."const WHERE name LIKE '%THEME%' ORDER BY name";
$res2 = $db->query($sql2);
print '<h3>Constantes THEME</h3><pre>';
if ($res2) while ($o = $db->fetch_object($res2)) print htmlspecialchars($o->name).' = '.$o->value."\n";
print '</pre>';

// Liens test
print '<h3>Tests</h3>';
print '<p><a href="?THEME_DARKMODEENABLED=1" class="butAction">Activer dark mode</a></p>';
print '<p><a href="?THEME_DARKMODEENABLED=0" class="butAction">Désactiver dark mode</a></p>';

llxFooter();
$db->close();
