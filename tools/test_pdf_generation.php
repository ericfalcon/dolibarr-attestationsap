<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/attestationsap/class/attestationsap.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/attestationsap/core/modules/pdf/pdf_attestation_sap.modules.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

if (!$user->admin) accessforbidden();

$year = GETPOST('year', 'int') ?: date('Y');
$socid = GETPOST('socid', 'int');

llxHeader('', 'Test Génération PDF');

print load_fiche_titre('Test de génération PDF Attestation SAP', '', 'title_setup');

// Vérifications préalables
print '<div class="info">';
print '<h3>🔍 Vérifications préalables</h3>';

// 1. Répertoire de sortie
$outputdir = $conf->attestationsap->dir_output;
print '<p><strong>Répertoire de sortie :</strong> '.$outputdir.'</p>';

if (!is_dir($outputdir)) {
    print '<p class="error">❌ Le répertoire n\'existe pas. Tentative de création...</p>';
    if (dol_mkdir($outputdir) < 0) {
        print '<p class="error">❌ Impossible de créer le répertoire</p>';
    } else {
        print '<p class="ok">✅ Répertoire créé avec succès</p>';
    }
} else {
    print '<p class="ok">✅ Le répertoire existe</p>';
}

// Permissions
if (is_writable($outputdir)) {
    print '<p class="ok">✅ Le répertoire est accessible en écriture</p>';
} else {
    print '<p class="error">❌ Le répertoire n\'est PAS accessible en écriture</p>';
    print '<p>Exécutez : <code>chmod 755 '.$outputdir.'</code></p>';
}

// 2. Configuration SAP
print '<h4>Configuration SAP :</h4>';
print '<ul>';
print '<li><strong>ATTESTATIONSAP_ID_PRO :</strong> '.($conf->global->ATTESTATIONSAP_ID_PRO ?: '<span style="color:red;">Non défini</span>').'</li>';
print '<li><strong>ATTESTATIONSAP_SIGN_NAME :</strong> '.($conf->global->ATTESTATIONSAP_SIGN_NAME ?: '<span style="color:red;">Non défini</span>').'</li>';
print '<li><strong>mysoc->idprof8 :</strong> '.($mysoc->idprof8 ?: '<span style="color:red;">Non défini</span>').'</li>';
print '</ul>';

// 3. TCPDF
$tcpdf_path = DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/tcpdf.php';
if (file_exists($tcpdf_path)) {
    print '<p class="ok">✅ TCPDF trouvé : '.$tcpdf_path.'</p>';
} else {
    print '<p class="error">❌ TCPDF non trouvé à : '.$tcpdf_path.'</p>';
}

print '</div><br>';

// Formulaire de sélection
print '<form method="GET">';
print '<table class="noborder">';
print '<tr>';
print '<td>Année :</td>';
print '<td><input type="number" name="year" value="'.$year.'" min="2020" max="2030"></td>';
print '</tr>';

// Liste des clients avec factures SAP
$att = new AttestationSAP($db);
$clients = $att->getTotalsByYear($year);

if (!empty($clients)) {
    print '<tr>';
    print '<td>Client :</td>';
    print '<td><select name="socid" required>';
    print '<option value="">-- Choisir un client --</option>';
    foreach ($clients as $c) {
        $selected = ($socid == $c->socid) ? 'selected' : '';
        print '<option value="'.$c->socid.'" '.$selected.'>'.$c->client.' ('.price($c->total_ttc).' €)</option>';
    }
    print '</select></td>';
    print '</tr>';
}

print '<tr>';
print '<td colspan="2"><input type="submit" class="button" value="Tester la génération PDF"></td>';
print '</tr>';
print '</table>';
print '</form><br>';

// Test de génération
if ($socid > 0) {
    print '<h3>📄 Test de génération pour le client #'.$socid.'</h3>';
    
    // Charger le client
    $soc = new Societe($db);
    $result = $soc->fetch($socid);
    
    if ($result <= 0) {
        print '<p class="error">❌ Impossible de charger le client</p>';
    } else {
        print '<p class="ok">✅ Client chargé : '.$soc->name.'</p>';
        
        // Récupérer les données
        $client_data = $att->getClientTotal($socid, $year);
        $hours = $att->getHoursByYearAndClient($socid, $year);
        $factures = $att->getInvoicesByClientYear($socid, $year);
        
        print '<p><strong>Montant total :</strong> '.price($client_data->total_ttc).' €</p>';
        print '<p><strong>Heures totales :</strong> '.$hours.'</p>';
        print '<p><strong>Nombre de factures :</strong> '.count($factures).'</p>';
        
        if (empty($factures)) {
            print '<p class="warning">⚠ Aucune facture trouvée pour ce client en '.$year.'</p>';
        } else {
            print '<p class="ok">✅ '.count($factures).' facture(s) trouvée(s)</p>';
        }
        
        // Tentative de génération
        print '<h4>Génération du PDF...</h4>';
        
        // Activer l'affichage des erreurs PHP
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
        
        // Capturer toutes les sorties et erreurs
        ob_start();
        
        try {
            print '<p>Appel de pdf_attestation_sap::write_file()...</p>';
            print '<p>Paramètres :</p>';
            print '<ul>';
            print '<li>Client ID: '.$soc->id.'</li>';
            print '<li>Nom: '.$soc->name.'</li>';
            print '<li>Total TTC: '.$client_data->total_ttc.'</li>';
            print '<li>Heures: '.$hours.'</li>';
            print '<li>Année: '.$year.'</li>';
            print '<li>Nb factures: '.count($factures).'</li>';
            print '</ul>';
            
            $file = pdf_attestation_sap::write_file($soc, $client_data->total_ttc, $hours, $year, $factures);
            
            print '<p>Retour de write_file: '.var_export($file, true).'</p>';
            
            if ($file && file_exists($file)) {
                print '<p class="ok">✅ <strong>PDF généré avec succès !</strong></p>';
                print '<p>Fichier : <code>'.$file.'</code></p>';
                print '<p>Taille : '.filesize($file).' octets</p>';
                
                // Lien de téléchargement
                $relativefile = basename($file);
                print '<p><a class="button" href="'.DOL_URL_ROOT.'/document.php?modulepart=attestationsap&file='.$relativefile.'" target="_blank">📥 Télécharger le PDF</a></p>';
            } elseif ($file === false) {
                print '<p class="error">❌ write_file() a retourné FALSE</p>';
                print '<p>Vérifiez les logs Dolibarr pour plus de détails</p>';
            } elseif (empty($file)) {
                print '<p class="error">❌ write_file() a retourné une chaîne vide</p>';
            } else {
                print '<p class="error">❌ Fichier non créé. Chemin retourné : '.$file.'</p>';
                print '<p>Le fichier existe ? '.var_export(file_exists($file), true).'</p>';
            }
            
        } catch (Exception $e) {
            print '<p class="error">❌ Exception : '.$e->getMessage().'</p>';
            print '<p>Fichier : '.$e->getFile().'</p>';
            print '<p>Ligne : '.$e->getLine().'</p>';
            print '<pre>'.$e->getTraceAsString().'</pre>';
        } catch (Error $e) {
            print '<p class="error">❌ Erreur fatale : '.$e->getMessage().'</p>';
            print '<p>Fichier : '.$e->getFile().'</p>';
            print '<p>Ligne : '.$e->getLine().'</p>';
            print '<pre>'.$e->getTraceAsString().'</pre>';
        }
        
        // Récupérer et afficher les sorties tamponnées
        $output = ob_get_clean();
        print $output;
        
        // Afficher les dernières erreurs PHP
        $last_error = error_get_last();
        if ($last_error && ($last_error['type'] & (E_ERROR | E_WARNING | E_PARSE | E_NOTICE))) {
            print '<div class="error">';
            print '<h4>Dernière erreur PHP :</h4>';
            print '<pre>'.print_r($last_error, true).'</pre>';
            print '</div>';
        }
    }
}

// Liste des PDFs existants
print '<br><h3>📁 PDFs existants</h3>';

if (is_dir($outputdir)) {
    $files = scandir($outputdir);
    $pdf_files = array_filter($files, function($f) {
        return pathinfo($f, PATHINFO_EXTENSION) === 'pdf';
    });
    
    if (!empty($pdf_files)) {
        print '<ul>';
        foreach ($pdf_files as $file) {
            $filepath = $outputdir.'/'.$file;
            $size = filesize($filepath);
            $date = date('Y-m-d H:i:s', filemtime($filepath));
            print '<li>';
            print '<strong>'.$file.'</strong> ('.$size.' octets, '.$date.')';
            print ' - <a href="'.DOL_URL_ROOT.'/document.php?modulepart=attestationsap&file='.$file.'" target="_blank">Télécharger</a>';
            print '</li>';
        }
        print '</ul>';
    } else {
        print '<p class="opacitymedium">Aucun PDF trouvé dans le répertoire</p>';
    }
} else {
    print '<p class="error">Le répertoire n\'existe pas</p>';
}

llxFooter();
?>