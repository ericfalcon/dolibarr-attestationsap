<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Test attestation 2');

// Simuler exactement ce que fait le code ligne 195-202
$outputdir = getDolGlobalString('ATTESTATIONSAP_OUTPUTDIR', '');
print '<p>1. ATTESTATIONSAP_OUTPUTDIR brut = <code>'.dol_escape_htmltag(var_export($outputdir,true)).'</code></p>';
print '<p>2. empty() = '.var_export(empty($outputdir),true).'</p>';
print '<p>3. dirname($outputdir) = <code>'.dol_escape_htmltag(dirname($outputdir)).'</code></p>';
print '<p>4. is_dir(dirname) = '.var_export(is_dir(dirname($outputdir)),true).'</p>';

// Après la condition
if (empty($outputdir) || !is_dir(dirname($outputdir))) {
    $outputdir = DOL_DATA_ROOT . '/attestationsap';
    print '<p style="color:orange">→ Fallback utilisé : <code>'.dol_escape_htmltag($outputdir).'</code></p>';
} else {
    print '<p style="color:green">→ Valeur constante utilisée : <code>'.dol_escape_htmltag($outputdir).'</code></p>';
}

print '<p>5. is_dir($outputdir) = '.var_export(is_dir($outputdir),true).'</p>';
print '<p>6. is_writable($outputdir) = '.var_export(is_writable($outputdir),true).'</p>';

if (!is_dir($outputdir)) {
    $r = @mkdir($outputdir, 0755, true);
    print '<p>7. mkdir() = '.var_export($r,true).'</p>';
}

llxFooter();
$db->close();
