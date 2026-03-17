<?php
/**
 * \file        htdocs/custom/attestationsap/class/SapIntervenants.class.php
 * \ingroup     attestationsap
 * \brief       Gestion des intervenants SAP
 * \version     2.1.0
 *
 * Stratégie universelle (fonctionne pour tous les types de structure) :
 *
 *  SOURCE 1 — Utilisateurs Dolibarr (llx_user)
 *    - Auto-entrepreneur : 1 seul user (le dirigeant)
 *    - Société avec salariés : plusieurs users marqués "intervenant SAP"
 *    - Le module HRM n'est PAS requis
 *
 *  SOURCE 2 — Paramètre texte libre (ATTESTATIONSAP_INTERVENANT_LIBRE)
 *    - Fallback si aucun user configuré
 *    - Permet de saisir manuellement un nom (ex : sous-traitant ponctuel)
 *
 *  AFFICHAGE
 *    - Sur les factures : nom de l'intervenant dans le cadre SAP
 *    - Sur les attestations : liste des intervenants ayant travaillé sur l'année
 *
 * Conformité D.7233-1 Code du travail :
 *   "L'attestation mentionne l'identité du ou des salariés ayant effectué
 *    les interventions et la nature des services rendus."
 *   → Pour un auto-entrepreneur, c'est le dirigeant lui-même.
 */

class SapIntervenants
{
    /** @var DoliDB */
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    // =========================================================================
    // RÉCUPÉRATION DES INTERVENANTS
    // =========================================================================

    /**
     * Retourne la liste des utilisateurs Dolibarr marqués comme intervenants SAP.
     * Si aucun n'est marqué, retourne tous les utilisateurs actifs internes non-externes.
     *
     * @param  int  $entity  Entité Dolibarr
     * @return array         [['id'=>int, 'nom'=>string, 'prenom'=>string, 'fullname'=>string, 'email'=>string], ...]
     */
    public function getIntervenantsUsers($entity = 1)
    {
        $out = array();

        // Chercher tous les utilisateurs internes actifs (y compris admin)
        $sql = "SELECT u.rowid, u.lastname, u.firstname, u.email
                FROM " . MAIN_DB_PREFIX . "user u
                WHERE u.entity IN (0, " . (int)$entity . ")
                  AND u.statut = 1
                  AND (u.fk_soc = 0 OR u.fk_soc IS NULL)
                ORDER BY u.lastname ASC, u.firstname ASC";

        // Fallback : tous les users actifs internes si aucun "employee"
        $res = $this->db->query($sql);
        $found = 0;
        if ($res) {
            while ($o = $this->db->fetch_object($res)) {
                $out[] = $this->_buildUserEntry($o);
                $found++;
            }
            $this->db->free($res);
        }

        if ($found === 0) {
            // Fallback : tous users internes actifs (hors externals)
            $sql2 = "SELECT u.rowid, u.lastname, u.firstname, u.email
                     FROM " . MAIN_DB_PREFIX . "user u
                     WHERE u.entity IN (0, " . (int)$entity . ")
                       AND u.statut = 1
                       AND (u.fk_soc = 0 OR u.fk_soc IS NULL)
                     ORDER BY u.lastname ASC, u.firstname ASC";
            $res2 = $this->db->query($sql2);
            if ($res2) {
                while ($o = $this->db->fetch_object($res2)) {
                    $out[] = $this->_buildUserEntry($o);
                }
                $this->db->free($res2);
            }
        }

        return $out;
    }

    /**
     * Retourne l'intervenant par défaut configuré dans les paramètres.
     * Priorités :
     *  1. ATTESTATIONSAP_INTERVENANT_USER_ID (ID user Dolibarr)
     *  2. Premier user interne actif
     *  3. Texte libre ATTESTATIONSAP_INTERVENANT_LIBRE
     *
     * @param  int  $entity
     * @return array|null  ['id'=>int|0, 'fullname'=>string, 'email'=>string, 'source'=>string]
     */
    public function getIntervenantDefaut($entity = 1)
    {
        $userId = (int)getDolGlobalString('ATTESTATIONSAP_INTERVENANT_USER_ID', 0);

        if ($userId > 0) {
            $sql = "SELECT rowid, lastname, firstname, email
                    FROM " . MAIN_DB_PREFIX . "user
                    WHERE rowid = " . $userId . " AND statut = 1";
            $res = $this->db->query($sql);
            if ($res) {
                $o = $this->db->fetch_object($res);
                $this->db->free($res);
                if ($o) {
                    $entry = $this->_buildUserEntry($o);
                    $entry['source'] = 'user';
                    return $entry;
                }
            }
        }

        // Fallback : premier user interne actif
        $users = $this->getIntervenantsUsers($entity);
        if (!empty($users)) {
            $users[0]['source'] = 'user_auto';
            return $users[0];
        }

        // Fallback texte libre
        $libre = getDolGlobalString('ATTESTATIONSAP_INTERVENANT_LIBRE', '');
        if (!empty($libre)) {
            return array(
                'id'       => 0,
                'nom'      => '',
                'prenom'   => '',
                'fullname' => $libre,
                'email'    => '',
                'source'   => 'libre',
            );
        }

        return null;
    }

    /**
     * Retourne le nom complet de l'intervenant par défaut (pour affichage PDF)
     *
     * @param  int  $entity
     * @return string
     */
    public function getIntervenantDefaultFullname($entity = 1)
    {
        $i = $this->getIntervenantDefaut($entity);
        return $i ? $i['fullname'] : '';
    }

    /**
     * Retourne les intervenants liés à une facture donnée.
     * Cherche via llx_element_element (liens entre objets Dolibarr).
     * Fallback : intervenant par défaut si aucun lien trouvé.
     *
     * @param  int  $fk_facture  ID de la facture
     * @param  int  $entity
     * @return array             Liste de fullnames
     */
    public function getIntervenantsForFacture($fk_facture, $entity = 1)
    {
        $out = array();

        // Chercher les users liés via la table de feuilles de temps (timesheet)
        // ou via element_element si on utilise les fiches d'intervention
        if (isModEnabled('ficheinter')) {
            $sql = "SELECT DISTINCT u.rowid, u.lastname, u.firstname
                    FROM " . MAIN_DB_PREFIX . "element_element ee
                    JOIN " . MAIN_DB_PREFIX . "fichinter fi ON fi.rowid = ee.fk_source
                    JOIN " . MAIN_DB_PREFIX . "fichinterdet fid ON fid.fk_fichinter = fi.rowid
                    LEFT JOIN " . MAIN_DB_PREFIX . "user u ON u.rowid = fid.fk_user
                    WHERE ee.fk_target = " . (int)$fk_facture . "
                      AND ee.targettype = 'facture'
                      AND ee.sourcetype = 'fichinter'
                      AND u.rowid IS NOT NULL
                    ORDER BY u.lastname ASC";
            $res = $this->db->query($sql);
            if ($res) {
                while ($o = $this->db->fetch_object($res)) {
                    $out[] = trim($o->firstname . ' ' . $o->lastname);
                }
                $this->db->free($res);
            }
        }

        // Fallback : intervenant par défaut
        if (empty($out)) {
            $def = $this->getIntervenantDefaultFullname($entity);
            if (!empty($def)) $out[] = $def;
        }

        return array_unique($out);
    }

    /**
     * Retourne la liste consolidée des intervenants sur toute une année pour un client.
     * Agrège les intervenants de toutes les factures de l'année.
     *
     * @param  array  $factures  Objets factures (avec ->rowid)
     * @param  int    $entity
     * @return array             Liste unique de fullnames
     */
    public function getIntervenantsForAnnee($factures, $entity = 1)
    {
        $all = array();
        foreach ($factures as $f) {
            $intervenants = $this->getIntervenantsForFacture($f->rowid, $entity);
            foreach ($intervenants as $name) {
                $all[$name] = true;
            }
        }

        if (empty($all)) {
            $def = $this->getIntervenantDefaultFullname($entity);
            if (!empty($def)) $all[$def] = true;
        }

        return array_keys($all);
    }

    // =========================================================================
    // HELPERS SETUP
    // =========================================================================

    /**
     * Retourne tous les users internes actifs pour le select du setup
     *
     * @param  int  $entity
     * @return array  [['id'=>int, 'fullname'=>string], ...]
     */
    public function getAllUsersForSelect($entity = 1)
    {
        $out = array();
        $sql = "SELECT rowid, lastname, firstname
                FROM " . MAIN_DB_PREFIX . "user
                WHERE entity IN (0, " . (int)$entity . ")
                  AND statut = 1
                  AND (fk_soc = 0 OR fk_soc IS NULL)
                ORDER BY lastname ASC, firstname ASC";
        $res = $this->db->query($sql);
        if ($res) {
            while ($o = $this->db->fetch_object($res)) {
                $out[] = $this->_buildUserEntry($o);
            }
            $this->db->free($res);
        }
        return $out;
    }

    // =========================================================================
    // PRIVÉ
    // =========================================================================

    private function _buildUserEntry($o)
    {
        $prenom   = trim((string)$o->firstname);
        $nom      = trim((string)$o->lastname);
        $fullname = trim($prenom . ' ' . $nom);
        if (empty($fullname)) $fullname = 'User #' . $o->rowid;

        return array(
            'id'       => (int)$o->rowid,
            'nom'      => $nom,
            'prenom'   => $prenom,
            'fullname' => $fullname,
            'email'    => isset($o->email) ? (string)$o->email : '',
            'source'   => 'user',
        );
    }
}
