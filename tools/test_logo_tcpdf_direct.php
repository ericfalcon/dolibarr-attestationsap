<?php
require '../../main.inc.php';

if (!$user->admin) accessforbidden();

llxHeader('', 'Test Logo TCPDF Direct');

print '<h2>Test insertion logo avec TCPDF (méthode directe)</h2>';

// Chemins à tester
$logos_to_test = array(
    'Logo entreprise' => '/home/ericfalcon/dolibarrdata/mycompany/logos/logo-no-background-violet.png',
    'Logo SAP (img)' => DOL_DOCUMENT_ROOT.'/custom/attestationsap/img/logo-sap.jpg',
    'Logo SAP (racine)' => DOL_DOCUMENT_ROOT.'/custom/attestationsap/logo-sap.jpg',
);

foreach ($logos_to_test as $name => $path) {
    print '<h3>Test : '.$name.'</h3>';
    print '<p><strong>Chemin :</strong> <code>'.$path.'</code></p>';
    
    if (!file_exists($path)) {
        print '<p class="error">❌ Fichier non trouvé</p><hr>';
        continue;
    }
    
    if (!is_readable($path)) {
        print '<p class="error">❌ Fichier non lisible</p><hr>';
        continue;
    }
    
    $filesize = filesize($path);
    print '<p class="ok">✅ Fichier trouvé ('.$filesize.' octets)</p>';
    
    // Test avec TCPDF
    try {
        require_once DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/tcpdf.php';
        
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 12);
        
        // Écrire du texte
        $pdf->Cell(0, 10, 'Test insertion logo : '.$name, 0, 1);
        
        // Essayer d'insérer l'image
        print '<p>Tentative d\'insertion avec Image()...</p>';
        
        // MÉTHODE 1 : Chemin direct
        $pdf->Image($path, 15, 30, 0, 20);
        
        print '<p class="ok">✅ Image insérée sans erreur</p>';
        
        // Générer le PDF
        $test_file = $conf->attestationsap->dir_output.'/test_logo_'.md5($name).'.pdf';
        $pdf->Output($test_file, 'F');
        
        if (file_exists($test_file) && filesize($test_file) > 0) {
            print '<p class="ok">✅ <strong>PDF généré avec succès !</strong></p>';
            print '<p><a class="button" href="'.DOL_URL_ROOT.'/document.php?modulepart=attestationsap&file='.basename($test_file).'" target="_blank">📥 Télécharger</a></p>';
        } else {
            print '<p class="error">❌ PDF non généré</p>';
        }
        
    } catch (Exception $e) {
        print '<p class="error">❌ Exception : '.$e->getMessage().'</p>';
        print '<pre>'.htmlspecialchars($e->getTraceAsString()).'</pre>';
    }
    
    print '<hr>';
}

// Test avec conversion en base64 (si les chemins directs échouent)
print '<h2>Test alternatif : Conversion base64</h2>';

$logo_test = '/home/ericfalcon/dolibarrdata/mycompany/logos/logo-no-background-violet.png';

if (file_exists($logo_test)) {
    print '<p>Tentative avec encodage base64...</p>';
    
    try {
        require_once DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/tcpdf.php';
        
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        
        // Lire le fichier et encoder en base64
        $imagedata = file_get_contents($logo_test);
        $base64 = base64_encode($imagedata);
        $imgdata = 'data:image/png;base64,'.$base64;
        
        // Utiliser l'image base64
        $pdf->Image('@'.$imgdata, 15, 30, 0, 20);
        
        $test_file = $conf->attestationsap->dir_output.'/test_logo_base64.pdf';
        $pdf->Output($test_file, 'F');
        
        if (file_exists($test_file)) {
            print '<p class="ok">✅ Méthode base64 fonctionne !</p>';
            print '<p><a class="button" href="'.DOL_URL_ROOT.'/document.php?modulepart=attestationsap&file=test_logo_base64.pdf" target="_blank">📥 Télécharger</a></p>';
        }
        
    } catch (Exception $e) {
        print '<p class="error">❌ Erreur base64 : '.$e->getMessage().'</p>';
    }
}

llxFooter();
?>