<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Fix outputdir');

// Voir quel répertoire est utilisé
$outputdir = getDolGlobalString('ATTESTATIONSAP_OUTPUTDIR', '');
print '<p>ATTESTATIONSAP_OUTPUTDIR = <code>'.dol_escape_htmltag($outputdir ?: '(vide)').'</code></p>';

// Chemin calculé par le module
if (empty($outputdir)) {
    $outputdir = !empty($conf->attestationsap->dir_output)
        ? $conf->attestationsap->dir_output
        : DOL_DATA_ROOT . '/attestationsap';
}
print '<p>Chemin calculé : <code>'.dol_escape_htmltag($outputdir).'</code></p>';
print '<p>DOL_DATA_ROOT = <code>'.DOL_DATA_ROOT.'</code></p>';
print '<p>Existe : '.(is_dir($outputdir) ? '✓ OUI' : '✗ NON').'</p>';
print '<p>Parent existe : '.(is_dir(dirname($outputdir)) ? '✓ OUI' : '✗ NON').'</p>';
print '<p>Parent writable : '.(is_writable(dirname($outputdir)) ? '✓ OUI' : '✗ NON').'</p>';

// Tenter de créer
if (!is_dir($outputdir)) {
    if (@mkdir($outputdir, 0755, true)) {
        print '<p class="ok">✓ Dossier créé avec succès : <code>'.dol_escape_htmltag($outputdir).'</code></p>';
    } else {
        print '<p class="error">✗ Impossible de créer le dossier</p>';
        
        // Essayer dans documents/attestationsap/
        $alt = DOL_DATA_ROOT . '/attestationsap';
        print '<p>Essai alternative : <code>'.dol_escape_htmltag($alt).'</code></p>';
        if (@mkdir($alt, 0755, true)) {
            print '<p class="ok">✓ Dossier alternatif créé</p>';
            dolibarr_set_const($db, 'ATTESTATIONSAP_OUTPUTDIR', $alt, 'chaine', 0, '', $conf->entity);
            print '<p class="ok">✓ Constante ATTESTATIONSAP_OUTPUTDIR mise à jour</p>';
        } else {
            print '<p class="error">✗ Impossible de créer le dossier alternatif non plus</p>';
            print '<p>Writable DOL_DATA_ROOT : '.(is_writable(DOL_DATA_ROOT) ? 'OUI' : 'NON').'</p>';
            
            // Lister les sous-dossiers de DOL_DATA_ROOT pour trouver un existant
            print '<h3>Sous-dossiers de DOL_DATA_ROOT</h3><pre>';
            foreach (glob(DOL_DATA_ROOT.'/*', GLOB_ONLYDIR) as $d) {
                $w = is_writable($d) ? '✓' : '✗';
                print $w.' '.basename($d)."\n";
            }
            print '</pre>';
        }
    }
} else {
    print '<p class="ok">✓ Dossier existe déjà</p>';
    print '<p>Writable : '.(is_writable($outputdir) ? '✓ OUI' : '✗ NON').'</p>';
}

llxFooter();
$db->close();
