<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Find logo');

print '<h3>Recherche du logo entreprise</h3>';

$logoDir = !empty($conf->mycompany->multidir_output[$mysoc->entity])
    ? $conf->mycompany->multidir_output[$mysoc->entity]
    : (!empty($conf->mycompany->dir_output) ? $conf->mycompany->dir_output : DOL_DATA_ROOT.'/mycompany');

print '<p>logoDir = <code>'.htmlspecialchars($logoDir).'</code></p>';
print '<p>Existe : '.(is_dir($logoDir)?'✓':'✗').'</p>';

// Lister tous les fichiers dans logos/
$patterns = array(
    $logoDir.'/logos/thumbs/mycompany_small.jpg',
    $logoDir.'/logos/thumbs/mycompany_small.png',
    $logoDir.'/logos/mycompany.jpg',
    $logoDir.'/logos/mycompany.png',
    $logoDir.'/logos/*.jpg',
    $logoDir.'/logos/*.png',
);
foreach ($patterns as $p) {
    $files = glob($p);
    if ($files) foreach ($files as $f) {
        $url = DOL_URL_ROOT.'/viewimage.php?modulepart=mycompany&file='.urlencode('logos/'.basename($f));
        print '<p>✓ <code>'.htmlspecialchars($f).'</code><br>';
        print '<img src="'.htmlspecialchars($url).'" style="max-height:50px;border:1px solid #ccc;padding:4px;background:#fff"><br></p>';
    } else {
        print '<p class="opacitymedium">✗ '.htmlspecialchars($p).'</p>';
    }
}

llxFooter();
$db->close();
