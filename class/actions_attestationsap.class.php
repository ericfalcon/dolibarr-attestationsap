
<?php
/**
 * htdocs/custom/attestationsap/class/actions_attestationsap.class.php
 *
 * Hooks Attestations SAP :
 * - Pré‑sélection des modèles PDF SAP si sap_mode=1
 * - Remplit systématiquement 'model', 'modelpdf' et 'doctemplate' (ODT neutralisé)
 * - Positionne le modèle sur l'objet (setModelPdf si disponible)
 * - Nettoie l’UI pour masquer le suffixe ": Aucun" lorsque l’ODT est vide :
 *     * dans le libellé du champ
 *     * dans les <option> du <select> (création et formbuilddoc), y compris après refresh AJAX
 *
 * Contextes : propalcard, invoicecard, formbuilddoc, pdfgeneration
 */

class ActionsAttestationsap
{
    /** @var DoliDB */
    public $db;

    /** @var array */
    public $errors = array();

    public function __construct($db)
    {
        $this->db = $db;
    }

    /* ================== Helpers ================== */

    /**
     * Retourne la liste des contextes actifs pour le hook courant.
     *
     * @param array $parameters
     * @return string[]
     */
    private function getContexts($parameters)
    {
        $contexts = array();
        if (!empty($parameters['context'])) $contexts = array_merge($contexts, explode(':', $parameters['context']));
        if (!empty($parameters['currentcontext'])) $contexts[] = $parameters['currentcontext'];
        return array_values(array_unique(array_filter($contexts)));
    }

    /**
     * Lit les entrées utiles envoyées en GET/POST pour décider du modèle.
     *
     * @return array [int $sap_mode, string $picked]
     */
    private function readInputs()
    {
        $sap_mode = (int) GETPOST('sap_mode', 'int');
        $picked = '';
        foreach (array('model','modelpdf','model_pdf','modelepdf','doctemplate') as $k) {
            $val = GETPOST($k, 'alpha');
            if (is_string($val) && strlen(trim($val))) { $picked = trim($val); break; }
        }
        return array($sap_mode, $picked);
    }

    /**
     * Détermine le modèle cible à utiliser (PDF).
     *
     * @param string[] $contexts
     * @param int $sap_mode
     * @param string $picked
     * @param Object $object
     * @return string
     */
    private function decideTargetModel($contexts, $sap_mode, $picked, $object)
    {
        if (!empty($picked)) return $picked;

        if ($sap_mode === 1) {
            $isPropal  = ($object && ($object->element === 'propal'  || $object->table_element === 'propal'));
            $isInvoice = ($object && ($object->element === 'facture' || $object->table_element === 'facture'));

            if ($isPropal && (in_array('propalcard', $contexts) || in_array('formbuilddoc', $contexts))) return 'devis_sap_v2';
            if ($isInvoice && (in_array('invoicecard', $contexts) || in_array('formbuilddoc', $contexts))) return 'facture_sap_v3';
        }

        if ($object && !empty($object->model_pdf)) return $object->model_pdf;
        if ($object && !empty($object->modelpdf))  return $object->modelpdf;
        return '';
    }

    /**
     * Applique le nom de modèle sur l'objet (compat core).
     *
     * @param Object $object
     * @param string $modelname
     * @return void
     */
    private function applyModelOnObject(&$object, $modelname)
    {
        $object->model_pdf = $modelname;
        $object->modelpdf  = $modelname;
        if (method_exists($object, 'setModelPdf')) {
            $res = $object->setModelPdf($modelname);
            dol_syslog(__METHOD__." setModelPdf(".$modelname.") => ".$res, ($res>0)?LOG_INFO:LOG_ERR);
        }
    }

    /**
     * Injecte les inputs cachés pour garantir le POST attendu par Dolibarr.
     * On neutralise volontairement l'ODT (doctemplate vide) pour prioriser le PDF.
     *
     * @param string $modelname
     * @return void
     */
    private function printHiddenModelInputs($modelname)
    {
        print '<input type="hidden" name="model" value="'.dol_escape_htmltag($modelname).'">'."\n";
        print '<input type="hidden" name="modelpdf" value="'.dol_escape_htmltag($modelname).'">'."\n";
        print '<input type="hidden" name="doctemplate" value="">'."\n"; // ODT neutralisé
    }

    /**
     * Nettoyage UI :
     *  - masque ": Aucun" dans le label si présent
     *  - enlève ": Aucun" dans les options <select> et dans la valeur affichée
     *  - résiste aux rafraîchissements dynamiques grâce à un MutationObserver
     *
     * @return void
     */
    private function printCleanModelUI()
    {
        // Petit style pour éviter les clignotements lors du nettoyage
        print '<style>.attestationsap-cleaning{visibility:hidden}</style>'."\n";

        print '<script>
        (function(){
            var SUFFIX_RE = /:\\s*Aucun$/i;

            function cleanLabelText(node){
                try{
                    var txt = (node.innerText||"").trim();
                    if(SUFFIX_RE.test(txt)){
                        node.classList.add("attestationsap-cleaning");
                        node.innerHTML = node.innerHTML.replace(/:\\s*Aucun(<\\/[^>]+>)?$/i,"$1");
                        node.classList.remove("attestationsap-cleaning");
                    }
                }catch(e){}
            }

            function cleanOptions(selectEl){
                try{
                    var changed=false;
                    var opts = selectEl && selectEl.options ? selectEl.options : [];
                    for(var i=0;i<opts.length;i++){
                        var t = opts[i].text||"";
                        if(SUFFIX_RE.test(t)){
                            opts[i].text = t.replace(SUFFIX_RE,"");
                            changed=true;
                        }
                    }
                    // forcer redraw si l’option sélectionnée a changé de texte
                    if(changed){
                        var idx = selectEl.selectedIndex;
                        selectEl.selectedIndex = idx;
                        // Tenter un refresh des widgets liés
                        var ev = document.createEvent("HTMLEvents");
                        ev.initEvent("change", true, false);
                        selectEl.dispatchEvent(ev);
                    }
                }catch(e){}
            }

            function cleanAutocompleteLis(container){
                try{
                    var lis = container.querySelectorAll("li, .ui-menu-item, .dropdown-item");
                    lis.forEach(function(li){
                        var t = (li.innerText||"").trim();
                        if(SUFFIX_RE.test(t)){
                            li.innerHTML = li.innerHTML.replace(SUFFIX_RE,"");
                        }
                    });
                }catch(e){}
            }

            function initialSweep(root){
                // 1) labels proches du champ "Modèle de document"
                var labelCandidates = root.querySelectorAll("label, .titre, .fieldlabel, .tdoverflowwrap, .fichehalf, .ficheaddleft");
                labelCandidates.forEach(cleanLabelText);

                // 2) tous les selects (certains écrans n’assignent pas d’id stable)
                var selects = root.querySelectorAll("select");
                selects.forEach(function(sel){ cleanOptions(sel); });

                // 3) items des listes d’auto-complétion si présents
                cleanAutocompleteLis(root);
            }

            function runInitial(){
                initialSweep(document);
            }

            if(document.readyState==="complete" || document.readyState==="interactive"){
                runInitial();
            }else{
                document.addEventListener("DOMContentLoaded", runInitial);
            }

            // Observer les mutations pour nettoyer à la volée (AJAX, re-render, etc.)
            var obs = new MutationObserver(function(muts){
                try{
                    muts.forEach(function(m){
                        if(m.type==="childList"){
                            m.addedNodes.forEach(function(n){
                                if(n.nodeType!==1) return;

                                // <select> ajouté/rafraîchi
                                if(n.tagName==="SELECT"){
                                    cleanOptions(n);
                                    return;
                                }

                                // container avec selects / labels / listes
                                var sels = n.querySelectorAll ? n.querySelectorAll("select") : [];
                                sels.forEach(function(sel){ cleanOptions(sel); });

                                var lbls = n.querySelectorAll ? n.querySelectorAll("label, .titre, .fieldlabel, .tdoverflowwrap, .fichehalf, .ficheaddleft") : [];
                                lbls.forEach(cleanLabelText);

                                cleanAutocompleteLis(n);

                                // cas particulier : option brute insérée
                                if(n.tagName==="OPTION"){
                                    var t = n.text||"";
                                    if(SUFFIX_RE.test(t)){ n.text = t.replace(SUFFIX_RE,""); }
                                }
                            });
                        } else if(m.type==="characterData"){
                            // ex: un label mis à jour par JS
                            var p = m.target && m.target.parentNode ? m.target.parentNode : null;
                            if(p){ cleanLabelText(p); }
                        }
                    });
                }catch(e){}
            });

            try{
                obs.observe(document.body, { childList:true, subtree:true, characterData:true });
            }catch(e){}
        })();
        </script>'."\n";
    }

    /* ================== Hooks écrans ================== */

    /**
     * Avant rendu du formulaire (création d'objet)
     *
     * @return int 0=ok
     */
    public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
    {
        $contexts = $this->getContexts($parameters);
        list($sap_mode, $picked) = $this->readInputs();
        $target = $this->decideTargetModel($contexts, $sap_mode, $picked, $object);

        // Devis
        if (in_array('propalcard', $contexts) && $action === 'create') {
            if ($target === 'devis_sap_v2' || $target === 'devis_sap') {
                $this->applyModelOnObject($object, $target);
                $this->printHiddenModelInputs($target);
                $this->printCleanModelUI(); // Nettoyage libellé + options
            }
        }

        // Facture
        if (in_array('invoicecard', $contexts) && $action === 'create') {
            if ($target === 'facture_sap_v3' || $target === 'facture_sap') {
                $this->applyModelOnObject($object, $target);
                $this->printHiddenModelInputs($target);
                $this->printCleanModelUI(); // Nettoyage libellé + options
            }
        }

        return 0;
    }

    /**
     * Formulaire “Générer document” : positionne aussi le modèle.
     *
     * @return int 0=ok
     */
    public function formBuilddocOptions($parameters, &$object, &$action, $hookmanager)
    {
        $contexts = $this->getContexts($parameters);
        if (!in_array('formbuilddoc', $contexts)) return 0;

        list($sap_mode, $picked) = $this->readInputs();
        $target = $this->decideTargetModel($contexts, $sap_mode, $picked, $object);
        if (!$target) return 0;

        $this->applyModelOnObject($object, $target);
        $this->printHiddenModelInputs($target);
        $this->printCleanModelUI(); // Nettoyage libellé + options
        return 0;
    }

    /**
     * Juste avant la génération du PDF : impose le modèle (filet de sécurité).
     *
     * @return int 0=ok
     */
    public function beforePDFCreation($parameters, &$object, &$action, $hookmanager)
    {
        $contexts = $this->getContexts($parameters);
        if (!in_array('pdfgeneration', $contexts)) return 0;

        list($sap_mode, $picked) = $this->readInputs();
        $target = $this->decideTargetModel($contexts, $sap_mode, $picked, $object);
        if (!$target) return 0;

        $this->applyModelOnObject($object, $target);
        return 0;
    }
}
