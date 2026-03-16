<?php
/**
 * \file        htdocs/custom/attestationsap/core/modules/facture/doc/pdf_facture_sap_v3.modules.php
 * \ingroup     attestationsap
 * \brief       Modèle PDF facture Services à la Personne V3
 * \version     2.1.0
 *
 * Affiche dans le cadre SAP obligatoire :
 *  - N° déclaration/agrément SAP
 *  - Nature du service + mode d'intervention
 *  - Nom de l'intervenant (depuis SapIntervenants : user Dolibarr ou texte libre)
 *  - Crédit d'impôt 50% (art. 199 sexdecies CGI)
 *  - TVA non applicable (art. 293 B CGI)
 */

require_once DOL_DOCUMENT_ROOT . '/core/modules/facture/doc/pdf_crabe.modules.php';
require_once DOL_DOCUMENT_ROOT . '/custom/attestationsap/class/SapIntervenants.class.php';

class pdf_facture_sap_v3 extends pdf_crabe
{
    public function __construct($db)
    {
        global $conf;

        parent::__construct($db);

        $this->name        = 'facture_sap_v3';
        $this->description = 'Facture Services à la Personne V3 — Crédit d\'impôt 50 % + intervenant + mentions SAP';
        $this->type        = 'pdf';
        $this->page_largeur = 210;
        $this->page_hauteur = 297;
        $this->format       = array($this->page_largeur, $this->page_hauteur);

        if (!is_object($conf->global)) $conf->global = new stdClass();
        $conf->global->INVOICE_SHOW_SHIPPING_ADDRESS = 0;
    }

    /**
     * Génération du PDF
     */
    public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
    {
        global $conf;

        $saved = array(
            'INVOICE_SHOW_SHIPPING_ADDRESS' => getDolGlobalInt('INVOICE_SHOW_SHIPPING_ADDRESS'),
            'INVOICE_FREE_TEXT'             => getDolGlobalString('INVOICE_FREE_TEXT'),
        );
        $conf->global->INVOICE_SHOW_SHIPPING_ADDRESS = 0;
        $conf->global->INVOICE_FREE_TEXT             = '';

        $result = parent::write_file($object, $outputlangs, $srctemplatepath, $hidedetails, $hidedesc, $hideref);

        foreach ($saved as $k => $v) $conf->global->$k = $v;

        return $result;
    }

    /**
     * Section totaux + ligne crédit d'impôt SAP
     */
    protected function _tableau_tot(&$pdf, $object, $deja_regle, $posy, $outputlangs, $outputlangsbis)
    {
        $posy = parent::_tableau_tot($pdf, $object, $deja_regle, $posy, $outputlangs, $outputlangsbis);

        if (!getDolGlobalInt('ATTESTATIONSAP_SHOW_CREDIT_IMPOT', 1)) return $posy;

        $default_font_size = pdf_getPDFFontSize($outputlangs);
        $col1x    = 120;
        $col2x    = 170;
        if ($this->page_largeur < 210) { $col1x -= 15; $col2x -= 10; }
        $largcol2 = $this->page_largeur - $this->marge_droite - $col2x;

        $sign = ($object->type == 2 && getDolGlobalString('INVOICE_POSITIVE_CREDIT_NOTE')) ? -1 : 1;

        $total_ttc = (isModEnabled('multicurrency') && $object->multicurrency_tx != 1)
            ? $object->multicurrency_total_ttc
            : $object->total_ttc;

        $credit_impot = $sign * (float)$total_ttc * 0.5;

        $posy += 2;
        $pdf->SetDrawColor(160, 200, 160);
        $pdf->SetLineWidth(0.3);
        $pdf->Line($col1x, $posy, $this->page_largeur - $this->marge_droite, $posy);
        $posy += 2;

        $pdf->SetFillColor(228, 255, 228);
        $pdf->SetDrawColor(100, 175, 100);
        $pdf->SetFont('', 'B', $default_font_size - 0.5);
        $pdf->SetTextColor(0, 100, 0);
        $pdf->SetXY($col1x, $posy);
        $pdf->MultiCell($col2x - $col1x, 5, "Crédit d'impôt 50 %", 1, 'L', true);
        $pdf->SetXY($col2x, $posy);
        $pdf->MultiCell($largcol2, 5, price($credit_impot, 0, $outputlangs), 1, 'R', true);
        $pdf->SetTextColor(0, 0, 0);

        $posy += 5;
        $pdf->SetFont('', 'I', $default_font_size - 2.5);
        $pdf->SetXY($col1x, $posy);
        $pdf->MultiCell(
            $this->page_largeur - $this->marge_droite - $col1x,
            3.5,
            'Art. 199 sexdecies CGI — À déduire de vos impôts ou à rembourser si non imposable',
            0, 'L'
        );

        return $posy + 4;
    }

    /**
     * Bloc informations bas de facture : RIB + cadre SAP avec intervenant
     */
    protected function _tableau_info(&$pdf, $object, $posy, $outputlangs, $outputlangsbis)
    {
        global $conf, $mysoc;

        $default_font_size = pdf_getPDFFontSize($outputlangs);
        $page_width        = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
        $left_x            = $this->marge_gauche;
        $current_y         = $posy;

        // ---- Conditions de paiement ----
        if ($object->type != 2 && ($object->cond_reglement_code || $object->cond_reglement)) {
            $pdf->SetFont('', 'B', $default_font_size - 2);
            $pdf->SetXY($left_x, $current_y);
            $pdf->MultiCell(50, 4, $outputlangs->transnoentities('PaymentConditions') . ':', 0, 'L');
            $pdf->SetFont('', '', $default_font_size - 2);
            $pdf->SetXY($left_x + 50, $current_y);
            $label = ($outputlangs->transnoentities('PaymentCondition' . $object->cond_reglement_code) != 'PaymentCondition' . $object->cond_reglement_code)
                ? $outputlangs->transnoentities('PaymentCondition' . $object->cond_reglement_code)
                : $outputlangs->convToOutputCharset($object->cond_reglement_doc ?: $object->cond_reglement_label);
            $pdf->MultiCell($page_width / 2 - 50, 4, str_replace('\n', "\n", $label), 0, 'L');
            $current_y = $pdf->GetY() + 2;
        }

        // ---- RIB (si virement) ----
        if ($object->type != 2) {
            $modeReg = empty($object->mode_reglement_code) ? 'VIR' : $object->mode_reglement_code;
            if ($modeReg === 'VIR') {
                $bankid = ($object->fk_account > 0) ? $object->fk_account : getDolGlobalString('FACTURE_RIB_NUMBER');
                if ($object->fk_bank > 0) $bankid = $object->fk_bank;
                if (!empty($bankid)) {
                    require_once DOL_DOCUMENT_ROOT . '/compta/bank/class/account.class.php';
                    $account = new Account($this->db);
                    if ($account->fetch($bankid) > 0) {
                        $current_y = pdf_bank($pdf, $outputlangs, $left_x, $current_y, $account, 0, $default_font_size);
                        $current_y += 4;
                    }
                }
            }
        }

        // ---- Intervenant SAP ----
        $sapInter    = new SapIntervenants($this->db);
        $intervenants = $sapInter->getIntervenantsForFacture($object->id, $conf->entity);
        $interStr    = !empty($intervenants) ? implode(', ', $intervenants) : '';

        // ---- Cadre SAP obligatoire ----
        $sap_y     = $current_y + 4;
        $sap_width = $page_width;

        // Hauteur du cadre : +1 ligne si intervenant présent
        $hauteur = empty($interStr) ? 30 : 35;

        // Logo SAP
        $logo_sap = $this->_findLogoSap($object);
        $logo_w   = 18;
        $logo_x   = $left_x + 2;
        $logo_y   = $sap_y + 2;
        $text_x   = $left_x + ($logo_sap ? $logo_w + 6 : 4);
        $text_maxw = $sap_width - ($logo_sap ? $logo_w + 8 : 6);

        // Dessin du cadre
        $pdf->SetDrawColor(0, 60, 120);
        $pdf->SetFillColor(238, 244, 255);
        $pdf->SetLineWidth(0.5);
        $pdf->RoundedRect($left_x, $sap_y, $sap_width, $hauteur, 2.5, '1234', 'DF');

        if (!empty($logo_sap) && is_readable($logo_sap)) {
            $pdf->Image($logo_sap, $logo_x, $logo_y, $logo_w, 0, '', '', 'T', false, 150);
        }

        // Titre du cadre
        $pdf->SetFont('', 'B', 7.5);
        $pdf->SetTextColor(0, 60, 120);
        $pdf->SetXY($text_x, $logo_y + 0.5);
        $pdf->Cell($text_maxw, 4, 'MENTIONS OBLIGATOIRES — SERVICES À LA PERSONNE', 0, 1, 'L');

        // Paramètres SAP
        $numAgrement      = getDolGlobalString('ATTESTATIONSAP_NUMERO_AGREMENT', '');
        if (empty($numAgrement)) $numAgrement = getDolGlobalString('ATTESTATIONSAP_DECL_NUM', '');
        $natureService    = getDolGlobalString('ATTESTATIONSAP_NATURE_SERVICE', 'Assistance informatique à domicile');
        $modeIntervention = getDolGlobalString('ATTESTATIONSAP_MODE', 'prestataire');
        $menTva           = getDolGlobalInt('ATTESTATIONSAP_MENTION_TVA_EXONEREE', 1);

        // Lignes du cadre
        $pdf->SetFont('', '', 6.5);
        $pdf->SetTextColor(0, 0, 0);
        $lineY = $logo_y + 5.5;

        $lines = array(
            'N° déclaration SAP : ' . ($numAgrement ?: '⚠ Non renseigné') . ' — ' . $natureService . ' — Mode ' . ucfirst($modeIntervention),
            '50 % des sommes versées ouvrent droit à crédit d\'impôt (art. 199 sexdecies du CGI)',
        );

        // Intervenant sur une ligne dédiée
        if (!empty($interStr)) {
            $lines[] = 'Intervenant(s) : ' . $interStr;
        }

        $lines[] = 'Les interventions ont lieu au domicile du client — Conservez ce document pour votre déclaration de revenus';

        if ($menTva) {
            $lines[] = 'TVA non applicable — Article 293 B du CGI';
        }

        foreach ($lines as $line) {
            $pdf->SetXY($text_x, $lineY);
            $pdf->MultiCell($text_maxw, 3.6, $line, 0, 'L', false);
            $lineY += 3.8;
        }

        $pdf->SetTextColor(0, 0, 0);
        return $sap_y + $hauteur + 3;
    }

    /**
     * Pied de page
     */
    protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0, $heightforqrinvoice = 0)
    {
        global $conf;

        $showdetails = getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS', 0);
        $marge_basse = $this->marge_basse + 8;

        if ($showdetails) {
            $pdf->SetFont('', '', 6.5);
            $pdf->SetY($this->page_hauteur - $marge_basse);
            $infos = array();
            if (!empty($this->emetteur->capital))   $infos[] = $outputlangs->transnoentities('CapitalOf', price($this->emetteur->capital, 0, $outputlangs, 0, 0, -1, $conf->currency));
            if (!empty($this->emetteur->tva_intra)) $infos[] = $outputlangs->transnoentities('VATIntraShort') . ': ' . $this->emetteur->tva_intra;
            if (!empty($this->emetteur->idprof1) && $this->emetteur->country_code == 'FR') $infos[] = 'SIRET: ' . $this->emetteur->idprof1;
            if (!empty($this->emetteur->idprof2))   $infos[] = ($this->emetteur->country_code == 'FR' ? 'APE: ' : '') . $this->emetteur->idprof2;
            if ($infos) {
                $pdf->SetX($this->marge_gauche);
                $pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 2, implode(' • ', $infos), 0, 'C');
            }
        }

        if (!$hidefreetext) {
            $pdf->SetY(-8);
            $pdf->SetFont('', 'I', 8);
            $pdf->SetX($this->marge_gauche);
            $pdf->SetTextColor(120, 120, 120);
            $pdf->MultiCell(
                $this->page_largeur - $this->marge_gauche - $this->marge_droite,
                2, $outputlangs->transnoentities('Page') . ' ' . $pdf->PageNo() . '/\{nb\}',
                0, 'C'
            );
        }

        if (!empty($this->watermark)) {
            pdf_watermark($pdf, $outputlangs, $this->page_hauteur, $this->page_largeur, 'mm', $this->watermark);
        }

        return $marge_basse;
    }

    /**
     * Cherche le logo SAP dans les emplacements connus
     */
    protected function _findLogoSap($object)
    {
        global $conf;

        $rel = getDolGlobalString('ATTESTATIONSAP_LOGO', '');
        if (!empty($rel)) {
            $path = DOL_DATA_ROOT . '/' . ltrim($rel, '/');
            if (is_readable($path)) return $path;
        }

        $logodir = !empty($conf->mycompany->multidir_output[$object->entity])
            ? $conf->mycompany->multidir_output[$object->entity]
            : (isset($conf->mycompany->dir_output) ? $conf->mycompany->dir_output : '');

        foreach (array('/logos/logo-sap.jpg', '/logos/logo-sap.png') as $lp) {
            if (!empty($logodir) && is_readable($logodir . $lp)) return $logodir . $lp;
        }

        $moduleDir = DOL_DOCUMENT_ROOT . '/custom/attestationsap/img/';
        foreach (array('logo-sap.jpg', 'logo-sap.png', 'logo_sap.jpg', 'logo_sap.png') as $fn) {
            if (is_readable($moduleDir . $fn)) return $moduleDir . $fn;
        }

        return '';
    }
}
