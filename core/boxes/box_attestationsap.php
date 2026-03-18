<?php
/**
 * \file    htdocs/custom/attestationsap/core/boxes/box_attestationsap.php
 * \ingroup attestationsap
 * \brief   Widget SAP pour le tableau de bord Dolibarr
 */

include_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';

class box_attestationsap extends ModeleBoxes
{
	public $boxcode  = "box_attestationsap";
	public $boximg   = "generic";
	public $boxlabel = "BoxAttestationSAP";
	public $depends  = array("attestationsap");

	public function __construct($db, $param = '')
	{
		$this->db = $db;
	}

	public function loadBox($max = 5)
	{
		global $conf, $langs, $user;

		$langs->load('attestationsap@attestationsap');

		// Compter attestations non envoyées de l'année fiscale (N-1)
		$attDir = getDolGlobalString('ATTESTATIONSAP_OUTPUTDIR', '');
		if (empty($attDir) || strpos($attDir, DOL_DATA_ROOT) !== 0) $attDir = DOL_DATA_ROOT.'/attestationsap';
		$yearFisc = (int)date('Y') - 1;
		$allPdfs = is_dir($attDir) ? (glob($attDir.'/attestation_sap_'.$yearFisc.'-*.pdf') ?: array()) : array();
		$nbNonEnvoyees = 0;
		foreach ($allPdfs as $pdf) {
			if (!file_exists($pdf.'.sent.json')) $nbNonEnvoyees++;
		}
		$badge = $nbNonEnvoyees > 0
			? ' <span class="badge marginleftonlyshort" style="background:#e74c3c;color:#fff" title="'.$nbNonEnvoyees.' attestation(s) non envoyée(s) pour '.$yearFisc.'">'.$nbNonEnvoyees.'</span>'
			: '';

		$this->info_box_head = array(
			'text'    => $langs->trans("BoxAttestationSAP").$badge,
			'sublink' => dol_buildpath('/custom/attestationsap/index.php', 1),
		);

		$entity = (int)$conf->entity;
		$line   = 0;

		// ---- RAPPEL JANVIER ----
		if ((int)date('n') === 1) {
			$this->info_box_contents[$line][] = array(
				'td'   => 'colspan="3" class="center"',
				'text' => '<div style="background:#fdecea;border:1px solid #e74c3c;border-radius:4px;padding:6px 10px;color:#c0392b;font-weight:bold">'
				         .'⚠ Janvier : pensez à générer les attestations fiscales SAP !&nbsp;'
				         .'<a href="'.dol_buildpath('/custom/attestationsap/index.php?tab=generate', 1).'">Générer →</a>'
				         .'</div>',
				'asis' => 1,
			);
			$line++;
		}

		// ---- EN-TÊTE DEVIS ----
		$this->info_box_contents[$line][] = array(
			'td'   => 'colspan="3" class="liste_titre"',
			'text' => '<a href="'.DOL_URL_ROOT.'/comm/propal/list.php"><strong style="text-transform:uppercase">Derniers devis SAP</strong></a>',
			'asis' => 1,
		);
		$line++;

		// ---- DEVIS SAP ----
		$sql = "SELECT p.rowid, p.ref, p.total_ttc, p.fk_statut, s.nom as client, p.datep"
		      ." FROM ".MAIN_DB_PREFIX."propal p"
		      ." LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = p.fk_soc"
		      ." WHERE p.entity = ".$entity
		      ." AND p.model_pdf IN ('devis_sap','devis_sap_v2')"
		      ." ORDER BY p.rowid DESC LIMIT ".((int)$max);

		$res = $this->db->query($sql);
		$nb  = 0;
		if ($res) {
			while ($obj = $this->db->fetch_object($res)) {
				$nb++;
				$statuts = array(0=>'Brouillon', 1=>'Validé', 2=>'Signé', 3=>'Refusé', 4=>'Expiré');
				$statut  = isset($statuts[$obj->fk_statut]) ? $statuts[$obj->fk_statut] : '';
				$date    = $obj->datep ? dol_print_date($this->db->jdate($obj->datep), 'day') : '';

				$this->info_box_contents[$line][] = array(
					'td'   => '',
					'text' => '<a href="'.DOL_URL_ROOT.'/comm/propal/card.php?id='.$obj->rowid.'">'.dol_escape_htmltag($obj->ref).'</a>',
					'asis' => 1,
				);
				$this->info_box_contents[$line][] = array(
					'td'   => 'class="tdoverflowmax150"',
					'text' => dol_escape_htmltag($obj->client),
				);
				$this->info_box_contents[$line][] = array(
					'td'   => 'class="nowraponall right amount"',
					'text' => price($obj->total_ttc, 0, $langs, 0, -1, -1, $conf->currency)
					         .($date ? ' <span class="opacitymedium">&nbsp;'.$date.'</span>' : '')
					         .' &nbsp;<span class="opacitymedium">'.$statut.'</span>',
					'asis' => 1,
				);
				$line++;
			}
			$this->db->free($res);
		}
		if ($nb === 0) {
			$this->info_box_contents[$line][] = array(
				'td'   => 'colspan="3" class="center opacitymedium"',
				'text' => 'Aucun devis SAP',
			);
			$line++;
		}

		// ---- EN-TÊTE FACTURES ----
		$this->info_box_contents[$line][] = array(
			'td'   => 'colspan="3" class="liste_titre"',
			'text' => '<a href="'.DOL_URL_ROOT.'/compta/facture/list.php"><strong style="text-transform:uppercase">Dernières factures SAP</strong></a>',
			'asis' => 1,
		);
		$line++;

		// ---- FACTURES SAP ----
		$models_raw = getDolGlobalString('ATTESTATIONSAP_FACTURE_MODEL_LIST', 'facture_sap_v3');
		$models     = array_values(array_filter(array_map('trim', explode(',', $models_raw))));
		if (empty($models)) $models = array('facture_sap_v3');
		$model_in   = "'".implode("','", array_map(array($this->db, 'escape'), $models))."'";

		$sql = "SELECT f.rowid, f.ref, f.total_ttc, f.fk_statut, s.nom as client, f.datef"
		      ." FROM ".MAIN_DB_PREFIX."facture f"
		      ." LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = f.fk_soc"
		      ." WHERE f.entity = ".$entity
		      ." AND f.model_pdf IN (".$model_in.")"
		      ." ORDER BY f.rowid DESC LIMIT ".((int)$max);

		$res = $this->db->query($sql);
		$nb  = 0;
		if ($res) {
			while ($obj = $this->db->fetch_object($res)) {
				$nb++;
				$statuts = array(0=>'Brouillon', 1=>'Validée', 2=>'Payée', 3=>'Abandonnée');
				$statut  = isset($statuts[$obj->fk_statut]) ? $statuts[$obj->fk_statut] : '';
				$date    = $obj->datef ? dol_print_date($this->db->jdate($obj->datef), 'day') : '';

				$this->info_box_contents[$line][] = array(
					'td'   => '',
					'text' => '<a href="'.DOL_URL_ROOT.'/compta/facture/card.php?id='.$obj->rowid.'">'.dol_escape_htmltag($obj->ref).'</a>',
					'asis' => 1,
				);
				$this->info_box_contents[$line][] = array(
					'td'   => 'class="tdoverflowmax150"',
					'text' => dol_escape_htmltag($obj->client),
				);
				$this->info_box_contents[$line][] = array(
					'td'   => 'class="nowraponall right amount"',
					'text' => price($obj->total_ttc, 0, $langs, 0, -1, -1, $conf->currency)
					         .($date ? ' <span class="opacitymedium">&nbsp;'.$date.'</span>' : '')
					         .' &nbsp;<span class="opacitymedium">'.$statut.'</span>',
					'asis' => 1,
				);
				$line++;
			}
			$this->db->free($res);
		}
		if ($nb === 0) {
			$this->info_box_contents[$line][] = array(
				'td'   => 'colspan="3" class="center opacitymedium"',
				'text' => 'Aucune facture SAP',
			);
			$line++;
		}

		// ---- LIEN ATTESTATIONS ----
		$this->info_box_contents[$line][] = array(
			'td'   => 'colspan="3" class="center"',
			'text' => '<a href="'.dol_buildpath('/custom/attestationsap/index.php?tab=generate', 1).'">→ Générer les attestations fiscales</a>',
			'asis' => 1,
		);
	}

	public function showBox($head = null, $contents = null, $nooutput = 0)
	{
		return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
	}
}
