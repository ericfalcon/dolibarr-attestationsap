<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'test dol_include');

$relsourcefile = '/attestationsap/core/boxes/box_attestationsap.php';

// Ce que fait dol_include_once
$path0 = dol_buildpath($relsourcefile, 0);
$path1 = dol_buildpath($relsourcefile, 1);

print '<h3>dol_buildpath results</h3>';
print '<p>dol_buildpath(..., 0) = <code>'.htmlspecialchars($path0).'</code> — '.(file_exists($path0)?'✓ EXISTS':'✗ ABSENT').'</p>';
print '<p>dol_buildpath(..., 1) = <code>'.htmlspecialchars($path1).'</code></p>';

// Chemin réel du fichier
$real = DOL_DOCUMENT_ROOT.'/custom/attestationsap/core/boxes/box_attestationsap.php';
print '<p>Chemin custom direct = <code>'.htmlspecialchars($real).'</code> — '.(file_exists($real)?'✓ EXISTS':'✗ ABSENT').'</p>';

// Test dol_include_once
print '<h3>Test dol_include_once</h3>';
$result = dol_include_once($relsourcefile);
print '<p>dol_include_once retourne : <code>'.var_export($result, true).'</code></p>';
print '<p>Classe box_attestationsap existe : <code>'.(class_exists('box_attestationsap')?'OUI':'NON').'</code></p>';

// DOL_DOCUMENT_ROOT et paths customs
print '<h3>Chemins Dolibarr</h3>';
print '<p>DOL_DOCUMENT_ROOT = <code>'.DOL_DOCUMENT_ROOT.'</code></p>';
if (!empty($conf->file->dol_document_root)) {
    print '<pre>'; var_dump($conf->file->dol_document_root); print '</pre>';
}

llxFooter();
$db->close();
