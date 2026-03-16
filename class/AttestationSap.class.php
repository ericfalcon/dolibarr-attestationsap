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
        
        // Requête avec calcul des heures directement
        $sql = "SELECT s.rowid AS socid,
                       s.nom AS client,
                       SUM(f.total_ttc) AS total_ttc,
                       SUM(fd.qty) AS hours
                FROM ".MAIN_DB_PREFIX."societe s
                INNER JOIN ".MAIN_DB_PREFIX."facture f ON f.fk_soc = s.rowid
                INNER JOIN ".MAIN_DB_PREFIX."facturedet fd ON fd.fk_facture = f.rowid
                WHERE f.paye = 1
                  AND f.fk_statut = 2
                  AND f.type = 0
                  AND f.model_pdf = 'facture_sap_v3'
                  AND YEAR(f.datef) = ".$year."
                GROUP BY s.rowid, s.nom
                ORDER BY s.nom";
        
        $resql = $this->db->query($sql);
        $out = array();
        
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                // S'assurer que hours n'est jamais null
                $obj->hours = $obj->hours ? (float) $obj->hours : 0;
                $obj->total_ttc = $obj->total_ttc ? (float) $obj->total_ttc : 0;
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
        $year = (int) $year;
        
        if ($socid <= 0 || $year <= 0) {
            $this->error = 'Invalid parameters';
            return null;
        }
        
        $sql = "SELECT s.rowid AS socid,
                       s.nom AS client,
                       SUM(f.total_ttc) AS total_ttc
                FROM ".MAIN_DB_PREFIX."societe s
                INNER JOIN ".MAIN_DB_PREFIX."facture f ON f.fk_soc = s.rowid
                WHERE f.paye = 1
                  AND f.fk_statut = 2
                  AND f.type = 0
                  AND f.model_pdf = 'facture_sap_v3'
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
        $year = (int) $year;
        
        if ($socid <= 0 || $year <= 0) {
            $this->error = 'Invalid parameters';
            return 0;
        }
        
        $sql = "SELECT SUM(fd.qty) AS hours
                FROM ".MAIN_DB_PREFIX."facture f
                INNER JOIN ".MAIN_DB_PREFIX."facturedet fd ON fd.fk_facture = f.rowid
                WHERE f.paye = 1
                  AND f.fk_statut = 2
                  AND f.type = 0
                  AND f.model_pdf = 'facture_sap_v3'
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
        $year = (int) $year;
        
        if ($socid <= 0 || $year <= 0) {
            $this->error = 'Invalid parameters';
            return array();
        }
        
        $sql = "SELECT rowid,
                       ref,
                       datef,
                       total_ttc
                FROM ".MAIN_DB_PREFIX."facture
                WHERE fk_soc = ".$socid."
                  AND paye = 1
                  AND fk_statut = 2
                  AND type = 0
                  AND model_pdf = 'facture_sap_v3'
                  AND YEAR(datef) = ".$year."
                ORDER BY datef";
        
        $resql = $this->db->query($sql);
        $out = array();
        
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
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
     * Alias pour compatibilité (si utilisé ailleurs)
     * ============================================================ */
    /**
     * Alias pour getInvoicesByClientYear
     *
     * @param int $socid Third party ID
     * @param int $year Year to filter
     * @return array Array of invoice objects
     */
    public function getFactures($socid, $year)
    {
        return $this->getInvoicesByClientYear($socid, $year);
    }
    
    /* ============================================================
     * Vérification si un client a des factures SAP
     * ============================================================ */
    /**
     * Check if client has SAP invoices for a year
     *
     * @param int $socid Third party ID
     * @param int $year Year to filter
     * @return bool True if has invoices
     */
    public function hasInvoices($socid, $year)
    {
        $socid = (int) $socid;
        $year = (int) $year;
        
        if ($socid <= 0 || $year <= 0) {
            return false;
        }
        
        $sql = "SELECT COUNT(*) as nb
                FROM ".MAIN_DB_PREFIX."facture
                WHERE fk_soc = ".$socid."
                  AND paye = 1
                  AND fk_statut = 2
                  AND type = 0
                  AND model_pdf = 'facture_sap_v3'
                  AND YEAR(datef) = ".$year;
        
        $resql = $this->db->query($sql);
        
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            $this->db->free($resql);
            return ($obj && $obj->nb > 0);
        }
        
        return false;
    }
    
    /* ============================================================
     * Statistiques globales pour une année
     * ============================================================ */
    /**
     * Get global statistics for a year
     *
     * @param int $year Year to filter
     * @return array Array with total_clients, total_invoices, total_ttc, total_hours
     */
    public function getYearStats($year)
    {
        $year = (int) $year;
        
        $sql = "SELECT COUNT(DISTINCT f.fk_soc) as total_clients,
                       COUNT(f.rowid) as total_invoices,
                       SUM(f.total_ttc) as total_ttc,
                       SUM(fd.qty) as total_hours
                FROM ".MAIN_DB_PREFIX."facture f
                INNER JOIN ".MAIN_DB_PREFIX."facturedet fd ON fd.fk_facture = f.rowid
                WHERE f.paye = 1
                  AND f.fk_statut = 2
                  AND f.type = 0
                  AND f.model_pdf = 'facture_sap_v3'
                  AND YEAR(f.datef) = ".$year;
        
        $resql = $this->db->query($sql);
        
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            $this->db->free($resql);
            
            return array(
                'total_clients' => $obj->total_clients ? (int) $obj->total_clients : 0,
                'total_invoices' => $obj->total_invoices ? (int) $obj->total_invoices : 0,
                'total_ttc' => $obj->total_ttc ? (float) $obj->total_ttc : 0,
                'total_hours' => $obj->total_hours ? (float) $obj->total_hours : 0
            );
        }
        
        return array(
            'total_clients' => 0,
            'total_invoices' => 0,
            'total_ttc' => 0,
            'total_hours' => 0
        );
    }
}