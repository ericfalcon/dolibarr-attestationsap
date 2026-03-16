<?php
require '../../main.inc.php';

if (!$user->admin) accessforbidden();

llxHeader('', 'Test Logo Final');

print '<h2>Test de résolution du chemin logo</h2>';

print '<h3>Variables de base :</h3>';
print '<pre>';
print "DOL_DATA_ROOT: ".DOL_DATA_ROOT."\n";
print "DOL_DOCUMENT_ROOT: ".DOL_DOCUMENT_ROOT."\n";
print "conf->mycompany->dir_output: ".$conf->mycompany->dir_output."\n";
if (!empty($conf->mycompany->multidir_output[$conf->entity])) {
    print "conf->mycompany->multidir_output: ".$conf->mycompany->multidir_output[$conf->entity]."\n";
}
print '</pre>';

print '<h3>Simulation du code actuel :</h3>';

$logodir = $conf->mycompany->dir_output;
if (!empty($conf->mycompany->multidir_output[$conf->entity])) {
    $logodir = $conf->mycompany->multidir_output[$conf->entity];
}

print '<p><strong>$logodir =</strong> <code>'.$logodir.'</code></p>';

// Test logo SAP
$logo_path = '';
$sap_logo_extensions = array('png', 'jpg', 'jpeg');
foreach ($sap_logo_extensions as $ext) {
    $test_path = $logodir.'/logos/logo-sap.'.$ext;
    print '<p>Test : <code>'.$test_path.'</code> → ';
    if (file_exists($test_path) && is_readable($test_path)) {
        print '<span style="color: green;">✓ TROUVÉ ('.filesize($test_path).' bytes)</span></p>';
        $logo_path = $test_path;
        break;
    } else {
        print '<span style="color: red;">✗ Non trouvé</span></p>';
    }
}

// Test logo principal
if (empty($logo_path)) {
    $logo_file = getDolGlobalString('MAIN_INFO_SOCIETE_LOGO');
    print '<p><strong>MAIN_INFO_SOCIETE_LOGO =</strong> <code>'.$logo_file.'</code></p>';
    
    if ($logo_file) {
        $test_path = $logodir.'/logos/'.$logo_file;
        print '<p>Test : <code>'.$test_path.'</code> → ';
        if (file_exists($test_path) && is_readable($test_path)) {
            print '<span style="color: green;">✓ TROUVÉ ('.filesize($test_path).' bytes)</span></p>';
            $logo_path = $test_path;
        } else {
            print '<span style="color: red;">✗ Non trouvé</span></p>';
        }
    }
}

print '<hr>';
print '<h3>Résultat final :</h3>';

if (!empty($logo_path)) {
    print '<p style="color: green; font-size: 18px;"><strong>✓ Logo trouvé :</strong></p>';
    print '<p><code>'.$logo_path.'</code></p>';
    print '<p>Taille : '.filesize($logo_path).' octets</p>';
    
    // Test d'affichage
    print '<h4>Aperçu :</h4>';
    $relative = str_replace(DOL_DATA_ROOT.'/', '', $logo_path);
    print '<p>Chemin relatif : <code>'.$relative.'</code></p>';
    
    $url = DOL_URL_ROOT.'/viewimage.php?modulepart=mycompany&file='.urlencode($relative);
    print '<img src="'.$url.'" style="max-height: 100px; border: 2px solid #333; padding: 10px; background: white;" alt="Logo">';
    
    // Test TCPDF
    print '<hr>';
    print '<h3>Test avec TCPDF :</h3>';
    
    try {
        require_once DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/tcpdf.php';
        
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 12);
        
        $html = '<h1>Test Logo TCPDF</h1>';
        $html .= '<p>Logo utilisé : '.$logo_path.'</p>';
        $html .= '<img src="'.$logo_path.'" height="40" alt="Logo">';
        
        $pdf->writeHTML($html, true, false, true, false, '');
        
        $test_file = $conf->attestationsap->dir_output.'/test_logo_tcpdf.pdf';
        $pdf->Output($test_file, 'F');
        
        if (file_exists($test_file)) {
            print '<p style="color: green;">✓ <strong>PDF test généré avec succès !</strong></p>';
            print '<p><a class="button" href="'.DOL_URL_ROOT.'/document.php?modulepart=attestationsap&file=test_logo_tcpdf.pdf" target="_blank">Télécharger le PDF test</a></p>';
        } else {
            print '<p style="color: red;">✗ Échec de génération du PDF</p>';
        }
        
    } catch (Exception $e) {
        print '<p style="color: red;">✗ Erreur TCPDF : '.$e->getMessage().'</p>';
    }
    
} else {
    print '<p style="color: red; font-size: 18px;"><strong>✗ Aucun logo trouvé</strong></p>';
}

llxFooter();
?>