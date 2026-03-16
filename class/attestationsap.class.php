
<?php
/**
 * Class AttestationSAP
 * Gestion des attestations fiscales pour les Services à la Personne
 */
class AttestationSAP
{
    /**
     * @var DoliDB Database handler
     */
    public $db;

    /**
     * @var array Errors
     */
    public $errors = array();

    /**
     * @var string Last error
     */
    public $error = '';

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Condition stricte pour identifier les factures SAP :
     * - Modèle PDF exactement 'facture_sap_v3'
     * - Optionnel: uniquement les clients 'particuliers' (ATTESTATIONSAP_ONLY_INDIVIDUAL = 1)
     *
     * @return array Array [ 'where' => '...', 'join' => '...' ]
     */
    private function getStrictSapFilter()
    {
        global $conf;
        $where = " f.model_pdf = 'facture_sap_v3' ";
        $join  = ""; // joins additionnels si besoin

        // Si activé, ne garder que les particuliers (exclure les entreprises)
        // Dolibarr: champ s.typent (2 = individual dans la plupart des installations)
        $onlyIndividual = (int) getDolGlobalInt('ATTESTATIONSAP_ONLY_INDIVIDUAL', 0);
        if ($onlyIndividual === 1) {
            $where .= " AND s.typent = 2 ";
        }

        return array('where' => $where, 'join' => $join);
    }

    /* ============================================================
     * Liste des clients SAP avec totaux annuels et heures
     * ============================================================ */

    /**
     * Get totals by year for all SAP clients
     *
     * @param int $year Year to filter
     * @return array Array of objects with socid, client, total_ttc, hours
     */
    public function getTotalsByYear($year)
    {
        $year = (int) $year;
        $flt = $this->getStrictSapFilter();

        // Totaux TTC par client
        $sql = "SELECT s.rowid AS socid,
                       s.nom AS client,
                       SUM(f.total_ttc) AS total_ttc
                FROM ".MAIN_DB_PREFIX."societe s
                INNER JOIN ".MAIN_DB_PREFIX."facture f ON f.fk_soc = s.rowid
                ".$flt['join']."
                WHERE f.paye = 1
                  AND f.fk_statut = 2
                  AND f.type = 0
                  AND ".$flt['where']."
                  AND YEAR(f.datef) = ".$year."
                GROUP BY s.rowid, s.nom
                ORDER BY s.nom";

        dol_syslog(__METHOD__." SQL: ".$sql, LOG_DEBUG);
        $resql = $this->db->query($sql);
        $out = array();

        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $obj->total_ttc = $obj->total_ttc ? (float) $obj->total_ttc : 0;
                $obj->hours = $this->getHoursByYearAndClient($obj->socid, $year);
                $out[] = $obj;
            }
            $this->db->free($resql);
        } else {
            $this->error = $this->db->lasterror();
            $this->errors[] = $this->error;
            dol_syslog(__METHOD__." Error: ".$this->error, LOG_ERR);
        }
        return $out;
    }

    /* ============================================================
     * Total TTC d'un client pour une année
     * ============================================================ */

    /**
     * Get client total for a specific year
     *
     * @param int $socid Third party ID
     * @param int $year Year to filter
     * @return object|null Object with socid, client, total_ttc or null
     */
    public function getClientTotal($socid, $year)
    {
        $socid = (int) $socid;
        $year  = (int) $year;
        if ($socid <= 0 || $year <= 0) {
            $this->error = 'Invalid parameters';
            return null;
        }
        $flt = $this->getStrictSapFilter();

        $sql = "SELECT s.rowid AS socid,
                       s.nom AS client,
                       SUM(f.total_ttc) AS total_ttc
                FROM ".MAIN_DB_PREFIX."societe s
                INNER JOIN ".MAIN_DB_PREFIX."facture f ON f.fk_soc = s.rowid
                ".$flt['join']."
                WHERE f.paye = 1
                  AND f.fk_statut = 2
                  AND f.type = 0
                  AND ".$flt['where']."
                  AND YEAR(f.datef) = ".$year."
                  AND s.rowid = ".$socid."
                GROUP BY s.rowid, s.nom";

        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            $this->db->free($resql);
            return $obj;
        } else {
            $this->error = $this->db->lasterror();
            $this->errors[] = $this->error;
            dol_syslog(__METHOD__." Error: ".$this->error, LOG_ERR);
        }
        return null;
    }

    /* ============================================================
     * Nombre total d'heures facturées (quantités)
     * ============================================================ */

    /**
     * Get total hours for a client and year
     *
     * @param int $socid Third party ID
     * @param int $year Year to filter
     * @return float Total hours
     */
    public function getHoursByYearAndClient($socid, $year)
    {
        $socid = (int) $socid;
        $year  = (int) $year;
        if ($socid <= 0 || $year <= 0) {
            $this->error = 'Invalid parameters';
            return 0;
        }
        $flt = $this->getStrictSapFilter();

        $sql = "SELECT SUM(fd.qty) AS hours
                FROM ".MAIN_DB_PREFIX."facture f
                INNER JOIN ".MAIN_DB_PREFIX."facturedet fd ON fd.fk_facture = f.rowid
                INNER JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = f.fk_soc
                WHERE f.paye = 1
                  AND f.fk_statut = 2
                  AND f.type = 0
                  AND ".$flt['where']."
                  AND YEAR(f.datef) = ".$year."
                  AND f.fk_soc = ".$socid;

        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            $this->db->free($resql);
            return $obj->hours ? (float) $obj->hours : 0;
        } else {
            $this->error = $this->db->lasterror();
            $this->errors[] = $this->error;
            dol_syslog(__METHOD__." Error: ".$this->error, LOG_ERR);
        }
        return 0;
    }

    /* ============================================================
     * Liste des factures d'un client sur l'année
     * ============================================================ */

    /**
     * Get invoices for a client and year
     *
     * @param int $socid Third party ID
     * @param int $year Year to filter
     * @return array Array of invoice objects
     */
    public function getInvoicesByClientYear($socid, $year)
    {
        $socid = (int) $socid;
        $year  = (int) $year;
        if ($socid <= 0 || $year <= 0) {
            $this->error = 'Invalid parameters';
            return array();
        }
        $flt = $this->getStrictSapFilter();

        $sql = "SELECT f.rowid, f.ref, f.datef, f.total_ttc
                FROM ".MAIN_DB_PREFIX."facture f
                INNER JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = f.fk_soc
                WHERE f.fk_soc = ".$socid."
                  AND f.paye = 1
                  AND f.fk_statut = 2
                  AND f.type = 0
                  AND ".$flt['where']."
                  AND YEAR(f.datef) = ".$year."
                ORDER BY f.datef";

        dol_syslog(__METHOD__." SQL: ".$sql, LOG_DEBUG);
        $resql = $this->db->query($sql);
        $out = array();
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $out[] = $obj;
            }
            $this->db->free($resql);
            dol_syslog(__METHOD__." Found ".count($out)." invoices for client ".$socid." in ".$year, LOG_DEBUG);
        } else {
            $this->error = $this->db->lasterror();
            $this->errors[] = $this->error;
            dol_syslog(__METHOD__." Error: ".$this->error, LOG_ERR);
        }
        return $out;
    }

    /* ============================================================
     * Alias
     * ============================================================ */

    public function getFactures($socid, $year)
    {
        return $this->getInvoicesByClientYear($socid, $year);
    }

    /* ============================================================
     * Check existence
     * ============================================================ */

    public function hasInvoices($socid, $year)
    {
        $socid = (int) $socid;
        $year  = (int) $year;
        if ($socid <= 0 || $year <= 0) return false;

        $flt = $this->getStrictSapFilter();

        $sql = "SELECT COUNT(*) as nb
                FROM ".MAIN_DB_PREFIX."facture f
                INNER JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = f.fk_soc
                WHERE f.fk_soc = ".$socid."
                  AND f.paye = 1
                  AND f.fk_statut = 2
                  AND f.type = 0
                  AND ".$flt['where']."
                  AND YEAR(f.datef) = ".$year;

        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            $this->db->free($resql);
            return ($obj && $obj->nb > 0);
        }
        return false;
    }

    /* ============================================================
     * Statistiques globales
     * ============================================================ */

    public function getYearStats($year)
    {
        $year = (int) $year;
        $flt = $this->getStrictSapFilter();

        $sql = "SELECT COUNT(DISTINCT f.fk_soc) as total_clients,
                       COUNT(f.rowid) as total_invoices,
                       SUM(f.total_ttc) as total_ttc,
                       SUM(fd.qty) as total_hours
                FROM ".MAIN_DB_PREFIX."facture f
                INNER JOIN ".MAIN_DB_PREFIX."facturedet fd ON fd.fk_facture = f.rowid
                INNER JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = f.fk_soc
                WHERE f.paye = 1
                  AND f.fk_statut = 2
                  AND f.type = 0
                  AND ".$flt['where']."
                  AND YEAR(f.datef) = ".$year;

        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            $this->db->free($resql);
            return array(
                'total_clients' => $obj->total_clients ? (int) $obj->total_clients : 0,
                'total_invoices'=> $obj->total_invoices ? (int) $obj->total_invoices : 0,
                'total_ttc'     => $obj->total_ttc ? (float) $obj->total_ttc : 0,
                'total_hours'   => $obj->total_hours ? (float) $obj->total_hours : 0
            );
        }
        return array(
            'total_clients' => 0,
            'total_invoices'=> 0,
            'total_ttc'     => 0,
            'total_hours'   => 0
        );
    }

    /* ============================================================
     * Utilitaires
     * ============================================================ */

    public function markInvoiceAsSap($facid)
    {
        $facid = (int) $facid;
        if ($facid <= 0) {
            $this->error = 'Invalid invoice ID';
            return -1;
        }
        $sql = "UPDATE ".MAIN_DB_PREFIX."facture
                SET model_pdf = 'facture_sap_v3'
                WHERE rowid = ".$facid;
        $resql = $this->db->query($sql);
        if ($resql) {
            dol_syslog(__METHOD__." Invoice ".$facid." marked as SAP", LOG_INFO);
            return 1;
        } else {
            $this->error = $this->db->lasterror();
            $this->errors[] = $this->error;
            dol_syslog(__METHOD__." Error: ".$this->error, LOG_ERR);
            return -1;
        }
    }

    public function normalizeExistingSapInvoices()
    {
        // Normaliser en 'facture_sap_v3' (exact)
        $sql = "UPDATE ".MAIN_DB_PREFIX."facture
                SET model_pdf = 'facture_sap_v3'
                WHERE LOWER(model_pdf) LIKE '%facture_sap%'
                  AND model_pdf <> 'facture_sap_v3'";
        $resql = $this->db->query($sql);
        if ($resql) {
            $affected = $this->db->affected_rows($resql);
            dol_syslog(__METHOD__." Normalized ".$affected." SAP invoices", LOG_INFO);
            return $affected;
        } else {
            $this->error = $this->db->lasterror();
            $this->errors[] = $this->error;
            dol_syslog(__METHOD__." Error: ".$this->error, LOG_ERR);
            return -1;
        }
    }
}
