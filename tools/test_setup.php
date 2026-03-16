<?php
// Test simple
echo "PHP fonctionne<br>";

// Test du chemin
$paths_to_test = array(
    "../../main.inc.php",
    "../../../main.inc.php",
    "../../../../main.inc.php"
);

foreach ($paths_to_test as $path) {
    if (file_exists($path)) {
        echo "✅ Trouvé: $path<br>";
    } else {
        echo "❌ Introuvable: $path<br>";
    }
}

// Afficher le chemin actuel
echo "<br>Répertoire actuel: " . getcwd() . "<br>";
echo "Fichier actuel: " . __FILE__ . "<br>";
?>
