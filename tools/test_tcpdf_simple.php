<?php
require '../../main.inc.php';

if (!$user->admin) accessforbidden();

llxHeader('', 'Test TCPDF Simple');

print load_fiche_titre('Test TCPDF basique', '', 'title_setup');

// Test 1 : Vérifier que TCPDF peut être chargé
print '<h3>Test 1 : Chargement de TCPDF</h3>';

try {
    require_once DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/tcpdf.php';
    print '<p class="ok">✅ TCPDF chargé avec succès</p>';
} catch (Exception $e) {
    print '<p class="error">❌ Erreur de chargement : '.$e->getMessage().'</p>';
    llxFooter();
    exit;
}

// Test 2 : Créer un PDF simple
print '<h3>Test 2 : Création d\'un PDF minimal</h3>';

$outputdir = $conf->attestationsap->dir_output;
$file = $outputdir.'/test_simple.pdf';

try {
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    $pdf->SetCreator('Test Dolibarr');
    $pdf->SetTitle('Test PDF');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 12);
    
    $html = '<h1>Test TCPDF</h1>';
    $html .= '<p>Si vous voyez ce PDF, TCPDF fonctionne correctement.</p>';
    $html .= '<p>Date : '.date('Y-m-d H:i:s').'</p>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    
    $pdf->Output($file, 'F');
    
    if (file_exists($file) && filesize($file) > 0) {
        print '<p class="ok">✅ PDF créé avec succès</p>';
        print '<p>Fichier : <code>'.$file.'</code></p>';
        print '<p>Taille : '.filesize($file).' octets</p>';
        print '<p><a class="button" href="'.DOL_URL_ROOT.'/document.php?modulepart=attestationsap&file=test_simple.pdf" target="_blank">📥 Télécharger</a></p>';
    } else {
        print '<p class="error">❌ Fichier non créé ou vide</p>';
    }
    
} catch (Exception $e) {
    print '<p class="error">❌ Exception : '.$e->getMessage().'</p>';
    print '<pre>'.$e->getTraceAsString().'</pre>';
}

// Test 3 : Tester avec les vraies données
print '<br><h3>Test 3 : PDF avec données réelles</h3>';

$file2 = $outputdir.'/test_with_data.pdf';

try {
    $pdf2 = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    $pdf2->SetCreator('Dolibarr');
    $pdf2->SetTitle('Test avec données');
    $pdf2->setPrintHeader(false);
    $pdf2->setPrintFooter(false);
    $pdf2->SetMargins(15, 15, 15);
    $pdf2->AddPage();
    $pdf2->SetFont('helvetica', '', 10);
    
    $html = '<h2 style="text-align: center;">TEST ATTESTATION</h2>';
    $html .= '<br><br>';
    
    $html .= '<table cellpadding="5">';
    $html .= '<tr>';
    $html .= '<td><strong>Société :</strong></td>';
    $html .= '<td>'.dol_htmlentitiesbr($conf->global->MAIN_INFO_SOCIETE_NOM).'</td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td><strong>Adresse :</strong></td>';
    $html .= '<td>'.$conf->global->MAIN_INFO_SOCIETE_ZIP.' '.$conf->global->MAIN_INFO_SOCIETE_TOWN.'</td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td><strong>ID Prof 8 :</strong></td>';
    $html .= '<td>'.($mysoc->idprof8 ?: 'Non défini').'</td>';
    $html .= '</tr>';
    $html .= '</table>';
    
    $html .= '<br><br>';
    $html .= '<table border="1" cellpadding="8" style="width: 100%;">';
    $html .= '<tr style="background-color: #e8e8e8;">';
    $html .= '<td><strong>Montant</strong></td>';
    $html .= '<td style="text-align: right;"><strong>175,00 €</strong></td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td>Heures</td>';
    $html .= '<td style="text-align: right;">3,5 h</td>';
    $html .= '</tr>';
    $html .= '</table>';
    
    $pdf2->writeHTML($html, true, false, true, false, '');
    
    $pdf2->Output($file2, 'F');
    
    if (file_exists($file2) && filesize($file2) > 0) {
        print '<p class="ok">✅ PDF avec données créé avec succès</p>';
        print '<p>Fichier : <code>'.$file2.'</code></p>';
        print '<p>Taille : '.filesize($file2).' octets</p>';
        print '<p><a class="button" href="'.DOL_URL_ROOT.'/document.php?modulepart=attestationsap&file=test_with_data.pdf" target="_blank">📥 Télécharger</a></p>';
    } else {
        print '<p class="error">❌ Fichier non créé ou vide</p>';
    }
    
} catch (Exception $e) {
    print '<p class="error">❌ Exception : '.$e->getMessage().'</p>';
    print '<pre>'.$e->getTraceAsString().'</pre>';
}

// Test 4 : Fonction price()
print '<br><h3>Test 4 : Fonction price()</h3>';

try {
    $test_amount = 175.50;
    $formatted = price($test_amount, 0, $langs, 0, 0, -1, 'EUR');
    print '<p class="ok">✅ price('.$test_amount.') = '.$formatted.'</p>';
} catch (Exception $e) {
    print '<p class="error">❌ Erreur avec price() : '.$e->getMessage().'</p>';
}

llxFooter();
?>