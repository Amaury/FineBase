#!/usr/bin/php5
<?php

/**
 * Script de visualisation de fichier JSON.
 *
 * @author	Amaury Bouchard <amaury.bouchard@finemedia.fr>
 * @copyright	Copyright (c) 2007, FineMedia
 * @version	$Id$
 */

/** Ecrit la documentation du programme et quitte. */
function usage() {
	print("Usage: exec.showJson.php file.json\n");
	exit(1);
}

// vérification des paramètres
if ($_SERVER["argc"] != 2 || $_SERVER["argv"][1] == "-h" || $_SERVER["argv"][1] == "--help")
	usage();

print_r(json_decode(file_get_contents($_SERVER["argv"][1]), true));

?>
