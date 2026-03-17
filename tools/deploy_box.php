<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Deploy box');

$src  = DOL_DOCUMENT_ROOT.'/custom/attestationsap/core/boxes/box_attestationsap.php';
$dest = DOL_DOCUMENT_ROOT.'/core/boxes/box_attestationsap.php';

print '<h2>Déploiement du widget SAP</h2>';
print '<p>Source : <code>'.htmlspecialchars($src).'</code> — '.(file_exists($src)?'✓':'✗ ABSENT').'</p>';
print '<p>Destination : <code>'.htmlspecialchars($dest).'</code> — '.(file_exists($dest)?'déjà présent':'absent').'</p>';

if (file_exists($src)) {
    if (copy($src, $dest)) {
        chmod($dest, 0644);
        print '<p class="ok">✓ Copié avec succès !</p>';
        print '<p><strong>Maintenant :</strong> allez sur Accueil → ⚙ Configurer les widgets → le widget SAP doit apparaître.</p>';
    } else {
        print '<p class="error">✗ Échec de la copie — vérifiez les droits sur '.htmlspecialchars(dirname($dest)).'</p>';
    }
} else {
    print '<p class="error">✗ Fichier source introuvable</p>';
}

llxFooter();
$db->close();
