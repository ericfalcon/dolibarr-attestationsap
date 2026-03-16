
<?php
// htdocs/custom/attestationsap/help.php — Version PRO

// -----------------------------------------------------------------------------
// Bootstrap Dolibarr
// -----------------------------------------------------------------------------
$res = 0;
if (!$res && file_exists(__DIR__ . '/../../main.inc.php')) $res = @include __DIR__ . '/../../main.inc.php';
if (!$res && file_exists(__DIR__ . '/../../../main.inc.php')) $res = @include __DIR__ . '/../../../main.inc.php';
if (!$res) {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Include of main.inc.php fails';
    exit;
}

// -----------------------------------------------------------------------------
// Sécurité : droits lecture du module ou admin
// -----------------------------------------------------------------------------
if (empty($user->rights->attestationsap->read) && empty($user->admin)) {
    accessforbidden();
}

// -----------------------------------------------------------------------------
// Langue : forcer fr_FR (utile si profil en fr_BE / fr_CA)
// -----------------------------------------------------------------------------
if (method_exists($langs, 'setDefaultLang')) {
    $langs->setDefaultLang('fr_FR');
}

// -----------------------------------------------------------------------------
// Chargement des traductions (robuste)
// Suffixe après @ = nom EXACT du dossier du module (casse incluse)
// -----------------------------------------------------------------------------
$moduleDir = basename(dirname(__FILE__)); // ex: 'attestationsap'
$loaded = $langs->load('attestationsap@' . $moduleDir);
if (!$loaded) {
    $langs->load('attestationsap'); // fallback core si présent
}

// -----------------------------------------------------------------------------
// Helper de traduction avec fallback FR si la clé n'est pas trouvée
// -----------------------------------------------------------------------------
function T($key, $fallback)
{
    global $langs;
    $txt = $langs->trans($key);
    return ($txt === $key) ? $fallback : $txt;
}

// -----------------------------------------------------------------------------
// Fallbacks FR (page lisible même si .lang non chargé)
// -----------------------------------------------------------------------------
$f = array(
    'ModeEmploiSap'           => "Mode d'emploi SAP",
    'AttestationSAPUserGuide' => "Guide d'utilisation des attestations SAP",
    'RappelSAPIntro'          => "Les attestations fiscales SAP doivent être générées et remises aux clients en janvier (année N-1).",

    'Vue_d_ensemble'          => "Vue d'ensemble",
    'Vue_d_ensemble_text'     => "Ce module permet de générer et d'envoyer les attestations fiscales SAP conformément à l'article 199 sexdecies du CGI.",

    'Tableau_de_bord'         => "Tableau de bord",
    'DashboardKPI'            => "Suivez les indicateurs clés : générées, en attente, envoyées.",
    'LiensRapides'            => "Accédez aux actions rapides : Générer, Nouveau devis SAP, Nouvelle facture SAP.",

    'Generer_attestations'    => "Générer les attestations",
    'Choix_annee_fiscale'     => "Choisissez l'année fiscale (N-1 en janvier par défaut).",
    'Filtrer_clients'         => "Filtrez les clients (tags, tiers, statut).",
    'Generation_action'       => "Cliquez sur « Générer » pour produire les PDF.",
    'Envoi_email'             => "Envoyez par e-mail les attestations (PDF en pièce jointe).",

    'Devis_SAP'               => "Devis SAP",
    'Devis_SAP_desc'          => "Créez vos devis avec le modèle devis_sap_v2 (mentions spécifiques SAP si nécessaire).",

    'Facture_SAP'             => "Facture SAP",
    'Facture_SAP_desc'        => "Émettez vos factures avec le modèle facture_sap_v3 (TVA adaptée, mentions légales).",

    'Parametres_SAP'          => "Paramètres SAP",
    'ParamAnnee'              => "Définissez l'année fiscale par défaut (N-1 en janvier).",
    'ParamMentionsLegales'    => "Renseignez les mentions légales pour les attestations.",
    'ParamEmailModele'        => "Personnalisez le modèle d'e-mail d'envoi d'attestation.",

    'Bonnes_pratiques'        => "Bonnes pratiques",
    'CheckTVA'                => "Vérifiez la TVA selon la nature des prestations SAP.",
    'DataQuality'             => "Assurez la qualité des données clients (e-mails, adresses).",
    'Archivage'               => "Conservez une copie PDF des attestations envoyées.",

    'FAQ'                     => "FAQ",
    'Q_annee'                 => "Comment est déterminée l'année des attestations ?",
    'R_annee'                 => "Par défaut N-1 en janvier ; sinon l'année courante. Paramétrable dans Paramètres SAP.",
    'Q_envoi_email'           => "Comment envoyer les attestations par e-mail ?",
    'R_envoi_email'           => "Utilisez l'action d'envoi groupé depuis la liste une fois les attestations générées."
);

// -----------------------------------------------------------------------------
// Header + onglet
// -----------------------------------------------------------------------------
$title = T('ModeEmploiSap', $f['ModeEmploiSap']);
llxHeader('', $title);

$head = array();
$head[0][0] = DOL_URL_ROOT . '/custom/' . $moduleDir . '/help.php';
$head[0][1] = $title;
$head[0][2] = 'help';

print dol_get_fiche_head($head, 'help', $title);
?>

<style>
/* -------- Styles page d'aide SAP (pro) -------- */
.sap-help {
  --sap-blue: #0b5aa2;
  --sap-gray: #333;
  --sap-soft: #f6f8fb;
  --sap-border: #e3e6ea;
  --sap-soft2: #f8f9fb;
}
.sap-help h1, .sap-help h2, .sap-help h3 { color: var(--sap-blue); }
.sap-help h2 { margin-top: 1.2rem; font-size: 1.4em; }
.sap-help h3 { margin-top: 1rem; font-size: 1.15em; color: var(--sap-gray); }
.sap-help p, .sap-help li, .sap-help dd { font-size: 0.95em; line-height: 1.5; }

.sap-help .sap-callout {
  background: var(--sap-soft);
  border: 1px solid var(--sap-border);
  padding: 12px;
  border-radius: 6px;
  margin: 0.5rem 0 1rem;
}

/* ---- Sommaire (2 colonnes) ---- */
.sap-help .sap-toc {
  background: var(--sap-soft2);
  border: 1px solid var(--sap-border);
  padding: 12px;
  border-radius: 6px;
  margin: 0.5rem 0 1rem;
}
.sap-help .sap-toc .grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(220px, 1fr));
  gap: 6px 16px;
}
.sap-help .sap-toc .sap-link {
  text-decoration: none;
  color: var(--sap-blue);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.sap-help .sap-toc .sap-link:hover { text-decoration: underline; }

/* Neutralisation totale de ::before/::after pour éviter attr(href) */
.sap-help .sap-toc *, .sap-help .sap-toc *::before, .sap-help .sap-toc *::after {
  content: none !important;
}

/* ---- Encadrés cartes ---- */
.sap-help .sap-card {
  border: 1px solid var(--sap-border);
  border-radius: 8px;
  padding: 12px 14px;
  margin: 12px 0;
  background: #fff;
}
.sap-help .sap-card .title {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-weight: bold;
  color: var(--sap-blue);
  margin-bottom: 6px;
}

/* ---- Badges ---- */
.sap-help .badge {
  display: inline-block;
  font-size: 0.85em;
  background: #eef3f8;
  border: 1px solid #d9e2ec;
  border-radius: 12px;
  padding: 2px 8px;
  margin-left: 6px;
}

/* ---- Astuce ---- */
.sap-help .kbd {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
  background: #eef3f8;
  border: 1px solid #d9e2ec;
  border-radius: 4px;
  padding: 0 4px;
}

/* ---- Listes ---- */
.sap-help ul, .sap-help ol { margin-left: 1.2rem; }
.sap-help dl dt { font-weight: bold; margin-top: 0.5rem; }
.sap-help .sap-section { margin-bottom: 1.25rem; }

/* Responsive sommaire */
@media (max-width: 960px) {
  .sap-help .sap-toc .grid { grid-template-columns: 1fr; }
}
</style>

<!-- Mini JS pour faire défiler vers les sections -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.sap-toc .sap-link[data-target]').forEach(function(el) {
    el.addEventListener('click', function(ev) {
      ev.preventDefault();
      var id = el.getAttribute('data-target');
      var target = document.querySelector(id);
      if (target) target.scrollIntoView({behavior:'smooth', block:'start'});
    });
  });
});
</script>

<?php
// Petit utilitaire pour insérer un SVG inline (icônes propres sans dépendance)
function sap_icon($name, $size = 18, $color = '#0b5aa2') {
    $icons = array(
        'overview'  => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 6h18M3 12h18M3 18h18" stroke="'.$color.'" stroke-width="2" stroke-linecap="round"/></svg>',
        'dashboard' => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="3" width="8" height="8" stroke="'.$color.'" stroke-width="2"/><rect x="13" y="3" width="8" height="5" stroke="'.$color.'" stroke-width="2"/><rect x="13" y="10" width="8" height="11" stroke="'.$color.'" stroke-width="2"/><rect x="3" y="13" width="8" height="8" stroke="'.$color.'" stroke-width="2"/></svg>',
        'generate'  => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="'.$color.'" stroke-width="2" stroke-linecap="round"/></svg>',
        'quote'     => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 4h9l5 5v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z" stroke="'.$color.'" stroke-width="2"/><path d="M14 4v5h5" stroke="'.$color.'" stroke-width="2"/></svg>',
        'invoice'   => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="3" width="18" height="18" rx="2" stroke="'.$color.'" stroke-width="2"/><path d="M7 8h10M7 12h10M7 16h6" stroke="'.$color.'" stroke-width="2" stroke-linecap="round"/></svg>',
        'settings'  => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z" stroke="'.$color.'" stroke-width="2"/><path d="M19.4 15a7.98 7.98 0 0 0 .1-2 7.98 7.98 0 0 0-.1-2l2.1-1.6-2-3.4-2.5 1a8.2 8.2 0 0 0-1.7-1l-.4-2.7H11l-.4 2.7a8.2 8.2 0 0 0-1.7 1l-2.5-1-2 3.4L5.5 11a7.98 7.98 0 0 0-.1 2 7.98 7.98 0 0 0 .1 2l-2.1 1.6 2 3.4 2.5-1c.5.4 1.1.7 1.7 1l.4 2.7h4.8l.4-2.7c.6-.3 1.2-.6 1.7-1l2.5 1 2-3.4-2.1-1.6z" stroke="'.$color.'" stroke-width="2"/></svg>',
        'tips'      => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2a7 7 0 0 1 7 7c0 2-1 3.8-2.6 5.1L15 16v2H9v-2l-1.4-1.9A6.9 6.9 0 0 1 5 9a7 7 0 0 1 7-7z" stroke="'.$color.'" stroke-width="2"/><path d="M9 22h6" stroke="'.$color.'" stroke-width="2" stroke-linecap="round"/></svg>',
        'faq'       => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="9" stroke="'.$color.'" stroke-width="2"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.8.4-1 1-1 2.2M12 17h.01" stroke="'.$color.'" stroke-width="2" stroke-linecap="round"/></svg>',
    );
    return isset($icons[$name]) ? $icons[$name] : '';
}
?>

<div class="sap-help">
  <!-- Bandeau introductif -->
  <p class="sap-callout"><?php echo T('RappelSAPIntro', $f['RappelSAPIntro']); ?></p>

  <!-- Sommaire (2 colonnes, pseudo-liens) -->
  <nav class="sap-toc" aria-label="Sommaire SAP">
    <strong><?php echo T('AttestationSAPUserGuide', $f['AttestationSAPUserGuide']); ?></strong>
    <div class="grid">
      <span class="sap-link" data-target="#overview"><?php echo sap_icon('overview'); ?> <?php echo T('Vue_d_ensemble', $f['Vue_d_ensemble']); ?></span>
      <span class="sap-link" data-target="#dashboard"><?php echo sap_icon('dashboard'); ?> <?php echo T('Tableau_de_bord', $f['Tableau_de_bord']); ?></span>
      <span class="sap-link" data-target="#generate"><?php echo sap_icon('generate'); ?> <?php echo T('Generer_attestations', $f['Generer_attestations']); ?></span>
      <span class="sap-link" data-target="#quotes"><?php echo sap_icon('quote'); ?> <?php echo T('Devis_SAP', $f['Devis_SAP']); ?></span>
      <span class="sap-link" data-target="#invoices"><?php echo sap_icon('invoice'); ?> <?php echo T('Facture_SAP', $f['Facture_SAP']); ?></span>
      <span class="sap-link" data-target="#settings"><?php echo sap_icon('settings'); ?> <?php echo T('Parametres_SAP', $f['Parametres_SAP']); ?></span>
      <span class="sap-link" data-target="#bestpractices"><?php echo sap_icon('tips'); ?> <?php echo T('Bonnes_pratiques', $f['Bonnes_pratiques']); ?></span>
      <span class="sap-link" data-target="#faq"><?php echo sap_icon('faq'); ?> <?php echo T('FAQ', $f['FAQ']); ?></span>
    </div>
  </nav>

  <!-- Vue d'ensemble -->
  <section id="overview" class="sap-section sap-card">
    <div class="title"><?php echo sap_icon('overview'); ?> <?php echo T('Vue_d_ensemble', $f['Vue_d_ensemble']); ?></div>
    <p><?php echo T('Vue_d_ensemble_text', $f['Vue_d_ensemble_text']); ?></p>
  </section>

  <!-- Tableau de bord -->
  <section id="dashboard" class="sap-section sap-card">
    <div class="title"><?php echo sap_icon('dashboard'); ?> <?php echo T('Tableau_de_bord', $f['Tableau_de_bord']); ?></div>
    <ul>
      <li><?php echo T('DashboardKPI', $f['DashboardKPI']); ?></li>
      <li><?php echo T('LiensRapides', $f['LiensRapides']); ?></li>
    </ul>
  </section>

  <!-- Générer les attestations -->
  <section id="generate" class="sap-section sap-card">
    <div class="title"><?php echo sap_icon('generate'); ?> <?php echo T('Generer_attestations', $f['Generer_attestations']); ?>
      <span class="badge">PDF</span>
      <span class="badge">E-mail</span>
    </div>
    <ol>
      <li><?php echo T('Choix_annee_fiscale', $f['Choix_annee_fiscale']); ?></li>
      <li><?php echo T('Filtrer_clients', $f['Filtrer_clients']); ?></li>
      <li><?php echo T('Generation_action', $f['Generation_action']); ?></li>
      <li><?php echo T('Envoi_email', $f['Envoi_email']); ?></li>
    </ol>
    <p><span class="kbd">Astuce</span> : <?php echo T('Archivage', $f['Archivage']); ?></p>
  </section>

  <!-- Devis SAP -->
  <section id="quotes" class="sap-section sap-card">
    <div class="title"><?php echo sap_icon('quote'); ?> <?php echo T('Devis_SAP', $f['Devis_SAP']); ?></div>
    <p><?php echo T('Devis_SAP_desc', $f['Devis_SAP_desc']); ?></p>
  </section>

  <!-- Facture SAP -->
  <section id="invoices" class="sap-section sap-card">
    <div class="title"><?php echo sap_icon('invoice'); ?> <?php echo T('Facture_SAP', $f['Facture_SAP']); ?></div>
    <p><?php echo T('Facture_SAP_desc', $f['Facture_SAP_desc']); ?></p>
    <p><span class="kbd">TVA</span> : <?php echo T('CheckTVA', $f['CheckTVA']); ?></p>
  </section>

  <!-- Paramètres SAP -->
  <section id="settings" class="sap-section sap-card">
    <div class="title"><?php echo sap_icon('settings'); ?> <?php echo T('Parametres_SAP', $f['Parametres_SAP']); ?></div>
    <ul>
      <li><?php echo T('ParamAnnee', $f['ParamAnnee']); ?></li>
      <li><?php echo T('ParamMentionsLegales', $f['ParamMentionsLegales']); ?></li>
      <li><?php echo T('ParamEmailModele', $f['ParamEmailModele']); ?></li>
    </ul>
  </section>

  <!-- Bonnes pratiques -->
  <section id="bestpractices" class="sap-section sap-card">
    <div class="title"><?php echo sap_icon('tips'); ?> <?php echo T('Bonnes_pratiques', $f['Bonnes_pratiques']); ?></div>
    <ul>
      <li><?php echo T('CheckTVA', $f['CheckTVA']); ?></li>
      <li><?php echo T('DataQuality', $f['DataQuality']); ?></li>
      <li><?php echo T('Archivage', $f['Archivage']); ?></li>
    </ul>
  </section>

  <!-- FAQ -->
  <section id="faq" class="sap-section sap-card">
    <div class="title"><?php echo sap_icon('faq'); ?> <?php echo T('FAQ', $f['FAQ']); ?></div>
    <dl>
      <dt><?php echo T('Q_annee', $f['Q_annee']); ?></dt>
      <dd><?php echo T('R_annee', $f['R_annee']); ?></dd>
      <dt><?php echo T('Q_envoi_email', $f['Q_envoi_email']); ?></dt>
      <dd><?php echo T('R_envoi_email', $f['R_envoi_email']); ?></dd>
    </dl>
  </section>
</div>

<?php
print dol_get_fiche_end();
llxFooter();
$db->close();
