<?php
/**
 * \file        htdocs/custom/attestationsap/core/modules/pdf/pdf_attestation_sap.modules.php
 * \ingroup     attestationsap
 * \brief       Génération PDF attestation fiscale SAP annuelle
 * \version     2.1.0
 *
 * Conformité légale :
 *  - Article 199 sexdecies du CGI (crédit d'impôt 50%)
 *  - Article D.7233-1 du Code du travail :
 *      "L'attestation mentionne l'identité du ou des salariés/intervenants
 *       ayant effectué les interventions et la nature des services rendus."
 *  - Agrément / déclaration préalable NOVA
 *  - Mode prestataire ou mandataire
 *
 * Gestion des intervenants (via SapIntervenants.class.php) :
 *  - Auto-entrepreneur : affiche le dirigeant (user Dolibarr)
 *  - Société avec salariés : affiche tous les intervenants de l'année
 *  - Texte libre en fallback si aucun user configuré
 *
 * PHP >= 7.4
 */

require_once DOL_DOCUMENT_ROOT . '/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
$_sapInterFile = DOL_DOCUMENT_ROOT . '/custom/attestationsap/class/SapIntervenants.class.php';
if (file_exists($_sapInterFile)) require_once $_sapInterFile;

class pdf_attestation_sap
{
    /** @var DoliDB */
    public $db;
    /** @var string */
    public $error = '';

    public function __construct($db)
    {
        $this->db = $db;
    }

    // =========================================================================
    // UTILITAIRES
    // =========================================================================

    public static function resolveFiscalYear($override = null)
    {
        if (!empty($override)) return (int)$override;
        $a = dol_getdate(dol_now());
        return ((int)$a['mon'] <= 1) ? ((int)$a['year'] - 1) : (int)$a['year'];
    }

    protected static function parseModelsList()
    {
        $raw = getDolGlobalString('ATTESTATIONSAP_FACTURE_MODEL_LIST', '');
        if ($raw === '') $raw = getDolGlobalString('ATTESTATIONSAP_FACTURE_MODEL_NAME', 'facture_sap_v3');
        $out = array();
        foreach (preg_split('/[,;]+/', (string)$raw) as $m) {
            $m = trim($m);
            if ($m !== '') $out[] = $m;
        }
        if (empty($out)) $out = array('facture_sap_v3');
        return array_values(array_unique($out));
    }

    protected static function autoLoadFacturesForSociete($socid, $year)
    {
        global $db, $conf;
        $out = array();
        if (empty($socid) || $year < 1900) return $out;

        $tsStart = dol_get_first_day($year, 1, true);
        $tsEnd   = dol_get_last_day($year, 12, true);
        $ds      = "'" . $db->idate($tsStart) . "'";
        $de      = "'" . $db->idate($tsEnd) . "'";

        $models = self::parseModelsList();
        $parts  = array();
        foreach ($models as $mdl) {
            $m       = $db->escape($mdl);
            $parts[] = "(f.model_pdf = '" . $m . "' OR f.model_pdf LIKE '" . $m . "%')";
        }
        if (empty($parts)) return $out;

        $sql = "SELECT f.rowid, f.ref, f.datef, f.date_valid, f.total_ttc, f.model_pdf
                FROM " . MAIN_DB_PREFIX . "facture f
                WHERE f.entity = " . (int)$conf->entity . "
                  AND f.fk_soc = " . (int)$socid . "
                  AND f.type = 0
                  AND f.fk_statut IN (1,2)
                  AND COALESCE(f.datef, f.date_valid) BETWEEN $ds AND $de
                  AND (" . implode(' OR ', $parts) . ")
                ORDER BY COALESCE(f.datef, f.date_valid) ASC";

        $res = $db->query($sql);
        if ($res) {
            while ($o = $db->fetch_object($res)) {
                $obj            = new stdClass();
                $obj->rowid     = (int)$o->rowid;
                $obj->ref       = $o->ref;
                $obj->datef     = $o->datef ? $db->jdate($o->datef) : $db->jdate($o->date_valid);
                $obj->total_ttc = (float)$o->total_ttc;
                $obj->model_pdf = $o->model_pdf;
                $out[]          = $obj;
            }
            $db->free($res);
        }
        return $out;
    }

    protected static function computeTotalsFromFactures($factures, $sap_cat_id, $services_fallback)
    {
        global $db;
        $total_ttc = 0.0;
        $hours     = 0.0;
        $detail    = array();

        $needles = array();
        foreach (preg_split('/\r?\n/', (string)$services_fallback) as $l) {
            $s = trim(dol_string_unaccent($l));
            if ($s !== '') $needles[] = dol_strtolower($s);
        }

        foreach ($factures as $f) {
            $detail[$f->rowid] = array('qty' => 0.0, 'total_ttc' => 0.0);
            $res = $db->query(
                "SELECT fd.fk_product, fd.qty, fd.total_ttc, fd.label, fd.description AS descs
                 FROM " . MAIN_DB_PREFIX . "facturedet fd
                 WHERE fd.fk_facture = " . (int)$f->rowid
            );
            if (!$res) continue;
            while ($ln = $db->fetch_object($res)) {
                if (!self::isLineSap($db, $ln, $sap_cat_id, $needles)) continue;
                $total_ttc += (float)$ln->total_ttc;
                $hours     += (float)$ln->qty;
                $detail[$f->rowid]['qty']       += (float)$ln->qty;
                $detail[$f->rowid]['total_ttc'] += (float)$ln->total_ttc;
            }
            $db->free($res);
        }
        return array(round($total_ttc, 2), round($hours, 2), $detail);
    }

    protected static function isLineSap($db, $ln, $sap_cat_id, $needles)
    {
        if ($sap_cat_id > 0 && (int)$ln->fk_product > 0) {
            $rc = $db->query(
                "SELECT 1 FROM " . MAIN_DB_PREFIX . "categorie_product
                 WHERE fk_product = " . (int)$ln->fk_product . "
                   AND fk_categorie = " . (int)$sap_cat_id . " LIMIT 1"
            );
            if ($rc && $db->fetch_object($rc)) return true;
        }
        if (!empty($needles)) {
            $txt = dol_strtolower(dol_string_unaccent(($ln->label ?: '') . ' ' . ($ln->descs ?: '')));
            foreach ($needles as $n) {
                if ($n !== '' && strpos($txt, $n) !== false) return true;
            }
        }
        $txt2 = dol_strtolower(dol_string_unaccent(($ln->label ?: '') . ' ' . ($ln->descs ?: '')));
        if (strpos($txt2, 'service a la personne') !== false || strpos($txt2, ' sap ') !== false) return true;
        return false;
    }

    // =========================================================================
    // GÉNÉRATION PDF
    // =========================================================================

    /**
     * @param  Societe   $societe
     * @param  float     $total_ttc   0 = calcul auto
     * @param  float     $hours       0 = calcul auto
     * @param  int       $year
     * @param  array     $factures    vide = auto-chargé
     * @return string|false           Chemin du fichier ou false
     */
    public static function write_file($societe, $total_ttc, $hours, $year, $factures = array())
    {
        global $conf, $langs, $db, $mysoc;

        if (!is_object($langs)) $langs = new Translate('', $conf);
        $year = self::resolveFiscalYear($year);
        if (empty($societe) || empty($societe->id)) return false;

        // Société émettrice
        if (empty($mysoc) || empty($mysoc->id)) {
            $mysoc = new Societe($db);
            $mysoc->setMysoc($conf);
        }

        // Répertoire de sortie
        $outputdir = getDolGlobalString('ATTESTATIONSAP_OUTPUTDIR', '');
        if (empty($outputdir)) {
            $outputdir = !empty($conf->attestationsap->dir_output)
                ? $conf->attestationsap->dir_output
                : DOL_DATA_ROOT . '/attestationsap';
        }
        if (!dol_is_dir($outputdir) && dol_mkdir($outputdir) < 0) return false;
        if (!is_writable($outputdir)) return false;

        // Chargement factures
        if (empty($factures) || !is_array($factures)) {
            $factures = self::autoLoadFacturesForSociete($societe->id, $year);
        }

        // Calcul totaux
        if ((float)$total_ttc <= 0.0 || (float)$hours <= 0.0) {
            $sap_cat_id        = (int)getDolGlobalString('ATTESTATIONSAP_CATEGORY_ID', 0);
            $services_fallback = getDolGlobalString('ATTESTATIONSAP_SERVICES', '');
            list($total_ttc, $hours, $detail) = self::computeTotalsFromFactures($factures, $sap_cat_id, $services_fallback);
        } else {
            $detail = array();
        }
        if ((float)$total_ttc <= 0.0) return false;

        // ---- Récupération des intervenants ----
        $sapInter     = new SapIntervenants($db);
        $intervenants = $sapInter->getIntervenantsForAnnee($factures, $conf->entity);
        // Si aucun trouvé → libellé par défaut depuis config
        if (empty($intervenants)) {
            $libre = getDolGlobalString('ATTESTATIONSAP_INTERVENANT_LIBRE', '');
            if (!empty($libre)) $intervenants = array($libre);
        }
        $intervenantsStr = implode(', ', $intervenants);

        // Nom du fichier
        $clean   = strtoupper(trim(preg_replace('/[^A-Z0-9\-]+/', '-', dol_sanitizeFileName(dol_string_unaccent($societe->name))), '-'));
        $pattern = $outputdir . '/attestation_sap_' . $year . '-' . $clean . '-ATT*.pdf';
        $next    = 1;
        foreach ((glob($pattern) ?: array()) as $existing) {
            if (preg_match('/ATT(\d+)\.pdf$/', $existing, $mx)) {
                $n = (int)$mx[1];
                if ($n >= $next) $next = $n + 1;
            }
        }
        $outfile = $outputdir . '/attestation_sap_' . $year . '-' . $clean . '-ATT' . str_pad($next, 4, '0', STR_PAD_LEFT) . '.pdf';

        // Config SAP
        $numAgrement      = getDolGlobalString('ATTESTATIONSAP_NUMERO_AGREMENT', '');
        if (empty($numAgrement)) $numAgrement = getDolGlobalString('ATTESTATIONSAP_DECL_NUM', '');
        $natureService    = getDolGlobalString('ATTESTATIONSAP_NATURE_SERVICE', 'Assistance informatique à domicile');
        $modeIntervention = getDolGlobalString('ATTESTATIONSAP_MODE', 'prestataire');
        $showCreditImpot  = getDolGlobalInt('ATTESTATIONSAP_SHOW_CREDIT_IMPOT', 1);
        $creditImpot      = round((float)$total_ttc * 0.5, 2);
        $signName         = getDolGlobalString('ATTESTATIONSAP_SIGN_NAME', $mysoc->name);
        $signText         = getDolGlobalString('ATTESTATIONSAP_SIGN_TEXT', '');

        // ---- Génération TCPDF ----
        require_once DOL_DOCUMENT_ROOT . '/includes/tecnickcom/tcpdf/tcpdf.php';
        try {
            $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(15, 15, 15);
            $pdf->SetAutoPageBreak(true, 22);
            $pdf->SetCreator('Dolibarr — AttestationSAP v2.1');
            $pdf->SetAuthor(dol_string_nospecial($mysoc->name));
            $pdf->SetTitle('Attestation fiscale SAP ' . $year . ' — ' . $societe->name);
            $pdf->SetSubject('Art. 199 sexdecies CGI — Art. D.7233-1 Code du travail');
            $pdf->AddPage();

            $colL  = 15;
            $pageW = 210 - 30;

            // ---- LOGO ÉMETTEUR ----
            $logoDir    = !empty($conf->mycompany->multidir_output[$mysoc->entity])
                ? $conf->mycompany->multidir_output[$mysoc->entity]
                : (isset($conf->mycompany->dir_output) ? $conf->mycompany->dir_output : '');
            $logoFound  = false;
            foreach (array('/logos/thumbs/mycompany_small.jpg', '/logos/thumbs/mycompany_small.png', '/logos/mycompany.jpg', '/logos/mycompany.png') as $lp) {
                if (!empty($logoDir) && is_readable($logoDir . $lp)) {
                    $pdf->Image($logoDir . $lp, $colL, 10, 45, 0, '', '', 'T', false, 300);
                    $logoFound = true;
                    break;
                }
            }
            $textOffX = $logoFound ? $colL + 50 : $colL;
            $textW    = $pageW - ($logoFound ? 50 : 0);

            // ---- LOGO SAP OFFICIEL (coin supérieur droit) ----
            $logoSapPath = '';
            $moduleImgDir = DOL_DOCUMENT_ROOT . '/custom/attestationsap/img/';
            foreach (array('logo-sap.jpg', 'logo-sap.png') as $_fn) {
                if (is_readable($moduleImgDir . $_fn)) { $logoSapPath = $moduleImgDir . $_fn; break; }
            }
            if (empty($logoSapPath)) {
                $relSap = getDolGlobalString('ATTESTATIONSAP_LOGO', '');
                if (!empty($relSap) && is_readable(DOL_DATA_ROOT . '/' . $relSap)) {
                    $logoSapPath = DOL_DATA_ROOT . '/' . $relSap;
                }
            }
            if (!empty($logoSapPath)) {
                // Positionner le logo SAP en haut à droite (40mm de large)
                $pdf->Image($logoSapPath, $colL + $pageW - 40, 8, 40, 0, '', '', 'T', false, 150);
                $textW = $textW - 42; // réduire la zone texte pour ne pas chevaucher
            }

            // ---- TITRE ----
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->SetXY($textOffX, 12);
            $pdf->SetTextColor(0, 60, 120);
            $pdf->Cell($textW, 7, 'ATTESTATION FISCALE ' . $year, 0, 1, $logoFound ? 'L' : 'C');
            $pdf->SetFont('helvetica', '', 8.5);
            $pdf->SetXY($textOffX, 21);
            $pdf->SetTextColor(80, 80, 80);
            $pdf->MultiCell($textW, 4, 'Conformément à l\'art. 199 sexdecies du CGI et à l\'art. D.7233-1 du Code du travail', 0, $logoFound ? 'L' : 'C');

            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetY(38);
            $pdf->SetDrawColor(0, 60, 120);
            $pdf->SetLineWidth(0.6);
            $pdf->Line($colL, $pdf->GetY(), $colL + $pageW, $pdf->GetY());
            $pdf->Ln(5);

            // ================================================================
            // BLOC PRESTATAIRE + BÉNÉFICIAIRE
            // ================================================================
            $yBloc = $pdf->GetY();
            $halfW = ($pageW - 4) / 2;

            // -- Cadre gauche : Prestataire --
            $pdf->SetFillColor(240, 245, 255);
            $pdf->SetDrawColor(180, 190, 220);
            $pdf->SetLineWidth(0.3);
            $pdf->RoundedRect($colL, $yBloc, $halfW, 46, 2, '1234', 'DF');

            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetXY($colL + 2, $yBloc + 2);
            $pdf->SetTextColor(0, 60, 120);
            $pdf->Cell($halfW - 4, 4.5, 'PRESTATAIRE', 0, 1, 'L');

            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY($colL + 2, $yBloc + 7);
            $pdf->MultiCell($halfW - 4, 5, dol_string_nospecial($mysoc->name), 0, 'L');

            $pdf->SetFont('helvetica', '', 8.5);
            $yTmp = $pdf->GetY();
            if (!empty($mysoc->address)) {
                $pdf->SetX($colL + 2);
                $pdf->MultiCell($halfW - 4, 4, $mysoc->address, 0, 'L');
            }
            $pdf->SetX($colL + 2);
            $pdf->MultiCell($halfW - 4, 4, trim($mysoc->zip . ' ' . $mysoc->town), 0, 'L');

            // SIRET
            if (!empty($mysoc->idprof2)) {
                $pdf->SetX($colL + 2);
                $pdf->Cell($halfW - 4, 4, 'SIRET : ' . $mysoc->idprof2, 0, 1, 'L');
            }

            // N° déclaration SAP — en vert, bien visible
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetTextColor(0, 110, 50);
            $pdf->SetX($colL + 2);
            $pdf->Cell($halfW - 4, 4.5, 'N° déclaration SAP : ' . ($numAgrement ?: '⚠ Non renseigné'), 0, 1, 'L');
            $pdf->SetTextColor(0, 0, 0);

            // -- Cadre droit : Bénéficiaire --
            $xR = $colL + $halfW + 4;
            $pdf->SetFillColor(255, 252, 240);
            $pdf->SetDrawColor(210, 190, 150);
            $pdf->RoundedRect($xR, $yBloc, $halfW, 46, 2, '1234', 'DF');

            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetXY($xR + 2, $yBloc + 2);
            $pdf->SetTextColor(130, 70, 0);
            $pdf->Cell($halfW - 4, 4.5, 'BÉNÉFICIAIRE', 0, 1, 'L');

            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY($xR + 2, $yBloc + 7);
            $pdf->MultiCell($halfW - 4, 5, dol_string_nospecial($societe->name), 0, 'L');

            $pdf->SetFont('helvetica', '', 8.5);
            if (!empty($societe->address)) {
                $pdf->SetX($xR + 2);
                $pdf->MultiCell($halfW - 4, 4, $societe->address, 0, 'L');
            }
            $pdf->SetX($xR + 2);
            $pdf->MultiCell($halfW - 4, 4, trim($societe->zip . ' ' . $societe->town), 0, 'L');
            if (!empty($societe->email)) {
                $pdf->SetX($xR + 2);
                $pdf->Cell($halfW - 4, 4, $societe->email, 0, 1, 'L');
            }

            $pdf->SetY($yBloc + 50);

            // ================================================================
            // BLOC INTERVENANTS — Conformité D.7233-1
            // ================================================================
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetTextColor(0, 60, 120);
            $pdf->Cell($pageW, 5, 'INTERVENANT(S)', 0, 1, 'L');
            $pdf->SetTextColor(0, 0, 0);

            $pdf->SetFillColor(235, 250, 235);
            $pdf->SetDrawColor(160, 210, 160);
            $pdf->SetLineWidth(0.3);

            if (!empty($intervenantsStr)) {
                // Affichage du/des intervenant(s)
                $pdf->SetFont('helvetica', 'B', 9.5);
                $pdf->SetXY($colL, $pdf->GetY());
                $rectH = 10;
                $pdf->RoundedRect($colL, $pdf->GetY(), $pageW, $rectH, 2, '1234', 'DF');
                $pdf->SetXY($colL + 3, $pdf->GetY() + 1.5);
                $pdf->Cell($pageW - 6, 4.5, $intervenantsStr, 0, 1, 'L');
                $pdf->SetFont('helvetica', 'I', 7.5);
                $pdf->SetTextColor(80, 80, 80);
                $pdf->SetX($colL + 3);

                // Précision selon source
                $intervenantCount = count($intervenants);
                $mention = ($intervenantCount === 1)
                    ? 'Intervenant ayant réalisé les prestations au domicile du bénéficiaire'
                    : $intervenantCount . ' intervenants ayant réalisé les prestations au domicile du bénéficiaire';
                $pdf->Cell($pageW - 6, 3.5, $mention, 0, 1, 'L');
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetY($pdf->GetY() + 2);
            } else {
                // Avertissement si intervenant non renseigné
                $pdf->SetFillColor(255, 240, 220);
                $pdf->SetDrawColor(220, 160, 100);
                $pdf->RoundedRect($colL, $pdf->GetY(), $pageW, 9, 2, '1234', 'DF');
                $pdf->SetFont('helvetica', 'I', 8);
                $pdf->SetTextColor(180, 80, 0);
                $pdf->SetXY($colL + 3, $pdf->GetY() + 2);
                $pdf->Cell($pageW - 6, 4, '⚠ Intervenant non renseigné — Configurez-le dans Paramètres SAP', 0, 1, 'L');
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetY($pdf->GetY() + 2);
            }

            // ================================================================
            // NATURE DES SERVICES
            // ================================================================
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetTextColor(0, 60, 120);
            $pdf->Cell($pageW, 5, 'NATURE DES SERVICES', 0, 1, 'L');
            $pdf->SetTextColor(0, 0, 0);

            $pdf->SetFillColor(240, 248, 255);
            $pdf->SetDrawColor(190, 210, 240);
            $pdf->RoundedRect($colL, $pdf->GetY(), $pageW, 11, 2, '1234', 'DF');
            $pdf->SetFont('helvetica', '', 8.5);
            $pdf->SetXY($colL + 3, $pdf->GetY() + 1.5);
            $pdf->MultiCell($pageW - 6, 4,
                $natureService . ' — Mode ' . ucfirst($modeIntervention) . "\n" .
                'Prestations réalisées au domicile du bénéficiaire.',
                0, 'L');
            $pdf->SetY($pdf->GetY() + 4);

            // ================================================================
            // TABLEAU RÉCAPITULATIF DES FACTURES
            // ================================================================
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetTextColor(0, 60, 120);
            $pdf->Cell($pageW, 5, 'RÉCAPITULATIF DES FACTURES — ANNÉE ' . $year, 0, 1, 'L');
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Ln(1);

            // Colonnes : N° Facture | Date | Description | Heures | Montant TTC
            // Largeurs totales = pageW (180mm)
            $colW = array(32, 24, 68, 22, 34); // total = 180
            $rowH = 6.5;

            // En-tête
            $pdf->SetFillColor(0, 60, 120);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('helvetica', 'B', 7.5);
            $pdf->SetX($colL);
            $pdf->Cell($colW[0], $rowH, 'N° Facture',   1, 0, 'C', true);
            $pdf->Cell($colW[1], $rowH, 'Date',          1, 0, 'C', true);
            $pdf->Cell($colW[2], $rowH, 'Description',   1, 0, 'C', true);
            $pdf->Cell($colW[3], $rowH, 'Heures',        1, 0, 'C', true);
            $pdf->Cell($colW[4], $rowH, 'Montant TTC',   1, 1, 'C', true);

            // Lignes factures
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('helvetica', '', 7.5);
            $fill = false;

            foreach ($factures as $fac) {
                $facH   = isset($detail[$fac->rowid]) ? $detail[$fac->rowid]['qty'] : 0.0;
                $facTTC = isset($detail[$fac->rowid]) ? $detail[$fac->rowid]['total_ttc'] : $fac->total_ttc;

                // Description = nature du service (art. D.7233-1)
                $descr = !empty($natureService) ? dol_string_nospecial($natureService) : 'Prestations SAP';

                $pdf->SetFillColor($fill ? 245 : 255, $fill ? 248 : 255, 255);

                // Calculer la hauteur nécessaire pour la description
                $pdf->SetFont('helvetica', '', 7.5);
                $nbLinesDescr = max(1, ceil($pdf->GetStringWidth($descr) / ($colW[2] - 2)));
                $lineH        = max($rowH, $nbLinesDescr * 4.2 + 1);

                $yRow = $pdf->GetY();
                $pdf->SetX($colL);

                // Cellules fixes (hauteur uniforme)
                $pdf->Cell($colW[0], $lineH, dol_string_nospecial($fac->ref),           1, 0, 'L', $fill);
                $pdf->Cell($colW[1], $lineH, dol_print_date($fac->datef, 'day'),         1, 0, 'C', $fill);

                // Description avec MultiCell — on sauvegarde X/Y et on réaligne
                $xDescr = $pdf->GetX();
                $pdf->MultiCell($colW[2], 4.2, $descr, 0, 'L', $fill);
                // Bordure manuelle autour de la zone description
                $pdf->Rect($xDescr, $yRow, $colW[2], $lineH);

                // Repositionner pour les colonnes suivantes
                $pdf->SetXY($xDescr + $colW[2], $yRow);
                $pdf->Cell($colW[3], $lineH, number_format($facH, 2, ',', ' ') . ' h', 1, 0, 'R', $fill);
                $pdf->Cell($colW[4], $lineH, number_format($facTTC, 2, ',', ' ') . ' €', 1, 1, 'R', $fill);

                // S'assurer qu'on est bien à la bonne ligne suivante
                $pdf->SetY($yRow + $lineH);
                $fill = !$fill;

                // Saut de page si besoin
                if ($pdf->GetY() > 235) {
                    $pdf->AddPage();
                    $pdf->SetFillColor(0, 60, 120);
                    $pdf->SetTextColor(255, 255, 255);
                    $pdf->SetFont('helvetica', 'B', 7.5);
                    $pdf->SetX($colL);
                    $pdf->Cell($colW[0], $rowH, 'N° Facture',  1, 0, 'C', true);
                    $pdf->Cell($colW[1], $rowH, 'Date',         1, 0, 'C', true);
                    $pdf->Cell($colW[2], $rowH, 'Description',  1, 0, 'C', true);
                    $pdf->Cell($colW[3], $rowH, 'Heures',       1, 0, 'C', true);
                    $pdf->Cell($colW[4], $rowH, 'Montant TTC',  1, 1, 'C', true);
                    $pdf->SetTextColor(0, 0, 0);
                    $pdf->SetFont('helvetica', '', 7.5);
                    $fill = false;
                }
            }

            // Ligne TOTAL
            $pdf->SetFont('helvetica', 'B', 8.5);
            $pdf->SetFillColor(215, 230, 255);
            $pdf->SetX($colL);
            $pdf->Cell($colW[0] + $colW[1] + $colW[2], $rowH, 'TOTAL', 1, 0, 'L', true);
            $pdf->Cell($colW[3], $rowH, number_format((float)$hours, 2, ',', ' ') . ' h', 1, 0, 'R', true);
            $pdf->Cell($colW[4], $rowH, number_format((float)$total_ttc, 2, ',', ' ') . ' €', 1, 1, 'R', true);
            $pdf->Ln(5);

            // ================================================================
            // CRÉDIT D'IMPÔT
            // ================================================================
            if ($showCreditImpot) {
                $pdf->SetFillColor(230, 255, 225);
                $pdf->SetDrawColor(100, 180, 100);
                $pdf->SetLineWidth(0.4);
                $pdf->RoundedRect($colL, $pdf->GetY(), $pageW, 18, 2, '1234', 'DF');
                $pdf->SetXY($colL + 3, $pdf->GetY() + 2);
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetTextColor(0, 110, 40);
                $pdf->Cell($pageW - 6, 6, 'Crédit d\'impôt estimé (50 %) : ' . number_format($creditImpot, 2, ',', ' ') . ' €', 0, 1, 'L');
                $pdf->SetFont('helvetica', '', 7.5);
                $pdf->SetXY($colL + 3, $pdf->GetY());
                $pdf->SetTextColor(0, 80, 30);
                $pdf->MultiCell($pageW - 6, 3.8,
                    'Art. 199 sexdecies du CGI — 50 % des dépenses sont déductibles de l\'impôt sur le revenu.' . "\n" .
                    'Montant à reporter sur votre déclaration de revenus (case 7DB ou équivalent).',
                    0, 'L');
                $pdf->SetTextColor(0, 0, 0);
                $pdf->Ln(6);
            }

            // ================================================================
            // MENTIONS LÉGALES OBLIGATOIRES
            // ================================================================
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetTextColor(0, 60, 120);
            $pdf->Cell($pageW, 5, 'MENTIONS LÉGALES', 0, 1, 'L');
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->SetFillColor(250, 250, 252);
            $pdf->SetDrawColor(200, 200, 210);

            $mentions = array(
                '• Les interventions ont été réalisées au domicile du bénéficiaire (art. D.7233-1 Code du travail).',
                '• Mode d\'intervention : ' . ucfirst($modeIntervention) . '.',
                '• Cette attestation est délivrée en application de l\'art. L.7232-1-1 du Code du travail.',
                '• Numéro de déclaration SAP (NOVA) : ' . ($numAgrement ?: '⚠ Non renseigné') . '.',
                '• Intervenant(s) : ' . ($intervenantsStr ?: '⚠ Non renseigné') . '.',
                '• Conservez ce document pour votre déclaration de revenus (art. 199 sexdecies du CGI).',
                '• En cas de contrôle fiscal, ce document fait foi du montant des dépenses engagées.',
            );
            if (getDolGlobalInt('ATTESTATIONSAP_MENTION_TVA_EXONEREE', 1)) {
                $mentions[] = '• TVA non applicable — Article 293 B du CGI.';
            }

            $pdf->SetX($colL);
            $pdf->MultiCell($pageW, 3.8, implode("\n", $mentions), 1, 'L', true);
            $pdf->Ln(5);

            // ================================================================
            // SIGNATURE
            // ================================================================
            $pdf->SetFont('helvetica', '', 8.5);
            $pdf->SetX($colL);
            $pdf->Cell($pageW / 2, 5, 'Fait à ' . $mysoc->town . ', le ' . dol_print_date(dol_now(), 'day'), 0, 0, 'L');
            $pdf->Cell($pageW / 2, 5, 'Signature et cachet de l\'organisme prestataire :', 0, 1, 'L');

            $pdf->SetFont('helvetica', 'I', 8);
            $pdf->SetTextColor(80, 80, 80);
            $pdf->SetX($colL + $pageW / 2 + 2);
            $ySign = $pdf->GetY();
            $pdf->Cell($pageW / 2 - 2, 4, $signName . ($signText ? ' — ' . $signText : ''), 0, 1, 'L');
            $pdf->SetTextColor(0, 0, 0);

            // Zone signature vierge
            $pdf->SetDrawColor(160, 160, 160);
            $pdf->SetLineWidth(0.3);
            $pdf->Rect($colL + $pageW / 2, $ySign + 4, $pageW / 2, 18, 'D');

            // ---- Pied de page ----
            $pdf->SetY(-14);
            $pdf->SetFont('helvetica', 'I', 6.5);
            $pdf->SetTextColor(140, 140, 140);
            $pdf->SetX($colL);
            $pdf->MultiCell($pageW, 3,
                'AttestationSAP v2.1 — Dolibarr — ' . dol_print_date(dol_now(), 'day') . ' — Page ' . $pdf->PageNo() . '/' . $pdf->getNumPages(),
                0, 'C');

            // Écriture
            $pdf->Output($outfile, 'F');
            dolChmod($outfile);

            return (file_exists($outfile) && filesize($outfile) > 0) ? $outfile : false;

        } catch (Exception $ex) {
            dol_syslog('pdf_attestation_sap::write_file ERROR: ' . $ex->getMessage(), LOG_ERR);
            return false;
        }
    }
}
