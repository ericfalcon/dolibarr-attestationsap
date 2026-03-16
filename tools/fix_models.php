
<?php
/**
 * htdocs/custom/attestationsap/tools/fix_models.php
 * Normalisation des modèles par défaut (propal/invoice) pour forcer l'usage des PDF SAP.
 * - Met PROPALE_ADDON_ODT = '' (désactive l'ODT par défaut).
 * - Met PROPALE_ADDON_PDF = 'devis_sap' et FACTURE_ADDON_PDF = 'facture_sap_v3'.
 * - Nettoie llx_document_model (retire suffixes ': ...', filtre template_*).
 * A lancer une seule fois puis vider les caches.
 */

require_once dirname(__FILE__).'/../../../main.inc.php';

if (!$user->admin) accessforbidden();

$langs->load("admin");
print '<h2>Fix modèles PDF par défaut (SAP)</h2>';

$entity = (int) $conf->entity;
$ok = true;
$logs = array();

/**
 * Retire un suffixe éventuel après ':' dans un nom de modèle (ex. 'facture_sap_v3: Aucun' -> 'facture_sap_v3')
 */
function normalize_model_name($name) {
    $name = preg_replace('/:.+$/', '', (string) $name);
    return trim($name);
}

/**
 * Nettoie les entrées de llx_document_model pour un type (propal|invoice) :
 * - supprime/ignore les 'template_*' (ODT) si on veut un PDF par défaut
 * - normalise le champ nom en retirant les suffixes
 * - insère le modèle s'il n'existe pas
 */
function fix_document_models($db, $type, $entity, $wanted_models, &$logs) {
    $type_sql = $db->escape($type);
    $sql = "SELECT rowid, nom FROM ".MAIN_DB_PREFIX."document_model
            WHERE entity = ".((int)$entity)." AND type = '".$type_sql."'";
    $resql = $db->query($sql);
    if (!$resql) {
        $logs[] = "Erreur SQL lecture llx_document_model (".$type."): ".$db->lasterror();
        return false;
    }

    $existing = array();  // nom normalisé => rowid
    while ($obj = $db->fetch_object($resql)) {
        $raw = (string) $obj->nom;
        $norm = normalize_model_name($raw);

        // Si c'est un ODT (template_*), on le garde en BDD mais on ne le mettra pas par défaut
        if (strpos($raw, 'template_') === 0) {
            $logs[] = "Détecté ODT pour ".$type." : ".$raw." (ignoré pour le défaut PDF)";
            continue;
        }

        // Mettre à jour la ligne si le nom a un suffixe
        if ($norm !== $raw) {
            $upd = "UPDATE ".MAIN_DB_PREFIX."document_model SET nom='".$db->escape($norm)."' WHERE rowid=".((int)$obj->rowid);
            if ($db->query($upd)) {
                $logs[] = "Normalisé modèle ".$type." : '".$raw."' => '".$norm."'";
            } else {
                $logs[] = "Erreur SQL update normalisation ".$type." '".$raw."' : ".$db->lasterror();
            }
        }
        $existing[$norm] = (int) $obj->rowid;
    }
    $db->free($resql);

    // Insérer les modèles voulus s'ils n'existent pas
    foreach ($wanted_models as $m) {
        if (!isset($existing[$m])) {
            $ins = "INSERT INTO ".MAIN_DB_PREFIX."document_model (nom, entity, type, libelle, description)
                    VALUES ('".$db->escape($m)."', ".((int)$entity).", '".$type_sql."', '".$db->escape($m)."', 'Ajout auto fix_models')";
            if ($db->query($ins)) {
                $logs[] = "Inséré modèle manquant ".$type." : ".$m;
            } else {
                $logs[] = "Erreur SQL insert ".$type." ".$m." : ".$db->lasterror();
                $ok = false;
            }
        }
    }
    return true;
}

// 1) Nettoyage des modèles PROPAL (devis)
$wanted_propal = array('devis_sap');    // ton modèle PDF devis
fix_document_models($db, 'propal', $entity, $wanted_propal, $logs);

// 2) Nettoyage des modèles INVOICE (facture)
$wanted_invoice = array('facture_sap_v3');  // ton modèle PDF facture
fix_document_models($db, 'invoice', $entity, $wanted_invoice, $logs);

// 3) Poser les constantes par défaut (PDF) et désactiver l'ODT pour Propal
$set1 = dolibarr_set_const($db, 'PROPALE_ADDON_PDF',  'devis_sap',     'chaine', 0, '', $entity);
$set2 = dolibarr_set_const($db, 'FACTURE_ADDON_PDF',  'facture_sap_v3','chaine', 0, '', $entity);
$set3 = dolibarr_set_const($db, 'PROPALE_ADDON_ODT',  '',               'chaine', 0, '', $entity); // désactive ODT défaut

$logs[] = "PROPALE_ADDON_PDF = devis_sap (res=".$set1.")";
$logs[] = "FACTURE_ADDON_PDF = facture_sap_v3 (res=".$set2.")";
$logs[] = "PROPALE_ADDON_ODT = '' (res=".$set3.")";

// Affichage
print '<div class="info">';
print '<p><strong>Terminé.</strong> Maintenant : <em>Outils d’administration → Vider/Reconstruire caches</em>.</p>';
print '<ul>';
foreach ($logs as $l) print '<li>'.dol_escape_htmltag($l).'</li>';
print '</ul>';
print '</div>';

llxFooter();
$db->close();
