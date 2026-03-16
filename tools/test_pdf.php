<?php
// Afficher toutes les erreurs pour debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Inclusion de base Dolibarr
require '../../main.inc.php';

// Inclure les librairies PDF
require_once DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/tcpdf.php';


// Inclure le module AttestationSAP
require_once DOL_DOCUMENT_ROOT.'/custom/attestationsap/class/attestationsap.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/attestationsap/core/modules/pdf/pdf_attestation_sap.modules.php';

llxHeader('', 'Test génération PDF Attestations SAP');

print "<h2>Test génération PDF Attestations SAP</h2>";

// Création du dossier dolibarrdata/attestationsap si nécessaire
$folder = DOL_DATA_ROOT.'/attestationsap/';
if (!file_exists($folder)) mkdir($folder, 0777, true);

// Définir 5 clients fictifs
$clients = [
    (object)['socid'=>1,'client'=>'Client A','address'=>'1 Rue A','zip'=>'75001','town'=>'Paris','total_ttc'=>120],
    (object)['socid'=>2,'client'=>'Client B','address'=>'2 Rue B','zip'=>'75002','town'=>'Paris','total_ttc'=>250],
    (object)['socid'=>3,'client'=>'Client C','address'=>'3 Rue C','zip'=>'75003','town'=>'Paris','total_ttc'=>175],
    (object)['socid'=>4,'client'=>'Client D','address'=>'4 Rue D','zip'=>'75004','town'=>'Paris','total_ttc'=>90],
    (object)['socid'=>5,'client'=>'Client E','address'=>'5 Rue E','zip'=>'75005','town'=>'Paris','total_ttc'=>300],
];

// Boucle sur chaque client et génération PDF
foreach ($clients as $c) {
    try {
        $filename = pdf_attestation_sap::write_file($c, $c->total_ttc, 2025);

        // Calcul du crédit d'impôt (exemple 50%)
        $credit = round($c->total_ttc * 0.5, 2);
        
        $public_folder = DOL_DOCUMENT_ROOT.'/documents/attestationsap/';
if (!file_exists($public_folder)) mkdir($public_folder, 0777, true);
copy(DOL_DATA_ROOT.'/attestationsap/'.$filename, $public_folder.$filename);

$file_url = DOL_URL_ROOT.'/documents/attestationsap/'.$filename;

        // Affichage du résultat avec lien cliquable
        $file_url = DOL_URL_ROOT.'/documents/attestationsap/'.$filename;
        print "PDF généré pour <b>{$c->client}</b> : ";
        print "<a href='$file_url' target='_blank'>Voir PDF</a>";
        print " (Crédit d'impôt : {$credit} €)<br>";
    } catch (Exception $e) {
        print "<span style='color:red'>Erreur pour {$c->client} : ".$e->getMessage()."</span><br>";
    }
}

llxFooter();
