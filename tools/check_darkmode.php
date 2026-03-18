<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check dark mode');

// Vérifier si Dolibarr 22 a un mode sombre natif
print '<h3>Thème actuel</h3>';
print '<p>MAIN_THEME = <code>'.getDolGlobalString('MAIN_THEME', 'eldy').'</code></p>';
print '<p>THEME_ELDY_TEXTCOLOR = <code>'.getDolGlobalString('THEME_ELDY_TEXTCOLOR', '').'</code></p>';
print '<p>user->conf->MAIN_THEME = <code>'.($user->conf->MAIN_THEME ?? 'non défini').'</code></p>';

// Chercher si une variable CSS dark mode existe
print '<h3>Variables CSS dark mode</h3>';
$cssfile = DOL_DOCUMENT_ROOT.'/theme/eldy/style.css.php';
if (file_exists($cssfile)) {
    $css = file_get_contents($cssfile);
    if (strpos($css, 'dark') !== false || strpos($css, 'prefers-color-scheme') !== false) {
        print '<p style="color:green">✓ Le thème eldy supporte le mode sombre</p>';
        // Extraire les lignes dark
        foreach (explode("\n", $css) as $n => $line) {
            if (stripos($line, 'dark') !== false || stripos($line, 'prefers-color-scheme') !== false) {
                print '<p><code>'.htmlspecialchars(trim($line)).'</code></p>';
                if ($n > 50) { print '<p>...</p>'; break; }
            }
        }
    } else {
        print '<p style="color:orange">⚠ Le thème eldy ne semble pas avoir de mode sombre natif</p>';
    }
}

// Vérifier MAIN_DARKMODE
print '<h3>Constante MAIN_DARKMODE</h3>';
print '<p>MAIN_DARKMODE = <code>'.getDolGlobalString('MAIN_DARKMODE', 'non défini').'</code></p>';

llxFooter();
$db->close();
