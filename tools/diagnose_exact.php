<?php
/**
 * Diagnostic exact : ce que Dolibarr voit pour les modèles de facture
 */
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();

$entity = (int)$conf->entity;

llxHeader('', 'Diagnostic exact modèles');
print '<h2>Diagnostic exact — modèles invoice</h2>';

// ---- 1. Contenu BRUT de llx_document_model ----
print '<h3>1. Contenu brut de llx_document_model (type=invoice, toutes entités)</h3>';
$sql = "SELECT rowid, nom, type, entity, libelle FROM ".MAIN_DB_PREFIX."document_model
        WHERE type = 'invoice' ORDER BY entity, rowid";
$resql = $db->query($sql);
if ($resql) {
    print '<table class="noborder"><tr class="liste_titre"><td>rowid</td><td>entity</td><td>nom</td><td>hex(nom)</td><td>libelle</td></tr>';
    $count = 0;
    while ($obj = $db->fetch_object($resql)) {
        $count++;
        $hex = bin2hex($obj->nom);
        $bg = ($obj->entity == $entity) ? 'background:#ffffcc' : '';
        print '<tr class="oddeven" style="'.$bg.'"><td>'.$obj->rowid.'</td>'
            .'<td>'.$obj->entity.($obj->entity==$entity?' ← vous':'').'</td>'
            .'<td><code>'.dol_escape_htmltag($obj->nom).'</code></td>'
            .'<td><code style="font-size:10px">'.dol_escape_htmltag($hex).'</code></td>'
            .'<td>'.dol_escape_htmltag($obj->libelle).'</td></tr>';
    }
    print '</table><p>Total : '.$count.' ligne(s)</p>';
    $db->free($resql);
} else {
    print '<p class="error">Erreur SQL : '.$db->lasterror().'</p>';
}

// ---- 2. Constantes pertinentes ----
print '<h3>2. Constantes PDF</h3>';
$consts = array('FACTURE_ADDON_PDF','FACTURE_ADDON_ODT','PROPALE_ADDON_PDF','PROPALE_ADDON_ODT',
                'ATTESTATIONSAP_FACTURE_MODEL_NAME');
foreach ($consts as $c) {
    $val = getDolGlobalString($c, '__NON_DEFINIE__');
    print '<p><code>'.$c.'</code> = <code>'.dol_escape_htmltag($val).'</code></p>';
}

// ---- 3. Fichiers PHP présents ----
print '<h3>3. Fichiers dans core/modules/facture/doc/</h3>';
$dir = DOL_DOCUMENT_ROOT.'/custom/attestationsap/core/modules/facture/doc/';
print '<p>Chemin : <code>'.dol_escape_htmltag($dir).'</code> — existe : '.(is_dir($dir)?'OUI':'NON').'</p>';
if (is_dir($dir)) {
    $files = glob($dir.'pdf_*.modules.php');
    if ($files) {
        foreach ($files as $f) {
            print '<p>→ <code>'.basename($f).'</code> — lisible : '.(is_readable($f)?'OUI':'NON').'</p>';
        }
    } else {
        print '<p class="error">Aucun fichier trouvé</p>';
    }
}

// ---- 4. Include + instanciation ----
print '<h3>4. Test include + instanciation pdf_facture_sap_v3</h3>';
$file = $dir.'pdf_facture_sap_v3.modules.php';
if (!is_readable($file)) {
    print '<p class="error">Fichier non lisible : '.dol_escape_htmltag($file).'</p>';
} else {
    $errors = array();
    set_error_handler(function($no,$str,$f,$l) use (&$errors){ $errors[] = "[$no] $str (ligne $l)"; return true; });
    ob_start();
    try {
        if (!class_exists('pdf_facture_sap_v3')) require_once $file;
        ob_get_clean();
        restore_error_handler();
        if (class_exists('pdf_facture_sap_v3')) {
            print '<p class="ok">✓ Classe chargée</p>';
            $o = new pdf_facture_sap_v3($db);
            print '<p>name = <code>'.dol_escape_htmltag($o->name).'</code></p>';
            print '<p>version = <code>'.dol_escape_htmltag($o->version).'</code></p>';
            print '<p>description = <code>'.dol_escape_htmltag($o->description).'</code></p>';
        } else {
            print '<p class="error">Classe introuvable après include</p>';
        }
    } catch (Throwable $e) {
        ob_get_clean();
        restore_error_handler();
        print '<p class="error">Exception : '.dol_escape_htmltag($e->getMessage()).' — '
              .dol_escape_htmltag($e->getFile()).':'.$e->getLine().'</p>';
    }
    foreach ($errors as $err) {
        print '<p class="error">PHP Warning : '.dol_escape_htmltag($err).'</p>';
    }
}

// ---- 5. Comment Dolibarr construit le select (simulation) ----
print '<h3>5. Simulation du select modèles Dolibarr</h3>';
print '<p>Dolibarr lit llx_document_model puis cherche le fichier PHP correspondant.</p>';
$sql = "SELECT nom FROM ".MAIN_DB_PREFIX."document_model
        WHERE entity = ".$entity." AND type = 'invoice' ORDER BY nom";
$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $nom = trim($obj->nom);
        // Dolibarr cherche dans tous les dossiers docmodels déclarés
        $found = false;
        $dirs_to_check = array(
            DOL_DOCUMENT_ROOT.'/core/modules/facture/doc/',
            DOL_DOCUMENT_ROOT.'/custom/attestationsap/core/modules/facture/doc/',
        );
        foreach ($dirs_to_check as $d) {
            $f = $d.'pdf_'.$nom.'.modules.php';
            if (is_readable($f)) { $found = true; break; }
        }
        $status = $found ? '✓ fichier trouvé' : '✗ fichier INTROUVABLE → affiche ":aucun"';
        $color  = $found ? 'green' : 'red';
        print '<p style="color:'.$color.'"><code>'.dol_escape_htmltag($nom).'</code> → '.$status.'</p>';
    }
    $db->free($resql);
}

llxFooter();
$db->close();
