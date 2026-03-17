<?php
/**
 * Widget SAP pour le tableau de bord Dolibarr
 * Affiche : derniers devis SAP, dernières factures SAP, rappel attestations en janvier
 */

include_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';

class box_attestationsap extends ModeleBoxes
{
    public $boxcode  = 'box_attestationsap';
    public $boximg   = 'generic';
    public $boxlabel = 'BoxAttestationSAP';
    public $depends  = array('modAttestationSap');

    public function __construct($db, $param = '')
    {
        global $langs;
        $this->db = $db;
        $langs->load('attestationsap@attestationsap');
    }

    public function loadBox($maxlines = 5)
    {
        global $conf, $langs, $user;

        $langs->load('attestationsap@attestationsap');
        $this->info_box_head = array('text' => '🏠 Services à la Personne (SAP)');

        $lines = array();
        $now   = dol_now();
        $month = (int)dol_print_date($now, '%m');
        $entity = (int)$conf->entity;

        // ---- RAPPEL JANVIER ----
        if ($month === 1) {
            $lines[] = array(
                'logo'   => 'warning',
                'label'  => '<strong style="color:#c0392b">📋 Janvier : pensez à générer les attestations fiscales SAP !</strong>',
                'url'    => dol_buildpath('/custom/attestationsap/index.php?tab=generate', 1),
                'asis'   => 1,
            );
        }

        // ---- DERNIERS DEVIS SAP ----
        $lines[] = array(
            'label' => '<strong>Derniers devis SAP</strong>',
            'url'   => DOL_URL_ROOT.'/comm/propal/list.php',
            'asis'  => 1,
        );

        $sql = "SELECT p.rowid, p.ref, p.total_ttc, p.fk_statut, s.nom as client,
                       p.datep as date_propal
                FROM ".MAIN_DB_PREFIX."propal p
                LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = p.fk_soc
                WHERE p.entity = ".$entity."
                  AND p.model_pdf IN ('devis_sap','devis_sap_v2')
                ORDER BY p.rowid DESC
                LIMIT ".((int)$maxlines);

        $res = $this->db->query($sql);
        $nb_propal = 0;
        if ($res) {
            while ($obj = $this->db->fetch_object($res)) {
                $nb_propal++;
                $statuts = array(0 => 'Brouillon', 1 => 'Validé', 2 => 'Signé', 3 => 'Refusé', 4 => 'Expiré');
                $statut  = isset($statuts[$obj->fk_statut]) ? $statuts[$obj->fk_statut] : '';
                $lines[] = array(
                    'label' => '&nbsp;&nbsp;<a href="'.DOL_URL_ROOT.'/comm/propal/card.php?id='.$obj->rowid.'">'.
                               dol_escape_htmltag($obj->ref).'</a> — '.
                               dol_escape_htmltag($obj->client).' — '.
                               price($obj->total_ttc).' € <span class="opacitymedium">('.$statut.')</span>',
                    'url'   => DOL_URL_ROOT.'/comm/propal/card.php?id='.$obj->rowid,
                    'asis'  => 1,
                );
            }
            $this->db->free($res);
        }
        if ($nb_propal === 0) {
            $lines[] = array('label' => '&nbsp;&nbsp;<span class="opacitymedium">Aucun devis SAP</span>', 'asis' => 1);
        }

        // ---- DERNIÈRES FACTURES SAP ----
        $lines[] = array(
            'label' => '<strong>Dernières factures SAP</strong>',
            'url'   => DOL_URL_ROOT.'/compta/facture/list.php',
            'asis'  => 1,
        );

        // Récupérer les modèles SAP configurés
        $models_raw = getDolGlobalString('ATTESTATIONSAP_FACTURE_MODEL_LIST', 'facture_sap_v3');
        $models = array_filter(array_map('trim', explode(',', $models_raw)));
        if (empty($models)) $models = array('facture_sap_v3');

        $model_list = implode("','", array_map(array($this->db, 'escape'), $models));

        $sql = "SELECT f.rowid, f.ref, f.total_ttc, f.fk_statut, s.nom as client,
                       f.datef as date_facture
                FROM ".MAIN_DB_PREFIX."facture f
                LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = f.fk_soc
                WHERE f.entity = ".$entity."
                  AND f.model_pdf IN ('".$model_list."')
                ORDER BY f.rowid DESC
                LIMIT ".((int)$maxlines);

        $res = $this->db->query($sql);
        $nb_fact = 0;
        if ($res) {
            while ($obj = $this->db->fetch_object($res)) {
                $nb_fact++;
                $statuts = array(0 => 'Brouillon', 1 => 'Validée', 2 => 'Payée', 3 => 'Abandonnée');
                $statut  = isset($statuts[$obj->fk_statut]) ? $statuts[$obj->fk_statut] : '';
                $date    = $obj->date_facture ? dol_print_date($this->db->jdate($obj->date_facture), 'day') : '';
                $lines[] = array(
                    'label' => '&nbsp;&nbsp;<a href="'.DOL_URL_ROOT.'/compta/facture/card.php?id='.$obj->rowid.'">'.
                               dol_escape_htmltag($obj->ref).'</a> — '.
                               dol_escape_htmltag($obj->client).' — '.
                               price($obj->total_ttc).' €'.($date ? ' <span class="opacitymedium">'.$date.'</span>' : '').
                               ' <span class="opacitymedium">('.$statut.')</span>',
                    'url'   => DOL_URL_ROOT.'/compta/facture/card.php?id='.$obj->rowid,
                    'asis'  => 1,
                );
            }
            $this->db->free($res);
        }
        if ($nb_fact === 0) {
            $lines[] = array('label' => '&nbsp;&nbsp;<span class="opacitymedium">Aucune facture SAP</span>', 'asis' => 1);
        }

        // Lien vers les attestations
        $lines[] = array(
            'label' => '<a href="'.dol_buildpath('/custom/attestationsap/index.php?tab=generate', 1).'">→ Générer les attestations fiscales</a>',
            'url'   => dol_buildpath('/custom/attestationsap/index.php?tab=generate', 1),
            'asis'  => 1,
        );

        $this->info_box_contents = $lines;
        return 0;
    }

    public function showBox($head = null, $contents = null, $nooutput = 0)
    {
        return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
    }
}
