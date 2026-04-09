<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check hooks tiers');

// Chercher quel hook est utilisé pour les onglets dans fiche tiers Dolibarr 23
$f = DOL_DOCUMENT_ROOT.'/societe/card.php';
$lines = file($f);
print '<h3>Hooks dans societe/card.php</h3>';
print '<pre style="font-size:10px;background:#1a2535;color:#c0d0e0;padding:8px;overflow:auto;max-height:500px">';
foreach ($lines as $n => $line) {
    if (stripos($line, 'hook') !== false
     || stripos($line, 'Tab') !== false
     || stripos($line, 'executeHooks') !== false) {
        print ($n+1).': '.htmlspecialchars($line);
    }
}
print '</pre>';

llxFooter(); $db->close();
