<?php
/**
 * \file        htdocs/custom/attestationsap/core/modules/modAttestationSap.class.php
 * \ingroup     attestationsap
 * \brief       Descripteur du module AttestationSAP
 * \version     2.1.0
 *
 * Conforme Dolibarr 14 → 22+ — PHP 7.4+
 */

require_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

class modAttestationSap extends DolibarrModules
{
    public function __construct($db)
    {
        global $conf;
        $this->db = $db;

        $this->numero          = 500100;
        $this->rights_class    = 'attestationsap';
        $this->family          = 'financial';
        $this->module_position = '90';
        $this->name            = 'AttestationSap';
        $this->description     = "Module Services à la Personne (SAP) : devis, factures et attestations fiscales conformes art. 199 sexdecies CGI";
        $this->descriptionlong = "Génération de devis, factures et attestations fiscales annuelles conformément aux exigences SAP. Conforme NOVA / agrément.";
        $this->editor_name     = 'AttestationSAP';
        $this->editor_url      = 'https://github.com/ericfalcon/dolibarr-attestationsap';
        $this->version         = '2.1.0';
        $this->const_name      = 'MAIN_MODULE_' . strtoupper($this->name);
        $this->picto           = 'object_attestationsap@attestationsap';

        $this->phpmin                = array(7, 4);
        $this->need_dolibarr_version = array(14, 0);

        $this->module_parts = array(
            'hooks'     => array('invoicecard', 'propalcard', 'pdfgeneration', 'formbuilddoc', 'thirdpartycard'),
            'models'    => 1,
            'docmodels' => array(
                'propal'  => array('class' => '/custom/attestationsap/core/modules/propale/doc/'),
                'invoice' => array('class' => '/custom/attestationsap/core/modules/facture/doc/'),
            ),
        );

        $this->dirs = array('/attestationsap', '/attestationsap/temp', '/attestationsap/archive');

        // Page de config dans admin/
        $this->config_page_url = array('admin/setup.php@attestationsap');

        $this->hidden       = false;
        $this->depends      = array('modFacture', 'modPropale', 'modSociete');
        $this->requiredby   = array();
        $this->conflictwith = array();
        $this->langfiles    = array('attestationsap@attestationsap');
        $this->tabs         = array();

        // Droits
        $this->rights = array();
        $r = 0;

        $r++; $this->rights[$r] = array($this->numero.sprintf('%02d',$r), 'Lire les attestations SAP',             0, 0, 'read');
        $r++; $this->rights[$r] = array($this->numero.sprintf('%02d',$r), 'Générer les attestations SAP',           0, 0, 'generate');
        $r++; $this->rights[$r] = array($this->numero.sprintf('%02d',$r), 'Envoyer les attestations SAP par email', 0, 0, 'send');
        $r++; $this->rights[$r] = array($this->numero.sprintf('%02d',$r), 'Supprimer les attestations SAP',         0, 0, 'delete');

        // Menus
        $this->menu = array();
        $m = 0;

        $base = array('langs'=>'attestationsap@attestationsap','target'=>'','user'=>2);

        $this->menu[$m++] = $base + array(
            'fk_menu'=>'','type'=>'top','titre'=>'SAP',
            'prefix'  => img_picto('','object_attestationsap@attestationsap','class="paddingright pictofixedwidth valignmiddle"'),
            'mainmenu'=>'attestationsap','leftmenu'=>'',
            'url'     =>'/custom/attestationsap/index.php',
            'position'=>1000,'enabled'=>'$conf->attestationsap->enabled','perms'=>'$user->rights->attestationsap->read',
        );

        $left = array('fk_menu'=>'fk_mainmenu=attestationsap','type'=>'left','mainmenu'=>'attestationsap');

        $this->menu[$m++] = $base+$left+array('titre'=>'Tableau de bord',        'prefix'=>img_picto('','home','class="paddingright pictofixedwidth valignmiddle"'),  'leftmenu'=>'attestationsap_dashboard','url'=>'/custom/attestationsap/index.php?tab=dashboard','position'=>95, 'enabled'=>'$conf->attestationsap->enabled','perms'=>'$user->rights->attestationsap->read');
        $this->menu[$m++] = $base+$left+array('titre'=>'Nouveau devis SAP',       'prefix'=>img_picto('','propal','class="paddingright pictofixedwidth valignmiddle"'),'leftmenu'=>'attestationsap_devis',     'url'=>'/comm/propal/card.php?action=create&sap_mode=1&model=devis_sap_v2&modelpdf=devis_sap_v2',     'position'=>100,'enabled'=>'$conf->propal->enabled',         'perms'=>'$user->rights->propal->creer');
        $this->menu[$m++] = $base+$left+array('titre'=>'Nouvelle facture SAP',    'prefix'=>img_picto('','bill', 'class="paddingright pictofixedwidth valignmiddle"'), 'leftmenu'=>'attestationsap_facture',   'url'=>'/compta/facture/card.php?action=create&sap_mode=1&model=facture_sap_v3&modelpdf=facture_sap_v3','position'=>110,'enabled'=>'$conf->facture->enabled',        'perms'=>'$user->rights->facture->creer');
        $this->menu[$m++] = $base+$left+array('titre'=>'Générer les attestations','prefix'=>img_picto('','pdf',  'class="paddingright pictofixedwidth valignmiddle"'), 'leftmenu'=>'attestationsap_generate',  'url'=>'/custom/attestationsap/index.php?tab=generate',  'position'=>120,'enabled'=>'$conf->attestationsap->enabled','perms'=>'$user->rights->attestationsap->generate');
        $this->menu[$m++] = $base+$left+array('titre'=>'Attestations existantes', 'prefix'=>img_picto('','folder','class="paddingright pictofixedwidth valignmiddle"'),'leftmenu'=>'attestationsap_list',      'url'=>'/custom/attestationsap/index.php?tab=list',      'position'=>125,'enabled'=>'$conf->attestationsap->enabled','perms'=>'$user->rights->attestationsap->read');
        $this->menu[$m++] = $base+$left+array('titre'=>'Paramètres SAP',          'prefix'=>img_picto('','setup','class="paddingright pictofixedwidth valignmiddle"'), 'leftmenu'=>'attestationsap_setup',     'url'=>'/custom/attestationsap/admin/setup.php',         'position'=>130,'enabled'=>'$conf->attestationsap->enabled','perms'=>'$user->admin');
        $this->menu[$m++] = $base+$left+array('titre'=>'À propos / Aide',         'prefix'=>img_picto('','help', 'class="paddingright pictofixedwidth valignmiddle"'), 'leftmenu'=>'attestationsap_about',     'url'=>'/custom/attestationsap/admin/about.php',         'position'=>140,'enabled'=>'$conf->attestationsap->enabled','perms'=>'$user->rights->attestationsap->read');
    }

    public function init($options = '')
    {
        global $conf;

        $result = $this->_load_tables('/attestationsap/sql/');
        if ($result < 0) return -1;

        $e   = (int)$conf->entity;
        $sql = array();

        $sql[] = "INSERT IGNORE INTO ".MAIN_DB_PREFIX."document_model (nom,entity,type,libelle,description)
                  VALUES ('devis_sap_v2',$e,'propal','Devis SAP V2','Modèle devis Services à la Personne V2')";
        $sql[] = "INSERT IGNORE INTO ".MAIN_DB_PREFIX."document_model (nom,entity,type,libelle,description)
                  VALUES ('facture_sap_v3',$e,'invoice','Facture SAP V3','Modèle facture Services à la Personne V3')";

        $defaults = array(
            'ATTESTATIONSAP_FACTURE_MODEL_LIST'   => 'facture_sap_v3',
            'ATTESTATIONSAP_SERVICES'             => '',
            'ATTESTATIONSAP_CATEGORY_ID'          => '0',
            'ATTESTATIONSAP_NUMERO_AGREMENT'      => '',
            'ATTESTATIONSAP_HABILITATION_TYPE'    => 'declaration',
            'ATTESTATIONSAP_NATURE_SERVICE'       => 'Assistance informatique à domicile',
            'ATTESTATIONSAP_MODE'                 => 'prestataire',
            'ATTESTATIONSAP_INTERVENANT_MODE'     => 'user',
            'ATTESTATIONSAP_INTERVENANT_USER_ID'  => '0',
            'ATTESTATIONSAP_INTERVENANT_LIBRE'    => '',
            'ATTESTATIONSAP_SIGN_NAME'            => '',
            'ATTESTATIONSAP_SIGN_TEXT'            => '',
            'ATTESTATIONSAP_EMAIL_SUBJECT'        => 'Attestation fiscale SAP {YEAR}',
            'ATTESTATIONSAP_EMAIL_BODY'           => "Bonjour,\n\nVeuillez trouver ci-joint votre attestation fiscale pour l'année {YEAR}.\n\nCordialement,\n{COMPANY}",
            'ATTESTATIONSAP_SHOW_CREDIT_IMPOT'    => '1',
            'ATTESTATIONSAP_MENTION_TVA_EXONEREE' => '1',
            'ATTESTATIONSAP_LOGO'                 => '',
        );

        foreach ($defaults as $key => $val) {
            $res = $this->db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."const WHERE name='".$this->db->escape($key)."' AND entity=".$e);
            if ($res && $this->db->num_rows($res) == 0) {
                dolibarr_set_const($this->db, $key, $val, 'chaine', 0, '', $e);
            }
        }

        return $this->_init($sql, $options);
    }

    public function remove($options = '')
    {
        return $this->_remove(array(), $options);
    }
}
