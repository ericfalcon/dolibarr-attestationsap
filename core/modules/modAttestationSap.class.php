
<?php
// htdocs/custom/attestationsap/core/modules/modAttestationSap.class.php

require_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

/**
 * Descripteur du module "AttestationSap"
 * - Gestion des attestations fiscales SAP (Services à la Personne)
 * - Installe les modèles PDF (devis/facture), menus, hooks, docmodels
 */
class modAttestationSap extends DolibarrModules
{
    public function __construct($db)
    {
        global $conf;
        $this->db = $db;

        // --- Identité du module ---
        $this->numero          = 500100;  // ID interne (unique)
        $this->rights_class    = 'attestationsap';
        $this->family          = 'financial';
        $this->module_position = '90';
        $this->name            = 'AttestationSap'; // MAIN_MODULE_ATTESTATIONSAP
        $this->description     = "Module de gestion des attestations fiscales SAP (Services à la Personne)";
        $this->descriptionlong = "Génération d'attestations fiscales conformément à l'article 199 sexdecies du CGI";
        $this->version         = '1.0.0';
        $this->const_name      = 'MAIN_MODULE_' . strtoupper($this->name);
        $this->picto           = 'generic';

        // --- Compatibilité ---
        $this->phpmin                = array(7, 0);
        $this->need_dolibarr_version = array(14, 0); // fonctionne 14 → 22+

        // --- Activation de parties du module ---
        $this->module_parts = array(
            'hooks'     => array('invoicecard', 'propalcard', 'pdfgeneration', 'formbuilddoc'),
            'models'    => 1, // autorise docmodels
            'docmodels' => array(
                'propal'  => array('class' => '/custom/attestationsap/core/modules/propale/doc/'),
                'invoice' => array('class' => '/custom/attestationsap/core/modules/facture/doc/'),
            ),
        );

        // --- Répertoires de données (créés à l'activation) ---
        $this->dirs = array(
            '/attestationsap',
            '/attestationsap/temp'
        );

        // --- Page de configuration ---
        $this->config_page_url = array('setup.php@attestationsap');

        // --- Dépendances ---
        $this->hidden       = false;
        $this->depends      = array('modFacture', 'modPropale', 'modSociete');
        $this->requiredby   = array();
        $this->conflictwith = array();

        // --- Traductions ---
        $this->langfiles = array('attestationsap@attestationsap');

        // --- Droits ---
        $this->rights = array();
        $r = 0;

        $r++;
        $this->rights[$r][0] = $this->numero . $r;
        $this->rights[$r][1] = 'Lire les attestations SAP';
        $this->rights[$r][4] = 'read';

        $r++;
        $this->rights[$r][0] = $this->numero . $r;
        $this->rights[$r][1] = 'Générer les attestations SAP';
        $this->rights[$r][4] = 'generate';

        // --- Menus ---
        $this->menu = array(); $m = 0;

        // TOP: SAP
        $this->menu[$m++] = array(
            'fk_menu'   => '',
            'type'      => 'top',
            'titre'     => 'SAP',
            'prefix'    => img_picto('', 'generic', 'class="paddingright pictofixedwidth valignmiddle"'),
            'mainmenu'  => 'attestationsap',
            'leftmenu'  => '',
            'url'       => '/custom/attestationsap/index.php',
            'langs'     => 'attestationsap@attestationsap',
            'position'  => 1000,
            'enabled'   => '$conf->attestationsap->enabled',
            'perms'     => '$user->rights->attestationsap->read',
            'target'    => '',
            'user'      => 2
        );

        // LEFT: Tableau de bord
        $this->menu[$m++] = array(
            'fk_menu'   => 'fk_mainmenu=attestationsap',
            'type'      => 'left',
            'titre'     => 'Tableau de bord',
            'prefix'    => img_picto('', 'object_home', 'class="paddingright pictofixedwidth valignmiddle"'),
            'mainmenu'  => 'attestationsap',
            'leftmenu'  => 'attestationsap_dashboard',
            'url'       => '/custom/attestationsap/index.php?tab=dashboard',
            'langs'     => 'attestationsap@attestationsap',
            'position'  => 95,
            'enabled'   => '$conf->attestationsap->enabled',
            'perms'     => '$user->rights->attestationsap->read',
            'target'    => '',
            'user'      => 2
        );

        // LEFT: Créer un devis SAP
        $this->menu[$m++] = array(
            'fk_menu'   => 'fk_mainmenu=attestationsap',
            'type'      => 'left',
            'titre'     => 'Créer un devis SAP',
            'prefix'    => img_picto('', 'object_propal', 'class="paddingright pictofixedwidth valignmiddle"'),
            'mainmenu'  => 'attestationsap',
            'leftmenu'  => 'attestationsap_devis',
            'url'       => '/comm/propal/card.php?action=create'
                           .'&sap_mode=1'
                           .'&model=devis_sap_v2'
                           .'&modelpdf=devis_sap_v2'
                           .'&doctemplate=',
            'langs'     => 'attestationsap@attestationsap',
            'position'  => 100,
            'enabled'   => '$conf->propal->enabled',
            'perms'     => '$user->rights->propal->creer',
            'target'    => '',
            'user'      => 2
        );

        // LEFT: Créer une facture SAP
        $this->menu[$m++] = array(
            'fk_menu'   => 'fk_mainmenu=attestationsap',
            'type'      => 'left',
            'titre'     => 'Créer une facture SAP',
            'prefix'    => img_picto('', 'object_invoice', 'class="paddingright pictofixedwidth valignmiddle"'),
            'mainmenu'  => 'attestationsap',
            'leftmenu'  => 'attestationsap_facture',
            'url'       => '/compta/facture/card.php?action=create'
                           .'&sap_mode=1'
                           .'&model=facture_sap_v3'
                           .'&modelpdf=facture_sap_v3'
                           .'&doctemplate=',
            'langs'     => 'attestationsap@attestationsap',
            'position'  => 110,
            'enabled'   => '$conf->facture->enabled',
            'perms'     => '$user->rights->facture->creer',
            'target'    => '',
            'user'      => 2
        );

        // LEFT: Générer une attestation fiscale
        $this->menu[$m++] = array(
            'fk_menu'   => 'fk_mainmenu=attestationsap',
            'type'      => 'left',
            'titre'     => 'Générer une attestation fiscale',
            'prefix'    => img_picto('', 'object_pdf', 'class="paddingright pictofixedwidth valignmiddle"'),
            'mainmenu'  => 'attestationsap',
            'leftmenu'  => 'attestationsap_generate',
            'url'       => '/custom/attestationsap/index.php?tab=generate',
            'langs'     => 'attestationsap@attestationsap',
            'position'  => 120,
            'enabled'   => '$conf->attestationsap->enabled',
            'perms'     => '$user->rights->attestationsap->generate',
            'target'    => '',
            'user'      => 2
        );

        // LEFT: Paramètres SAP
        $this->menu[$m++] = array(
            'fk_menu'   => 'fk_mainmenu=attestationsap',
            'type'      => 'left',
            'titre'     => 'Paramètres SAP',
            'prefix'    => img_picto('', 'setup', 'class="paddingright pictofixedwidth valignmiddle"'),
            'mainmenu'  => 'attestationsap',
            'leftmenu'  => 'attestationsap_setup',
            'url'       => '/custom/attestationsap/setup.php',
            'langs'     => 'attestationsap@attestationsap',
            'position'  => 130,
            'enabled'   => '$conf->attestationsap->enabled',
            'perms'     => '$user->admin',
            'target'    => '',
            'user'      => 2
        );

        // LEFT: Mode d'emploi
        $this->menu[$m++] = array(
            'fk_menu'   => 'fk_mainmenu=attestationsap',
            'type'      => 'left',
            'titre'     => 'Mode d\'emploi',
            'prefix'    => img_picto('', 'help', 'class="paddingright pictofixedwidth valignmiddle"'),
            'mainmenu'  => 'attestationsap',
            'leftmenu'  => 'attestationsap_help',
            'url'       => '/custom/attestationsap/help.php',
            'langs'     => 'attestationsap@attestationsap',
            'position'  => 140,
            'enabled'   => '$conf->attestationsap->enabled',
            'perms'     => '$user->rights->attestationsap->read',
            'target'    => '',
            'user'      => 2
        );
    }

    /**
     * Installation du module
     */
    public function init($options = '')
    {
        global $conf;

        // Charger tables SQL si présentes (silencieux si aucun fichier)
        $result = $this->_load_tables('/attestationsap/sql/');
        if ($result < 0) return -1;

        // Répertoires de données (créés automatiquement par le core sur _init)
        $sql = array();

        // Enregistrer les modèles doc (idempotent)
        $e = (int) $conf->entity;

        // Devis SAP (V1)
        // ---- Nettoyage des entrées avec suffixes ':...' (ex: 'facture_sap_v3:aucun') ----
        // Ces suffixes apparaissent quand Dolibarr ne trouve pas le fichier PHP au moment du scan
        $sql[] = "DELETE FROM ".MAIN_DB_PREFIX."document_model
                  WHERE entity = ".$e."
                  AND type = 'invoice'
                  AND nom LIKE 'facture_sap%'
                  AND nom LIKE '%:%'";
        $sql[] = "DELETE FROM ".MAIN_DB_PREFIX."document_model
                  WHERE entity = ".$e."
                  AND type = 'invoice'
                  AND nom NOT IN ('facture_sap_v3')
                  AND nom LIKE 'facture_sap%'";

        $sql[] = "INSERT INTO ".MAIN_DB_PREFIX."document_model (nom, entity, type, libelle, description)
                  SELECT 'devis_sap', ".$e.", 'propal', 'devis_sap', 'Modèle devis SAP'
                  WHERE NOT EXISTS (
                    SELECT 1 FROM ".MAIN_DB_PREFIX."document_model
                    WHERE nom='devis_sap' AND entity=".$e." AND type='propal'
                  )";

        // Devis SAP V2
        $sql[] = "INSERT INTO ".MAIN_DB_PREFIX."document_model (nom, entity, type, libelle, description)
                  SELECT 'devis_sap_v2', ".$e.", 'propal', 'devis_sap_v2', 'Modèle devis SAP V2'
                  WHERE NOT EXISTS (
                    SELECT 1 FROM ".MAIN_DB_PREFIX."document_model
                    WHERE nom='devis_sap_v2' AND entity=".$e." AND type='propal'
                  )";

        // Facture SAP V3
        $sql[] = "INSERT INTO ".MAIN_DB_PREFIX."document_model (nom, entity, type, libelle, description)
                  SELECT 'facture_sap_v3', ".$e.", 'invoice', 'facture_sap_v3', 'Modèle facture SAP v3'
                  WHERE NOT EXISTS (
                    SELECT 1 FROM ".MAIN_DB_PREFIX."document_model
                    WHERE nom='facture_sap_v3' AND entity=".$e." AND type='invoice'
                  )";

        // Constantes de confort (idempotentes)
        // - Dossier de sortie fallback (si non configuré par setup)
        // - Modèle de facture utilisé par l’attestation (par défaut facture_sap_v3)
        $docroot = $conf->dolibarr_main_document_root.'/attestationsap';
        $sql[] = "INSERT INTO ".MAIN_DB_PREFIX."const (name, value, type, note, visible, entity)
                  SELECT 'ATTESTATIONSAP_OUTPUTDIR', '".$this->db->escape($docroot)."', 'chaine', 'Répertoire de sortie des attestations', 0, ".$e."
                  WHERE NOT EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."const WHERE name='ATTESTATIONSAP_OUTPUTDIR' AND entity=".$e.")";
        $sql[] = "INSERT INTO ".MAIN_DB_PREFIX."const (name, value, type, note, visible, entity)
                  SELECT 'ATTESTATIONSAP_FACTURE_MODEL_NAME', 'facture_sap_v3', 'chaine', 'Modèle de facture SAP utilisé', 0, ".$e."
                  WHERE NOT EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."const WHERE name='ATTESTATIONSAP_FACTURE_MODEL_NAME' AND entity=".$e.")";

        return $this->_init($sql, $options);
    }

    /**
     * Désinstallation du module
     */
    public function remove($options = '')
    {
        // Pas de suppression agressive (on laisse docmodels et constantes)
        return $this->_remove(array(), $options);
    }
}
