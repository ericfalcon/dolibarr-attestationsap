<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();

llxHeader('', 'Source Aucun 3');

$f = DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
print '<p>Fichier : <code>'.htmlspecialchars($f).'</code> — existe : '.(file_exists($f)?'OUI':'NON').'</p>';

if (file_exists($f)) {
    $lines = file($f);
    $terms = array('Aucun', 'aucun', 'nomodel', ': None', 'document_model', 'pdf_', 'showdocuments');
    foreach ($lines as $n => $line) {
        foreach ($terms as $term) {
            if (stripos($line, $term) !== false) {
                $start = max(0, $n-1); $end = min(count($lines)-1, $n+2);
                print '<pre style="background:#f5f5f5;border:1px solid #ccc;padding:4px;margin:2px">';
                for ($i=$start;$i<=$end;$i++) {
                    $m = ($i===$n) ? '<b style="background:yellow">'.($i+1).': '.htmlspecialchars($lines[$i]).'</b>'
                                   : ($i+1).': '.htmlspecialchars($lines[$i]);
                    print $m;
                }
                print '</pre>';
                break;
            }
        }
    }
}

llxFooter();
$db->close();
