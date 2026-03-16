<?php
/**
 * Simule exactement ce que Dolibarr fait lors du scan des docmodels
 */
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();

llxHeader('', 'Test scan contexte');

print '<h2>Test scan exact comme Dolibarr</h2>';

// Exactement comme Dolibarr le fait dans Form::showDocuments()
$dir = DOL_DOCUMENT_ROOT.'/custom/attestationsap/core/modules/facture/doc/';

print '<p>DOL_DOCUMENT_ROOT = <code>'.DOL_DOCUMENT_ROOT.'</code></p>';
print '<p>Chemin complet : <code>'.$dir.'</code></p>';
print '<p>pdf_crabe existe : <code>'.(file_exists(DOL_DOCUMENT_ROOT.'/core/modules/facture/doc/pdf_crabe.modules.php')?'OUI':'NON').'</code></p>';

$file = $dir.'pdf_facture_sap_v3.modules.php';
print '<p>Notre fichier : <code>'.(file_exists($file)?'OUI':'NON').'</code></p>';

// Simuler l'include comme Dolibarr : SANS require_once préalable de pdf_crabe
// (dans certains cas Dolibarr scanne les fichiers avant que pdf_crabe soit inclus)
print '<h3>Test include sans pre-chargement de pdf_crabe</h3>';

// Vérifier si pdf_crabe est déjà inclus à ce stade
$included = get_included_files();
$crabe_included = false;
foreach ($included as $f) {
    if (strpos($f, 'pdf_crabe') !== false) { $crabe_included = true; break; }
}
print '<p>pdf_crabe déjà inclus : <code>'.($crabe_included?'OUI':'NON').'</code></p>';

// Maintenant tester l'instanciation comme Dolibarr le fait
// Dolibarr utilise ce pattern dans html.form.class.php :
// $classname = 'pdf_'.$obj->nom;
// if (class_exists($classname)) { $obj2 = new $classname($db); ... }
// Sinon il affiche ": Aucun"

$classname = 'pdf_facture_sap_v3';
$already = class_exists($classname);
print '<p>Classe existe avant include : <code>'.($already?'OUI':'NON').'</code></p>';

if (!$already) {
    ob_start();
    $err = null;
    set_error_handler(function($no,$str,$f,$l) use (&$err){ $err = "[$no] $str ligne $l"; return true; });
    try {
        include_once $file;
    } catch(Throwable $e) {
        $err = $e->getMessage();
    }
    $out = ob_get_clean();
    restore_error_handler();
    
    if ($err) print '<p class="error">Erreur include : '.dol_escape_htmltag($err).'</p>';
    if ($out) print '<p>Output : '.dol_escape_htmltag($out).'</p>';
}

$after = class_exists($classname);
print '<p>Classe existe après include : <code>'.($after?'OUI':'NON').'</code></p>';

if ($after) {
    try {
        $obj2 = new $classname($db);
        print '<p class="ok">✓ Instanciation OK</p>';
        print '<p>name = <code>'.dol_escape_htmltag($obj2->name).'</code></p>';
        print '<p>version = <code>'.dol_escape_htmltag($obj2->version).'</code></p>';
        
        // C'est exactement la comparaison que fait Dolibarr
        // Si $obj2->name === $nom_en_base → affiche la description
        // Sinon → "Aucun"
        $nom_en_base = 'facture_sap_v3';
        $match = ($obj2->name === $nom_en_base);
        print '<p>name == nom_en_base : <code>'.($match?'OUI ✓':'NON ✗ — VOILÀ LE PROBLÈME').'</code></p>';
        if (!$match) {
            print '<p class="error">name=<code>'.dol_escape_htmltag($obj2->name).'</code> vs base=<code>'.dol_escape_htmltag($nom_en_base).'</code></p>';
        }
    } catch(Throwable $e) {
        print '<p class="error">Exception instanciation : '.dol_escape_htmltag($e->getMessage()).'</p>';
    }
} else {
    print '<p class="error">→ CLASSE INTROUVABLE après include : Dolibarr affichera "Aucun"</p>';
}

llxFooter();
$db->close();
