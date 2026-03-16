<?php
/**
 * htdocs/custom/attestationsap/tools/fix_models.php
 *
 * PROBLÈMES CORRIGÉS :
 *  - "facture_sap_v3:aucun" → le champ nom en base contient un suffixe
 *  - "facture_sap" orphelin → entrée sans fichier PHP correspondant
 *  - Constantes FACTURE_ADDON_PDF / PROPALE_ADDON_PDF pas définies
 *
 * Accès : https://votre-dolibarr/custom/attestationsap/tools/fix_models.php
 */

require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();

$entity = (int)$conf->entity;

llxHeader('', 'Fix modèles SAP');
print load_fiche_titre('Correction des modèles PDF SAP', '', 'setup');

// -----------------------------------------------------------------------
// 1. NETTOYER la table document_model
// -----------------------------------------------------------------------
print '<h3>1. Table '.MAIN_DB_PREFIX.'document_model</h3>';

// Noms valides attendus
$valid_invoice = array('facture_sap_v3');
$valid_propal  = array('devis_sap', 'devis_sap_v2');

// Lire toutes les lignes invoice et propal
$sql = "SELECT rowid, nom, type FROM " . MAIN_DB_PREFIX . "document_model
        WHERE entity = " . $entity . " AND type IN ('invoice','propal')
        ORDER BY type, nom";
$resql = $db->query($sql);

$to_delete  = array();  // rowid => nom (orphelins + doublons)
$to_update  = array();  // rowid => array('old'=>, 'new'=>)
$seen       = array();  // type:nom_normalisé => rowid (premier trouvé)

if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $raw  = trim((string)$obj->nom);
        // Normaliser : retirer tout ce qui suit ':'
        $norm = trim(preg_replace('/\s*:.*$/', '', $raw));
        $key  = $obj->type . ':' . $norm;

        // Vérifier si c'est un modèle connu
        $valid = ($obj->type === 'invoice') ? $valid_invoice : $valid_propal;
        $is_valid = in_array($norm, $valid);

        if (!$is_valid) {
            // Modèle inconnu/orphelin → supprimer
            $to_delete[$obj->rowid] = $raw;
        } elseif ($raw !== $norm) {
            // Nom avec suffixe → normaliser
            $to_update[$obj->rowid] = array('old' => $raw, 'new' => $norm, 'type' => $obj->type);
            $seen[$key] = $obj->rowid;
        } elseif (isset($seen[$key])) {
            // Doublon → supprimer le second
            $to_delete[$obj->rowid] = $raw . ' (doublon)';
        } else {
            $seen[$key] = $obj->rowid;
        }
    }
    $db->free($resql);
}

// Appliquer les suppressions
foreach ($to_delete as $rowid => $nom) {
    $sql = "DELETE FROM " . MAIN_DB_PREFIX . "document_model WHERE rowid = " . (int)$rowid;
    if ($db->query($sql)) {
        print '<p class="ok">✓ Supprimé orphelin : <code>' . dol_escape_htmltag($nom) . '</code> (rowid=' . $rowid . ')</p>';
    } else {
        print '<p class="error">✗ Erreur suppression rowid=' . $rowid . ' : ' . $db->lasterror() . '</p>';
    }
}

// Appliquer les normalisations
foreach ($to_update as $rowid => $info) {
    $sql = "UPDATE " . MAIN_DB_PREFIX . "document_model SET nom = '" . $db->escape($info['new']) . "'
            WHERE rowid = " . (int)$rowid;
    if ($db->query($sql)) {
        print '<p class="ok">✓ Normalisé : <code>' . dol_escape_htmltag($info['old']) . '</code> → <code>' . dol_escape_htmltag($info['new']) . '</code></p>';
    } else {
        print '<p class="error">✗ Erreur normalisation rowid=' . $rowid . ' : ' . $db->lasterror() . '</p>';
    }
}

// Insérer les modèles manquants
foreach (array('invoice' => $valid_invoice, 'propal' => $valid_propal) as $type => $models) {
    foreach ($models as $nom) {
        $key = $type . ':' . $nom;
        if (!isset($seen[$key])) {
            $sql = "INSERT INTO " . MAIN_DB_PREFIX . "document_model (nom, entity, type, libelle, description)
                    VALUES ('" . $db->escape($nom) . "', " . $entity . ", '" . $db->escape($type) . "',
                            '" . $db->escape($nom) . "', 'Modèle SAP - ajout auto')";
            if ($db->query($sql)) {
                print '<p class="ok">✓ Inséré modèle manquant : <code>' . dol_escape_htmltag($nom) . '</code> (type=' . $type . ')</p>';
            } else {
                print '<p class="error">✗ Erreur insertion <code>' . $nom . '</code> : ' . $db->lasterror() . '</p>';
            }
        }
    }
}

if (empty($to_delete) && empty($to_update)) {
    print '<p class="ok">✓ Table déjà propre.</p>';
}

// -----------------------------------------------------------------------
// 2. CONSTANTES PDF PAR DÉFAUT
// -----------------------------------------------------------------------
print '<h3>2. Constantes PDF par défaut</h3>';

$consts = array(
    'FACTURE_ADDON_PDF'  => 'facture_sap_v3',
    'PROPALE_ADDON_PDF'  => 'devis_sap_v2',
    'PROPALE_ADDON_ODT'  => '',   // vider pour ne pas écraser le PDF
);

foreach ($consts as $name => $value) {
    $current = getDolGlobalString($name, '__NOT_SET__');
    if ($current === $value) {
        print '<p class="ok">✓ ' . $name . ' = <code>' . dol_escape_htmltag($value ?: '(vide)') . '</code> (déjà correct)</p>';
        continue;
    }
    if ($value === '') {
        $res = dolibarr_del_const($db, $name, $entity);
        $action = 'vidée';
    } else {
        $res = dolibarr_set_const($db, $name, $value, 'chaine', 0, '', $entity);
        $action = 'définie à <code>' . dol_escape_htmltag($value) . '</code>';
    }
    if ($res > 0 || $res === 1) {
        print '<p class="ok">✓ ' . $name . ' ' . $action . ' (était : <code>' . dol_escape_htmltag($current === '__NOT_SET__' ? 'non définie' : $current) . '</code>)</p>';
    } else {
        print '<p class="error">✗ Erreur pour ' . $name . '</p>';
    }
}

// -----------------------------------------------------------------------
// 3. ÉTAT FINAL
// -----------------------------------------------------------------------
print '<h3>3. État final de la table</h3>';

$sql = "SELECT rowid, nom, type, libelle FROM " . MAIN_DB_PREFIX . "document_model
        WHERE entity = " . $entity . " AND type IN ('invoice','propal')
        ORDER BY type, nom";
$resql = $db->query($sql);
if ($resql) {
    print '<table class="noborder centpercent"><tr class="liste_titre"><td>rowid</td><td>type</td><td>nom</td><td>libelle</td></tr>';
    while ($obj = $db->fetch_object($resql)) {
        print '<tr class="oddeven"><td>' . $obj->rowid . '</td><td>' . $obj->type . '</td>'
            . '<td><code>' . dol_escape_htmltag($obj->nom) . '</code></td>'
            . '<td>' . dol_escape_htmltag($obj->libelle) . '</td></tr>';
    }
    print '</table>';
    $db->free($resql);
}

// -----------------------------------------------------------------------
// 4. INSTRUCTIONS
// -----------------------------------------------------------------------
print '<br><div class="info">';
print '<strong>Après ce fix :</strong><br>';
print '1. <a href="' . DOL_URL_ROOT . '/admin/tools.php" target="_blank">Administration → Outils → Vider les caches</a><br>';
print '2. Recharger la page de votre facture (Ctrl+F5)<br>';
print '3. Le modèle <code>facture_sap_v3</code> doit apparaître dans la liste PDF sans ":aucun"<br>';
print '4. Si le modèle n\'apparaît toujours pas : désactiver puis réactiver le module AttestationSAP';
print '</div>';

llxFooter();
$db->close();
