<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Test attestation');

require_once DOL_DOCUMENT_ROOT.'/custom/attestationsap/core/modules/attestationsap/pdf_attestation_sap.modules.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

// Trouver le premier client avec des factures SAP
$sql = "SELECT DISTINCT f.fk_soc FROM ".MAIN_DB_PREFIX."facture f
        WHERE f.entity = ".(int)$conf->entity." AND f.model_pdf = 'facture_sap_v3'
        LIMIT 1";
$res = $db->query($sql);
$socid = 0;
if ($res && $o = $db->fetch_object($res)) $socid = (int)$o->fk_soc;

print '<p>Client trouvé : id='.$socid.'</p>';

if ($socid > 0) {
    $soc = new Societe($db);
    $soc->fetch($socid);
    print '<p>Nom : '.dol_escape_htmltag($soc->name).'</p>';
    
    // Activer les erreurs PHP
    set_error_handler(function($no, $str, $file, $line) {
        echo '<p class="error">PHP Error ['.$no.']: '.htmlspecialchars($str).' — '.htmlspecialchars(basename($file)).':'.$line.'</p>';
        return true;
    });
    
    ob_start();
    try {
        $result = pdf_attestation_sap::write_file($soc, 0, 0, 2025, array());
        $out = ob_get_clean();
        print '<p>Résultat : '.($result ? '✓ '.htmlspecialchars($result) : '✗ false').'</p>';
        if ($out) print '<p>Output : '.htmlspecialchars($out).'</p>';
    } catch (Throwable $e) {
        ob_get_clean();
        print '<p class="error">Exception : '.htmlspecialchars($e->getMessage()).' — '.htmlspecialchars($e->getFile()).':'.$e->getLine().'</p>';
    }
    restore_error_handler();
}

llxFooter();
$db->close();
