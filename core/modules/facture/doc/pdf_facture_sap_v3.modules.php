
<?php
// htdocs/custom/attestationsap/core/modules/facture/doc/pdf_facture_sap_v3.modules.php

require_once DOL_DOCUMENT_ROOT.'/core/modules/facture/doc/pdf_crabe.modules.php';

class pdf_facture_sap_v3 extends pdf_crabe
{
    public function __construct($db)
    {
        global $langs, $mysoc, $conf;
        parent::__construct($db);

        $this->name        = "facture_sap_v3";
        $this->description = "Modèle Services à la Personne V3 - RIB gauche / SAP droite";
        $this->type        = 'pdf';
        $this->page_largeur = 210;
        $this->page_hauteur = 297;
        $this->format       = array($this->page_largeur, $this->page_hauteur);

        // Désactiver complètement l'adresse de livraison/expédition
        if (!is_object($conf->global)) $conf->global = new stdClass();
        $conf->global->INVOICE_SHOW_SHIPPING_ADDRESS = 0;
    }

    public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
    {
        global $conf;

        // Forcer la désactivation de l'adresse de livraison et du texte libre pour ce modèle uniquement
        $old_shipping     = getDolGlobalInt('INVOICE_SHOW_SHIPPING_ADDRESS');
        $old_free         = getDolGlobalString('INVOICE_FREE_TEXT');
        $old_foot_details = getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS');
        $old_freetext_key = getDolGlobalString('INVOICE_FREE_TEXT');

        // Masquer tout ce qui n'est pas SAP sur ce modèle
        $conf->global->INVOICE_SHOW_SHIPPING_ADDRESS            = 0;
        $conf->global->INVOICE_FREE_TEXT                        = '';
        $conf->global->MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS = '';  // vide = désactivé

        // Appeler le parent
        $result = parent::write_file($object, $outputlangs, $srctemplatepath, $hidedetails, $hidedesc, $hideref);

        // Restaurer immédiatement
        if ($old_shipping !== null) $conf->global->INVOICE_SHOW_SHIPPING_ADDRESS = $old_shipping;
        $conf->global->INVOICE_FREE_TEXT                         = $old_free;
        $conf->global->MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS = $old_foot_details;

        return $result;
    }

    // Affichage des totaux + mention crédit d'impôt
    protected function _tableau_tot(&$pdf, $object, $deja_regle, $posy, $outputlangs, $outputlangsbis)
    {
        $posy = parent::_tableau_tot($pdf, $object, $deja_regle, $posy, $outputlangs, $outputlangsbis);

        $default_font_size = pdf_getPDFFontSize($outputlangs);
        $col1x = 120;
        $col2x = 170;
        if ($this->page_largeur < 210) { $col1x -= 15; $col2x -= 10; }
        $largcol2 = ($this->page_largeur - $this->marge_droite - $col2x);

        $sign = 1;
        if ($object->type == 2 && getDolGlobalString('INVOICE_POSITIVE_CREDIT_NOTE')) $sign = -1;

        $total_ttc = (isModEnabled("multicurrency") && $object->multicurrency_tx != 1)
                   ? $object->multicurrency_total_ttc : $object->total_ttc;

        // Ligne crédit d'impôt 50%
        $posy += 6;
        $pdf->SetFillColor(220, 255, 220);
        $pdf->SetFont('', 'B', $default_font_size - 1);
        $pdf->SetXY($col1x, $posy);
        $pdf->MultiCell($col2x - $col1x, 4, "Crédit d'impôt 50%", 1, 'L', true);
        $pdf->SetXY($col2x, $posy);
        $credit_impot = $sign * $total_ttc * 0.5;
        $pdf->MultiCell($largcol2, 4, price($credit_impot, 0, $outputlangs), 1, 'R', true);

        $posy += 5;
        $pdf->SetFont('', '', $default_font_size - 2);
        $pdf->SetXY($col1x, $posy);
        $pdf->MultiCell($this->page_largeur - $this->marge_droite - $col1x, 3,
            "Montant à déduire de vos impôts ou à vous faire rembourser si non imposable", 0, 'L', false);

        return $posy + 2;
    }

    // Informations diverses + cadre SAP (utilise ATTESTATIONSAP_LOGO si dispo)
    protected function _tableau_info(&$pdf, $object, $posy, $outputlangs, $outputlangsbis)
    {
        global $conf, $mysoc, $hookmanager;

        $default_font_size = pdf_getPDFFontSize($outputlangs);
        $pdf->SetFont('', '', $default_font_size - 1);

        $posy_start  = $posy;
        $page_width  = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
        $col_width   = $page_width / 2 - 2;

        // Conditions de paiement
        $left_x    = $this->marge_gauche;
        $current_y = $posy;

        if ($object->type != 2 && ($object->cond_reglement_code || $object->cond_reglement)) {
            $pdf->SetFont('', 'B', $default_font_size - 2);
            $pdf->SetXY($left_x, $current_y);
            $titre = $outputlangs->transnoentities("PaymentConditions").':';
            $pdf->MultiCell(43, 4, $titre, 0, 'L');
            $pdf->SetFont('', '', $default_font_size - 2);
            $pdf->SetXY($left_x + 43, $current_y);
            $label = ($outputlangs->transnoentities("PaymentCondition".$object->cond_reglement_code) != 'PaymentCondition'.$object->cond_reglement_code)
                ? $outputlangs->transnoentities("PaymentCondition".$object->cond_reglement_code)
                : $outputlangs->convToOutputCharset($object->cond_reglement_doc ? $object->cond_reglement_doc : $object->cond_reglement_label);
            $label = str_replace('\n', "\n", $label);
            $pdf->MultiCell($col_width - 43, 4, $label, 0, 'L');
            $current_y = $pdf->GetY() + 2;
        }

        // RIB en colonne gauche (si VIR)
        if ($object->type != 2) {
            if (empty($object->mode_reglement_code) || $object->mode_reglement_code == 'VIR') {
                if ($object->fk_account > 0 || $object->fk_bank > 0 || getDolGlobalInt('FACTURE_RIB_NUMBER')) {
                    $bankid = ($object->fk_account <= 0 ? getDolGlobalString('FACTURE_RIB_NUMBER') : $object->fk_account);
                    if ($object->fk_bank > 0) $bankid = $object->fk_bank;
                    $account = new Account($this->db);
                    $account->fetch($bankid);
                    $posy_after_rib = pdf_bank($pdf, $outputlangs, $left_x, $current_y, $account, 0, $default_font_size);
                    $current_y = $posy_after_rib;
                }
            }
        }

        // Cadre SAP en colonne gauche (sous le RIB)
        $sap_x      = $this->marge_gauche;
        $sap_y      = $current_y + 4;
        $sap_width  = $page_width / 2 - 2;  // moitié gauche
        $logo_width = 14;
        $logo_x     = $sap_x + 2;
        $logo_y     = $sap_y + 2;

        // Trouver le logo SAP : priorité à ATTESTATIONSAP_LOGO
        $logo_sap = '';
        $rel = getDolGlobalString('ATTESTATIONSAP_LOGO', '');
        if (!empty($rel)) {
            $logo_sap = DOL_DATA_ROOT.'/'.$rel; // ex: mycompany/logos/logo-sap.png
        } else {
            // fallback mycompany/logos
            $logodir = !empty($conf->mycompany->multidir_output[$object->entity])
                ? $conf->mycompany->multidir_output[$object->entity]
                : $conf->mycompany->dir_output;
            if (is_readable($logodir.'/logos/logo-sap.jpg'))  $logo_sap = $logodir.'/logos/logo-sap.jpg';
            elseif (is_readable($logodir.'/logos/logo-sap.png')) $logo_sap = $logodir.'/logos/logo-sap.png';
            // fallback module
            elseif (is_readable(DOL_DOCUMENT_ROOT.'/custom/attestationsap/img/logo-sap.jpg'))  $logo_sap = DOL_DOCUMENT_ROOT.'/custom/attestationsap/img/logo-sap.jpg';
            elseif (is_readable(DOL_DOCUMENT_ROOT.'/custom/attestationsap/img/logo-sap.png'))  $logo_sap = DOL_DOCUMENT_ROOT.'/custom/attestationsap/img/logo-sap.png';
        }

        // Hauteur du cadre
        $hauteur_cadre = 26;

        // Encadrer toute la largeur
        $pdf->SetDrawColor(0, 0, 100);
        $pdf->SetLineWidth(0.4);
        $pdf->RoundedRect($sap_x, $sap_y, $sap_width, $hauteur_cadre, 2, '1234', 'D');

        // Logo SAP à gauche
        if (!empty($logo_sap) && is_readable($logo_sap)) {
            $pdf->Image($logo_sap, $logo_x, $logo_y, $logo_width, 0);
        }

        // Titre
        $titre_x = $logo_x + $logo_width + 2;
        $pdf->SetFont('', 'B', 6.5);
        $pdf->SetXY($titre_x, $logo_y + 1.5);
        $pdf->SetTextColor(0, 0, 100);
        $titre_width = $sap_width - ($titre_x - $sap_x) - 2;
        $pdf->MultiCell($titre_width, 4, "MENTIONS OBLIGATOIRES SERVICES À LA PERSONNE", 0, 'L');

        // Mentions
        $mentions_x = $titre_x;
        $mentions_y = $sap_y + 7;
        $mentions_width = $sap_width - ($titre_x - $sap_x) - 3;

        $pdf->SetFont('', '', 5.8);
        $pdf->SetTextColor(0, 0, 0);

        $declaration_sap_value = getDolGlobalString('ATTESTATIONSAP_DECL_NUM', empty($this->emetteur->idprof8) ? '' : $this->emetteur->idprof8);
        $nature_service  = getDolGlobalString('ATTESTATIONSAP_NATURE_SERVICE', 'Services à la personne');
        $mode_interv     = getDolGlobalString('ATTESTATIONSAP_MODE', 'prestataire') === 'mandataire' ? 'Mode mandataire' : 'Mode prestataire';
        $complement      = ($nature_service ? ' - '.$nature_service : '').' - '.$mode_interv.' - Les interventions ont lieu au domicile du client';

        $pdf->SetXY($mentions_x, $mentions_y);
        $pdf->MultiCell($mentions_width, 3.5, "Déclaration SAP : ".$declaration_sap_value.$complement, 0, 'L');
        $pdf->SetX($mentions_x);
        $pdf->MultiCell($mentions_width, 3.5, "50% des sommes versées ouvrent droit à crédit d'impôt (art. 199 sexdecies du CGI)", 0, 'L');
        $pdf->SetX($mentions_x);
        $pdf->MultiCell($mentions_width, 3.5, "Conservez cette attestation fiscale pour votre déclaration de revenus", 0, 'L');
        $pdf->SetX($mentions_x);
        $pdf->MultiCell($mentions_width, 3.5, "TVA non applicable - Article 293 B du CGI", 0, 'L');

        return $sap_y + $hauteur_cadre + 2; // La colonne droite (totaux) est gérée par le parent
    }

    // Override _pagefoot : masquer texte libre et détails entreprise, garder numéro de page
    protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0, $heightforqrinvoice = 0)
    {
        global $conf;

        // Sauvegarder et neutraliser temporairement
        $old_free    = getDolGlobalString('INVOICE_FREE_TEXT');
        $old_details = getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS');
        $conf->global->INVOICE_FREE_TEXT                         = '';
        $conf->global->MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS = 0;

        // Passer null comme émetteur pour masquer line3/line4 (idprof inconditionnels)
        $result = pdf_pagefoot($pdf, $outputlangs, '', null,
            $heightforqrinvoice + $this->marge_basse,
            $this->marge_gauche,
            $this->page_hauteur,
            $object,
            0,    // showdetails = 0
            1,    // hidefreetext = 1
            $this->page_largeur,
            $this->watermark
        );

        // Restaurer
        $conf->global->INVOICE_FREE_TEXT                         = $old_free;
        $conf->global->MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS = $old_details;

        return $result;
    }

}
