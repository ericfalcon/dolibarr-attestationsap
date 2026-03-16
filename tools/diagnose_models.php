
<?php
/**
 * htdocs/custom/attestationsap/tools/diagnose_models.php
 * Affiche les constantes et les modèles disponibles (propal/invoice) pour comprendre
 * pourquoi l'UI reste sur template_proposal.odt / facture_sap_v3: Aucun.
 *
 * Ceci N'ÉCRIT RIEN en base. À exécuter en admin.
 */

require_once dirname(__FILE__).'/../../../main.inc.php';
if (!$user->admin) accessforbidden();

$langs->load("admin");

function h($s){ return dol_escape_htmltag($s); }
function normalize($name){ return trim(preg_replace('/:.+$/', '', (string)$name)); }

$entity = (int) $conf->entity;

// Lire constantes
$const_propal_pdf  = getDolGlobalString('PROPALE_ADDON_PDF', '');
$const_propal_odt  = getDolGlobalString('PROPALE_ADDON_ODT', '');
$const_invoice_pdf = getDolGlobalString('FACTURE_ADDON_PDF', '');

// Lister modèles bruts
$models_raw = array('propal'=>array(), 'invoice'=>array());
foreach (array('propal','invoice') as $type) {
    $sql = "SELECT rowid, nom, libelle, description
            FROM ".MAIN_DB_PREFIX."document_model
            WHERE entity=".$entity." AND type='".$db->escape($type)."'
            ORDER BY nom";
    $resql = $db->query($sql);
    if ($resql){
        while($o = $db->fetch_object($resql)){
            $models_raw[$type][] = $o;
        }
        $db->free($resql);
    } else {
        $models_raw[$type] = array();
        $error = $db->lasterror();
    }
}

// Préparer vues normalisées (value = à gauche du ':')
$norm_propal  = array_map(function($o){ return array('rowid'=>$o->rowid,'nom'=>normalize($o->nom)); }, $models_raw['propal']);
$norm_invoice = array_map(function($o){ return array('rowid'=>$o->rowid,'nom'=>normalize($o->nom)); }, $models_raw['invoice']);

// Détecter présence d'ODT "template_*"
$has_template_proposal = false;
foreach ($models_raw['propal'] as $o) {
    if (strpos((string)$o->nom, 'template_') === 0) { $has_template_proposal = true; break; }
}

// Page
llxHeader('', 'Diagnostic modèles (SAP)');

echo '<h2>Diagnostic modèles PDF/ODT (entité '.h($entity).')</h2>';

echo '<h3>Constantes chargées</h3>';
echo '<ul>';
echo '<li>PROPALE_ADDON_PDF = <code>'.h($const_propal_pdf).'</code></li>';
echo '<li>PROPALE_ADDON_ODT = <code>'.h($const_propal_odt).'</code></li>';
echo '<li>FACTURE_ADDON_PDF = <code>'.h($const_invoice_pdf).'</code></li>';
echo '</ul>';

echo '<div class="info">';
echo '<p><strong>Règle de priorité côté Devis :</strong> si <code>PROPALE_ADDON_ODT</code> est non vide, le modèle ODT est prioritaire sur le modèle PDF (<code>PROPALE_ADDON_PDF</code>).</p>';
echo '<p>Donc, pour forcer le PDF, mettre <code>PROPALE_ADDON_ODT</code> à vide.</p>';
echo '</div>';

echo '<h3>Modèles PROPAL en base (bruts)</h3>';
if (empty($models_raw['propal'])) {
    echo '<p>(aucun enregistrement)</p>';
} else {
    echo '<table class="noborder"><tr class="liste_titre"><td>rowid</td><td>nom</td><td>libelle</td><td>description</td></tr>';
    foreach ($models_raw['propal'] as $o) {
        echo '<tr class="oddeven"><td>'.h($o->rowid).'</td><td><code>'.h($o->nom).'</code></td><td>'.h($o->libelle).'</td><td>'.h($o->description).'</td></tr>';
    }
    echo '</table>';
}

echo '<h3>Modèles PROPAL normalisés (value = avant ":" )</h3>';
if (empty($norm_propal)) {
    echo '<p>(aucun)</p>';
} else {
    echo '<ul>';
    foreach ($norm_propal as $n) {
        echo '<li>#'.h($n['rowid']).' → <code>'.h($n['nom']).'</code></li>';
    }
    echo '</ul>';
}

echo '<h3>Modèles INVOICE en base (bruts)</h3>';
if (empty($models_raw['invoice'])) {
    echo '<p>(aucun enregistrement)</p>';
} else {
    echo '<table class="noborder"><tr class="liste_titre"><td>rowid</td><td>nom</td><td>libelle</td><td>description</td></tr>';
    foreach ($models_raw['invoice'] as $o) {
        echo '<tr class="oddeven"><td>'.h($o->rowid).'</td><td><code>'.h($o->nom).'</code></td><td>'.h($o->libelle).'</td><td>'.h($o->description).'</td></tr>';
    }
    echo '</table>';
}

echo '<h3>Modèles INVOICE normalisés</h3>';
if (empty($norm_invoice)) {
    echo '<p>(aucun)</p>';
} else {
    echo '<ul>';
    foreach ($norm_invoice as $n) {
        echo '<li>#'.h($n['rowid']).' → <code>'.h($n['nom']).'</code></li>';
    }
    echo '</ul>';
}

echo '<h3>Conclusions automatiques</h3>';
echo '<ul>';
// Devis
if (!empty($const_propal_odt)) {
    echo '<li><strong>Devis</strong> : <code>PROPALE_ADDON_ODT</code> = <code>'.h($const_propal_odt).'</code> ⇒ L’ODT est prioritaire. Mettre cette constante à vide pour voir le PDF par défaut.</li>';
} else {
    echo '<li><strong>Devis</strong> : <code>PROPALE_ADDON_ODT</code> est vide ⇒ Le PDF devrait être pris par défaut ( <code>PROPALE_ADDON_PDF = '.h($const_propal_pdf).'</code> ). Si l’UI montre encore <code>template_proposal.odt</code>, il y a un cache ou une surcharge de thème.</li>';
}
// Facture
if (!empty($const_invoice_pdf)) {
    echo '<li><strong>Facture</strong> : <code>FACTURE_ADDON_PDF</code> = <code>'.h($const_invoice_pdf).'</code>. Si l’UI affiche <code>facture_sap_v3: Aucun</code>, c’est que le champ <code>nom</code> en BDD contient un suffixe (voir la table ci‑dessus) ; il faut normaliser à <code>facture_sap_v3</code> strict.</li>';
} else {
    echo '<li><strong>Facture</strong> : <code>FACTURE_ADDON_PDF</code> est vide ⇒ définir la constante à <code>facture_sap_v3</code>.</li>';
}
echo '</ul>';

echo '<div class="info">';
echo '<p>Après modification des constantes/modèles, pensez à : <em>Outils d’administration → Vider/Reconstruire caches</em>. Sur certains thèmes Select2, un <em>F5 / Ctrl+F5</em> ou changement temporaire de thème aide à rafraîchir le libellé.</p>';
echo '</div>';

llxFooter();
$db->close();
