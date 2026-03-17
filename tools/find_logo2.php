<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Find logo 2');

// Chercher le logo tel que Dolibarr le connaît
print '<h3>Logo configuré dans mysoc</h3>';
print '<p>mysoc->logo = <code>'.htmlspecialchars($mysoc->logo ?? 'vide').'</code></p>';
print '<p>mysoc->logo_squarred = <code>'.htmlspecialchars($mysoc->logo_squarred ?? 'vide').'</code></p>';

$logoDir = !empty($conf->mycompany->multidir_output[$mysoc->entity])
    ? $conf->mycompany->multidir_output[$mysoc->entity]
    : DOL_DATA_ROOT.'/mycompany';

if (!empty($mysoc->logo)) {
    $logoPath = $logoDir.'/logos/'.$mysoc->logo;
    print '<p>Chemin complet : <code>'.htmlspecialchars($logoPath).'</code></p>';
    print '<p>Existe : '.(file_exists($logoPath)?'✓ OUI':'✗ NON').'</p>';
    if (file_exists($logoPath)) {
        $url = DOL_URL_ROOT.'/viewimage.php?modulepart=mycompany&file='.urlencode('logos/'.$mysoc->logo);
        print '<img src="'.htmlspecialchars($url).'" style="max-height:80px;border:1px solid #ccc;padding:6px;background:#fff">';
    }
}

// Aussi chercher le thumb
if (!empty($mysoc->logo)) {
    $ext = pathinfo($mysoc->logo, PATHINFO_EXTENSION);
    $base = pathinfo($mysoc->logo, PATHINFO_FILENAME);
    $thumbPath = $logoDir.'/logos/thumbs/'.$base.'_small.'.$ext;
    print '<p>Thumb : <code>'.htmlspecialchars($thumbPath).'</code> — '.(file_exists($thumbPath)?'✓':'✗').'</p>';
}

llxFooter();
$db->close();
