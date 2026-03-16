
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
        $old_shipping = getDolGlobalInt('INVOICE_SHOW_SHIPPING_ADDRESS');
        $old_free     = getDolGlobalString('INVOICE_FREE_TEXT');

        $conf->global->INVOICE_SHOW_SHIPPING_ADDRESS = 0;
        $conf->global->INVOICE_FREE_TEXT             = '';

        // Appeler le parent
        $result = parent::write_file($object, $outputlangs, $srctemplatepath, $hidedetails, $hidedesc, $hideref);

        // Restaurer immédiatement
        if ($old_shipping !== null) $conf->global->INVOICE_SHOW_SHIPPING_ADDRESS = $old_shipping;
        $conf->global->INVOICE_FREE_TEXT = $old_free;

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

        // Cadre SAP en pleine largeur
        $sap_x      = $this->marge_gauche;
        $sap_y      = $current_y + 6;
        $sap_width  = $page_width;
        $logo_width = 18;
        $logo_x     = $sap_x + 2;
        $logo_y     = $sap_y + 1.5;

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
        $hauteur_cadre = 24;

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
        $pdf->SetFont('', 'B', 8);
        $pdf->SetXY($titre_x, $logo_y + 1.5);
        $pdf->SetTextColor(0, 0, 100);
        $pdf->Cell(0, 4, "MENTIONS OBLIGATOIRES SERVICES À LA PERSONNE", 0, 1, 'L');

        // Mentions
        $mentions_x = $titre_x;
        $mentions_y = $sap_y + 7;
        $mentions_width = $sap_width - ($titre_x - $sap_x) - 2;

        $pdf->SetFont('', '', 6.5);
        $pdf->SetTextColor(0, 0, 0);

        $declaration_sap_value = empty($this->emetteur->idprof8) ? 'Non définie (Id. prof. 8)' : $this->emetteur->idprof8;
        $complement = ' - Assistance informatique à domicile mode prestataire - Les interventions ont lieu au domicile du client';

        $pdf->SetXY($mentions_x, $mentions_y);
        $pdf->Cell($mentions_width, 3.5, "Déclaration SAP : ".$declaration_sap_value . $complement, 0, 0, 'L');

        $pdf->SetXY($mentions_x, $mentions_y + 4);
        $pdf->Cell($mentions_width, 3.5, "50% des sommes versées ouvrent droit à crédit d'impôt (art. 199 sexdecies du CGI)", 0, 0, 'L');

        $pdf->SetXY($mentions_x, $mentions_y + 8);
        $pdf->Cell($mentions_width, 3.5, "Conservez cette attestation fiscale pour votre déclaration de revenus", 0, 0, 'L');

        $pdf->SetXY($mentions_x, $mentions_y + 12);
        $pdf->Cell($mentions_width, 3.5, "TVA non applicable - Article 293 B du CGI", 0, 0, 'L');

        return $sap_y + $hauteur_cadre + 2;
    }

    protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0, $heightforqrinvoice = 0)
    {
        global $conf;
        $showdetails = getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS', 0);

        $marge_basse = $this->marge_basse + 8;
        $line = $this->page_hauteur - $marge_basse;

        // Détails entreprise (condensés)
        if ($showdetails) {
            $pdf->SetFont('', '', 6.5);
            $pdf->SetY($line);
            $infos = '';
            if (!empty($this->emetteur->capital)) $infos .= $outputlangs->transnoentities("CapitalOf", price($this->emetteur->capital, 0, $outputlangs, 0, 0, -1, $conf->currency)).' • ';
            if (!empty($this->emetteur->tva_intra)) $infos .= $outputlangs->transnoentities("VATIntraShort").': '.$this->emetteur->tva_intra.' • ';
            if (!empty($this->emetteur->idprof1) && $this->emetteur->country_code == 'FR') $infos .= 'SIRET: '.$this->emetteur->idprof1.' • ';
            if (!empty($this->emetteur->idprof2)) $infos .= ($this->emetteur->country_code == 'FR' ? 'APE: ' : '').$this->emetteur->idprof2;
            if ($infos) {
                $pdf->SetX($this->marge_gauche);
                $pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 2, rtrim($infos, ' • '), 0, 'C', 0);
            }
        }

        // Numéro de page
        if (!$hidefreetext) {
            $pdf->SetY(-8);
            $pdf->SetFont('', 'I', 8);
            $pdf->SetX($this->marge_gauche);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 2, $outputlangs->transnoentities("Page").' '.$pdf->PageNo().'/\{nb\}', 0, 'C', 0);
        }

        if (!empty($this->watermark)) {
            pdf_watermark($pdf, $outputlangs, $this->page_hauteur, $this->page_largeur, 'mm', $this->watermark);
        }

        return $marge_basse;
    }
}
