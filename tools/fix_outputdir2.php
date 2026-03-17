<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Fix outputdir 2');

$correct = DOL_DATA_ROOT . '/attestationsap';
print '<p>Correction de ATTESTATIONSAP_OUTPUTDIR vers : <code>'.dol_escape_htmltag($correct).'</code></p>';
print '<p>Dossier existe : '.(is_dir($correct) ? '✓ OUI' : '✗ NON').'</p>';
print '<p>Writable : '.(is_writable($correct) ? '✓ OUI' : '✗ NON').'</p>';

$res = dolibarr_set_const($db, 'ATTESTATIONSAP_OUTPUTDIR', $correct, 'chaine', 0, '', $conf->entity);
if ($res !== false) {
    print '<p class="ok">✓ Constante mise à jour</p>';
    print '<p><strong>Régénérez maintenant l\'attestation.</strong></p>';
} else {
    print '<p class="error">✗ Erreur : '.$db->lasterror().'</p>';
}

llxFooter();
$db->close();
