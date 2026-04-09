
<?php
/**
 * \file htdocs/custom/attestationsap/core/modules/propale/doc/pdf_devis_sap_v2.modules.php
 * \ingroup propale
 * \brief Modèle de devis SAP (V2) basé sur Azur
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/propale/doc/pdf_azur.modules.php';

class pdf_devis_sap_v2 extends pdf_azur
{
    public $phpmin   = array(5, 6);
    public $version  = 'dolibarr';

    public function __construct($db)
    {
        global $conf, $langs, $mysoc;

        if (is_object($langs)) $langs->loadLangs(array("main","companies","propal","products","bills"));
        parent::__construct($db);

        // Identité du modèle
        $this->name        = 'devis_sap_v2';
        $this->description = 'Modèle Services à la Personne V2 (SAP)';

        // Format / marges
        $this->type          = 'pdf';
        $this->page_largeur  = 210;
        $this->page_hauteur  = 297;
        $this->format        = array($this->page_largeur, $this->page_hauteur);
        $this->marge_gauche  = 10;
        $this->marge_droite  = 10;
        $this->marge_haute   = 10;
        $this->marge_basse   = 10;

        // Options
        $this->option_logo      = 1;
        $this->option_multilang = 1;
        $this->option_escompte  = 0;
        $this->option_credit_note = 0;
        $this->option_freetext  = 0;
        $this->option_modereg   = 0;
        $this->option_condreg   = 0;

        if (!is_object($conf->global)) $conf->global = new stdClass();
        if (empty($mysoc->country_code)) $mysoc->country_code = 'FR';
    }

    /**
     * Génération du PDF (neutre les textes le temps du rendu puis restaure)
     */
    public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
    {
        global $conf, $langs;

        dol_syslog(__METHOD__." start for propal id=".$object->id." model=".$this->name, LOG_DEBUG);

        if (!is_object($outputlangs)) $outputlangs = $langs;
        $outputlangs->loadLangs(array("main","dict","companies","bills","propal","products"));

        // --- Autoriser le mode aperçu (id = 0) utilisé par admin/propal.php ---
        $isPreview = empty($object->id) || (!empty($object->context) && !empty($object->context['getpreview']));
        if ($isPreview) {
            // S'assurer que le flag de contexte est présent
            if (empty($object->context) || !is_array($object->context)) $object->context = array();
            if (empty($object->context['getpreview'])) $object->context['getpreview'] = 1;
            dol_syslog(__METHOD__." Preview mode: object has no id, continue", LOG_DEBUG);
            // Ne PAS retourner ici : laisser la classe parente gérer l’aperçu
        }

        // Neutraliser texte libre / conditions UNIQUEMENT pour ce modèle
        $old_free_text = getDolGlobalString('PROPALE_FREE_TEXT');
        $old_cond_reg  = getDolGlobalString('PROPALE_COND_REGLEMENT_TEXT');
        $conf->global->PROPALE_FREE_TEXT = '';
        $conf->global->PROPALE_COND_REGLEMENT_TEXT = '';

        // Appel parent
        $result = parent::write_file($object, $outputlangs, $srctemplatepath, $hidedetails, $hidedesc, $hideref);

        // Restaurer
        $conf->global->PROPALE_FREE_TEXT = $old_free_text;
        $conf->global->PROPALE_COND_REGLEMENT_TEXT = $old_cond_reg;

        dol_syslog(__METHOD__." end result=".$result, ($result>0)?LOG_INFO:LOG_ERR);
        return $result;
    }

    /**
     * Bloc des totaux : encart "Crédit d'impôt 50%"
     */
    protected function _tableau_tot(&$pdf, $object, $deja_regle, $posy, $outputlangs, $outputlangsbis = null)
    {
        $posy = parent::_tableau_tot($pdf, $object, $deja_regle, $posy, $outputlangs, $outputlangsbis);

        $default_font_size = pdf_getPDFFontSize($outputlangs);
        $col1x = 120; $col2x = 170;
        if ($this->page_largeur < 210) { $col1x -= 15; $col2x -= 10; }
        $largcol2 = ($this->page_largeur - $this->marge_droite - $col2x);

        $total_ttc = (isModEnabled("multicurrency") && !empty($object->multicurrency_tx) && $object->multicurrency_tx != 1)
            ? $object->multicurrency_total_ttc : $object->total_ttc;

        $posy += 6;
        $pdf->SetFillColor(220, 255, 220);
        $pdf->SetDrawColor(0, 150, 0);
        $pdf->SetLineWidth(0.3);
        $pdf->SetFont('', 'B', $default_font_size - 1);
        $pdf->SetXY($col1x, $posy);
        $pdf->MultiCell($col2x - $col1x, 5, "Crédit d'impôt 50%", 1, 'L', true);
        $pdf->SetXY($col2x, $posy);
        $pdf->MultiCell($largcol2, 5, price($total_ttc * 0.5, 0, $outputlangs), 1, 'R', true);

        $posy += 6;
        $pdf->SetFont('', 'I', $default_font_size - 2);
        $pdf->SetTextColor(0, 100, 0);
        $pdf->SetXY($col1x, $posy);
        $pdf->MultiCell($this->page_largeur - $this->marge_droite - $col1x, 3,
            "Montant à déduire de vos impôts ou à vous faire rembourser si non imposable", 0, 'L', false);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor(0, 0, 0);

        return $posy + 3;
    }

    /**
     * Cadre SAP (colonne gauche) avec logo via constante ATTESTATIONSAP_LOGO
     */
    protected function _tableau_info(&$pdf, $object, $posy, $outputlangs)
    {
        global $conf, $mysoc;

        $default_font_size = pdf_getPDFFontSize($outputlangs);
        $pdf->SetFont('', '', $default_font_size - 1);
        $page_width = $this->page_largeur - $this->marge_gauche - $this->marge_droite;

        // Durée de validité
        $left_x    = $this->marge_gauche;
        $current_y = $posy;
        if (!empty($object->duree_validite) && $object->duree_validite > 0) {
            $pdf->SetFont('', 'B', $default_font_size - 2);
            $pdf->SetXY($left_x, $current_y);
            $titre = $outputlangs->transnoentities("ValidityDuration").':';
            $pdf->MultiCell(43, 4, $titre, 0, 'L');
            $pdf->SetFont('', '', $default_font_size - 2);
            $pdf->SetXY($left_x + 43, $current_y);
            $pdf->MultiCell(100, 4, $object->duree_validite.' '.$outputlangs->transnoentities("days"), 0, 'L');
            $current_y = $pdf->GetY() + 2;
        }

        // RIB en colonne gauche (si VIR)
        if (empty($object->mode_reglement_code) || $object->mode_reglement_code == 'VIR') {
            if ($object->fk_account > 0 || getDolGlobalInt('FACTURE_RIB_NUMBER')) {
                $bankid = ($object->fk_account <= 0 ? getDolGlobalString('FACTURE_RIB_NUMBER') : $object->fk_account);
                $account = new Account($this->db);
                $account->fetch($bankid);
                $posy_after_rib = pdf_bank($pdf, $outputlangs, $left_x, $current_y, $account, 0, $default_font_size);
                $current_y = $posy_after_rib + 2;
            }
        }

        // Cadre SAP
        $col_width = $page_width / 2 - 2;
        $sap_x     = $this->marge_gauche;
        $sap_y     = $current_y + 2;
        $sap_width = $col_width;
        $logo_width = 15;
        $logo_x     = $sap_x + 2;
        $logo_y     = $sap_y + 2;

        // Logo SAP (constante prioritaire, fallbacks robustes)
        $logo_sap = '';
        $rel = getDolGlobalString('ATTESTATIONSAP_LOGO', '');
        if (!empty($rel) && is_readable(DOL_DATA_ROOT.'/'.$rel)) {
            $logo_sap = DOL_DATA_ROOT.'/'.$rel;
        } else {
            // Fallback même en preview où $object->entity peut être absent
            $entity  = isset($object->entity) ? $object->entity : (isset($conf->entity) ? $conf->entity : 1);
            $logodir = !empty($conf->mycompany->multidir_output[$entity]) ? $conf->mycompany->multidir_output[$entity] : $conf->mycompany->dir_output;

            // Logo officiel SAP livré avec le module (priorité 1)
            if (is_readable(DOL_DOCUMENT_ROOT.'/custom/attestationsap/img/logo-sap.jpg'))     $logo_sap = DOL_DOCUMENT_ROOT.'/custom/attestationsap/img/logo-sap.jpg';
            elseif (is_readable(DOL_DOCUMENT_ROOT.'/custom/attestationsap/img/logo-sap.png')) $logo_sap = DOL_DOCUMENT_ROOT.'/custom/attestationsap/img/logo-sap.png';
            // Logo personnalisé mycompany (priorité 2)
            elseif (is_readable($logodir.'/logos/logo-sap.jpg'))  $logo_sap = $logodir.'/logos/logo-sap.jpg';
            elseif (is_readable($logodir.'/logos/logo-sap.png'))  $logo_sap = $logodir.'/logos/logo-sap.png';
        }

        $hauteur_cadre = 28;
        $pdf->SetDrawColor(0, 0, 100);
        $pdf->SetLineWidth(0.4);
        $pdf->RoundedRect($sap_x, $sap_y, $sap_width, $hauteur_cadre, 2, '1234', 'D');

        if (!empty($logo_sap) && is_readable($logo_sap)) {
            $pdf->Image($logo_sap, $logo_x, $logo_y, $logo_width, 0, '', '', '', false, 300, '', false, false, 0);
        }

        $titre_x = $logo_x + $logo_width + 2;
        $pdf->SetFont('', 'B', 7);
        $pdf->SetXY($titre_x, $logo_y + 2);
        $pdf->SetTextColor(0, 0, 100);
        $pdf->MultiCell($sap_width - ($titre_x - $sap_x) - 2, 3, "MENTIONS OBLIGATOIRES\nSERVICES À LA PERSONNE", 0, 'L');

        // Mentions
        $mentions_x = $titre_x;  // Après le logo
        $mentions_y = $logo_y + 9;
        $mentions_w = $sap_width - ($titre_x - $sap_x) - 2;
        $pdf->SetFont('', '', 6.5);
        $pdf->SetTextColor(0, 0, 0);
        $num_sap      = getDolGlobalString('ATTESTATIONSAP_DECL_NUM', !empty($mysoc->idprof8) ? $mysoc->idprof8 : '');
        $nature_sap   = getDolGlobalString('ATTESTATIONSAP_NATURE_SERVICE', 'Services à la personne');
        $mode_sap     = getDolGlobalString('ATTESTATIONSAP_MODE', 'prestataire') === 'mandataire' ? 'Mode mandataire' : 'Mode prestataire';
        $pdf->SetXY($mentions_x, $mentions_y);
        $pdf->MultiCell($mentions_w, 3.5, "Déclaration SAP : ".$num_sap.($nature_sap ? ' - '.$nature_sap : '').' - '.$mode_sap, 0, 'L');
        $pdf->SetX($mentions_x); $pdf->Cell($mentions_w, 3.5, "Les interventions ont lieu au domicile du client", 0, 2, 'L');
        $pdf->SetX($mentions_x); $pdf->Cell($mentions_w, 3.5, "50% ouvrent droit à crédit d'impôt (art. 199 sexdecies du CGI)", 0, 2, 'L');
        $pdf->SetX($mentions_x); $pdf->Cell($mentions_w, 3.5, "TVA non applicable - Article 293 B du CGI", 0, 0, 'L');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor(0, 0, 0);

        return $sap_y + $hauteur_cadre + 4;
    }

    protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0)
    {
        // Déléguer à pdf_pagefoot avec émetteur vide pour masquer idprof/détails
        // AliasNbPages() est appelé après par pdf_azur → {nb} résolu automatiquement
        return pdf_pagefoot($pdf, $outputlangs, '', null,
            $this->marge_basse,
            $this->marge_gauche,
            $this->page_hauteur,
            $object,
            0,    // showdetails = 0
            1,    // hidefreetext = 1 (pas de texte libre propale)
            $this->page_largeur,
            $this->watermark
        );
    }
}
