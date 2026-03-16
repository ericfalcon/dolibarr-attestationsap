<?php
require '../../main.inc.php';

if (!$user->admin) accessforbidden();

llxHeader('', 'Test Chemins Logo');

print load_fiche_titre('Diagnostic des chemins de logo', '', 'title_setup');

print '<h3>🔍 Informations sur les chemins</h3>';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>Variable</th><th>Valeur</th></tr>';

print '<tr class="oddeven"><td>DOL_DATA_ROOT</td><td><strong>'.DOL_DATA_ROOT.'</strong></td></tr>';
print '<tr class="oddeven"><td>DOL_DOCUMENT_ROOT</td><td>'.DOL_DOCUMENT_ROOT.'</td></tr>';
print '<tr class="oddeven"><td>$conf->mycompany->dir_output</td><td><strong>'.$conf->mycompany->dir_output.'</strong></td></tr>';

if (!empty($conf->mycompany->multidir_output[$conf->entity])) {
    print '<tr class="oddeven"><td>$conf->mycompany->multidir_output[$conf->entity]</td><td><strong>'.$conf->mycompany->multidir_output[$conf->entity].'</strong></td></tr>';
}

print '<tr class="oddeven"><td>MAIN_INFO_SOCIETE_LOGO</td><td>'.getDolGlobalString('MAIN_INFO_SOCIETE_LOGO', '<em>Non défini</em>').'</td></tr>';
print '<tr class="oddeven"><td>ATTESTATIONSAP_LOGO</td><td>'.getDolGlobalString('ATTESTATIONSAP_LOGO', '<em>Non défini</em>').'</td></tr>';

print '</table>';

print '<br><h3>📁 Recherche de logos existants</h3>';

$logodir = $conf->mycompany->dir_output;
if (!empty($conf->mycompany->multidir_output[$conf->entity])) {
    $logodir = $conf->mycompany->multidir_output[$conf->entity];
}

$search_dirs = array(
    $logodir.'/logos',
    DOL_DATA_ROOT.'/mycompany/logos',
    DOL_DOCUMENT_ROOT.'/documents/mycompany/logos'
);

foreach ($search_dirs as $dir) {
    print '<h4>Répertoire : <code>'.$dir.'</code></h4>';
    
    if (is_dir($dir)) {
        print '<p class="ok">✅ Le répertoire existe</p>';
        
        $files = scandir($dir);
        $image_files = array();
        
        foreach ($files as $file) {
            if (preg_match('/\.(png|jpg|jpeg|gif)$/i', $file)) {
                $image_files[] = $file;
            }
        }
        
        if (!empty($image_files)) {
            print '<ul>';
            foreach ($image_files as $file) {
                $filepath = $dir.'/'.$file;
                $filesize = filesize($filepath);
                $readable = is_readable($filepath) ? '✅ Lisible' : '❌ Non lisible';
                
                print '<li>';
                print '<strong>'.$file.'</strong> ('.human_filesize($filesize).') - '.$readable;
                print '<br>Chemin complet : <code>'.$filepath.'</code>';
                print '</li>';
            }
            print '</ul>';
        } else {
            print '<p class="opacitymedium">Aucune image trouvée</p>';
        }
    } else {
        print '<p class="error">❌ Le répertoire n\'existe pas</p>';
    }
    print '<br>';
}

// Fonction helper
function human_filesize($bytes, $decimals = 2) {
    $size = array('B','KB','MB','GB','TB');
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . @$size[$factor];
}

print '<h3>🧪 Test du chemin logo configuré</h3>';

if (getDolGlobalString('MAIN_INFO_SOCIETE_LOGO')) {
    $logo_file = getDolGlobalString('MAIN_INFO_SOCIETE_LOGO');
    $logo_path = $logodir.'/logos/'.$logo_file;
    
    print '<p><strong>Logo principal configuré :</strong> '.$logo_file.'</p>';
    print '<p><strong>Chemin construit :</strong> <code>'.$logo_path.'</code></p>';
    
    if (file_exists($logo_path)) {
        print '<p class="ok">✅ Le fichier existe</p>';
        print '<p>Taille : '.filesize($logo_path).' octets</p>';
        
        if (is_readable($logo_path)) {
            print '<p class="ok">✅ Le fichier est lisible</p>';
            
            // Essayer de l'afficher
            $relative = str_replace(DOL_DATA_ROOT.'/', '', $logo_path);
            $url = DOL_URL_ROOT.'/viewimage.php?modulepart=mycompany&file='.urlencode($relative);
            print '<p><img src="'.$url.'" style="max-height: 100px; border: 1px solid #ccc; padding: 5px;" alt="Logo"></p>';
        } else {
            print '<p class="error">❌ Le fichier n\'est pas lisible (problème de permissions)</p>';
        }
    } else {
        print '<p class="error">❌ Le fichier n\'existe pas à ce chemin</p>';
    }
} else {
    print '<p class="warning">⚠ Aucun logo principal configuré dans Dolibarr</p>';
    print '<p>Configurez-le dans : Accueil → Configuration → Société → Logos</p>';
}

print '<br><h3>💡 Solution recommandée</h3>';
print '<div class="info">';
print '<p>Votre logo se trouve probablement ici :</p>';
print '<p><code>'.$logodir.'/logos/logo-no-background-violet.png</code></p>';
print '<p><strong>Pour le configurer :</strong></p>';
print '<ol>';
print '<li>Allez dans Accueil → Configuration → Société → Onglet "Logos"</li>';
print '<li>Sélectionnez <code>logo-no-background-violet.png</code></li>';
print '<li>Ou renommez votre fichier en <code>logo.png</code> dans le répertoire</li>';
print '</ol>';
print '</div>';

llxFooter();
?>