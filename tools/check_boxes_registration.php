<?php
require_once dirname(__FILE__) . '/../../../main.inc.php';
if (!$user->admin) accessforbidden();
llxHeader('', 'Check boxes');

$entity = (int)$conf->entity;

// 1. Vérifier si notre box est en base
print '<h3>1. Boxes enregistrées en base (llx_boxes_def)</h3>';
$res = $db->query("SELECT rowid, file, note FROM ".MAIN_DB_PREFIX."boxes_def ORDER BY rowid DESC LIMIT 20");
if ($res) {
    print '<pre>';
    while ($o = $db->fetch_object($res)) {
        $mark = (strpos($o->file, 'attestationsap') !== false) ? ' ← ICI' : '';
        print 'rowid='.$o->rowid.' file=['.htmlspecialchars($o->file).']'.$mark."\n";
    }
    print '</pre>';
}

// 2. Vérifier comment Dolibarr charge les boxes des modules
print '<h3>2. $conf->modules_parts[boxes]</h3>';
print '<pre>';
if (!empty($conf->modules_parts['boxes'])) {
    var_dump($conf->modules_parts['boxes']);
} else {
    print 'Vide ou non défini';
}
print '</pre>';

// 3. Le fichier box est-il accessible via dol_buildpath ?
print '<h3>3. Chemin du fichier box</h3>';
$path1 = DOL_DOCUMENT_ROOT.'/custom/attestationsap/core/boxes/box_attestationsap.php';
$path2 = dol_buildpath('/attestationsap/core/boxes/box_attestationsap.php', 0);
print '<p>Path direct : <code>'.htmlspecialchars($path1).'</code> — '.($path1 && file_exists($path1) ? '✓ EXISTS' : '✗ ABSENT').'</p>';
print '<p>dol_buildpath : <code>'.htmlspecialchars($path2).'</code> — '.(file_exists($path2) ? '✓ EXISTS' : '✗ ABSENT').'</p>';

// 4. Tenter d'inclure et instancier
print '<h3>4. Test include + instanciation</h3>';
if (file_exists($path1)) {
    try {
        require_once $path1;
        if (class_exists('box_attestationsap')) {
            $box = new box_attestationsap($db);
            print '<p class="ok">✓ Classe box_attestationsap instanciée</p>';
            print '<p>boxcode = <code>'.$box->boxcode.'</code></p>';
            print '<p>boxlabel = <code>'.$box->boxlabel.'</code></p>';
        } else {
            print '<p class="error">✗ Classe introuvable après include</p>';
        }
    } catch(Throwable $e) {
        print '<p class="error">Exception : '.htmlspecialchars($e->getMessage()).'</p>';
    }
}

// 5. Comment déclarer manuellement en base
print '<h3>5. Insérer manuellement en base si absent</h3>';
$check = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."boxes_def WHERE file LIKE '%attestationsap%'");
if ($check && $db->num_rows($check) == 0) {
    $sql = "INSERT INTO ".MAIN_DB_PREFIX."boxes_def (file, note, tms) 
            VALUES ('box_attestationsap@attestationsap', '', NOW())";
    if ($db->query($sql)) {
        print '<p class="ok">✓ Box insérée en base (rowid='.$db->last_insert_id(MAIN_DB_PREFIX.'boxes_def', 'rowid').')</p>';
    } else {
        print '<p class="error">✗ Erreur insert : '.$db->lasterror().'</p>';
    }
} else {
    print '<p class="ok">✓ Box déjà présente en base</p>';
}

// Relire pour confirmer
$check2 = $db->query("SELECT rowid, file FROM ".MAIN_DB_PREFIX."boxes_def WHERE file LIKE '%attestationsap%'");
if ($check2 && $o = $db->fetch_object($check2)) {
    print '<p>→ rowid='.$o->rowid.' file=['.$o->file.']</p>';
}

llxFooter();
$db->close();
