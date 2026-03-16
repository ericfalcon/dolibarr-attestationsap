<?php
/**
 * Force le rechargement complet des modèles Dolibarr
 * Vide tous les caches liés aux modèles de documents
 */
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();

llxHeader('', 'Force reload modèles');
print '<h2>Nettoyage forcé des caches modèles</h2>';

// 1. Vider le cache Dolibarr (fichiers temporaires)
$tmpdir = DOL_DATA_ROOT . '/admin/temp';
if (is_dir($tmpdir)) {
    $files = glob($tmpdir . '/*');
    $n = 0;
    if ($files) foreach ($files as $f) { if (is_file($f)) { unlink($f); $n++; } }
    print '<p>✓ Cache admin/temp vidé ('.$n.' fichiers)</p>';
}

// 2. Vider le cache OPcache si disponible
if (function_exists('opcache_reset')) {
    opcache_reset();
    print '<p>✓ OPcache réinitialisé</p>';
} else {
    print '<p class="warning">OPcache non disponible (normal sur certains serveurs)</p>';
}

// 3. Forcer la mise à jour de FACTURE_ADDON_PDF
$entity = (int)$conf->entity;
$current = getDolGlobalString('FACTURE_ADDON_PDF', '');
print '<p>FACTURE_ADDON_PDF actuel : <code>'.dol_escape_htmltag($current).'</code></p>';

// Réécrire la constante pour forcer un flush du cache interne Dolibarr
$res = dolibarr_set_const($db, 'FACTURE_ADDON_PDF', 'facture_sap_v3', 'chaine', 0, '', $entity);
print '<p>'.($res !== false ? '✓' : '✗').' FACTURE_ADDON_PDF réécrit à <code>facture_sap_v3</code></p>';

// 4. Vérifier l'état final en base
print '<h3>État final</h3>';
$resql = $db->query("SELECT nom FROM ".MAIN_DB_PREFIX."document_model WHERE entity=".$entity." AND type='invoice' ORDER BY nom");
if ($resql) {
    print '<pre>';
    while ($o = $db->fetch_object($resql)) print 'nom=['.dol_escape_htmltag($o->nom)."]\n";
    print '</pre>';
}

print '<hr>';
print '<p><strong>Maintenant :</strong></p>';
print '<ol>';
print '<li>Allez dans <strong>Administration → Outils → Vider caches</strong></li>';
print '<li>Déconnectez-vous complètement de Dolibarr</li>';
print '<li>Reconnectez-vous</li>';
print '<li>Ouvrez une facture et vérifiez le select des modèles</li>';
print '</ol>';

// Lien direct vers les outils admin
print '<p><a href="'.DOL_URL_ROOT.'/admin/tools.php" class="butAction" target="_blank">Ouvrir Administration → Outils</a></p>';

llxFooter();
$db->close();
