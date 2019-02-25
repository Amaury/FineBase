<?php

require_once('finebase/FineLog.php');

/**
 * Objet de chargement automatiquement d'objets PHP.
 *
 * @author	Amaury Bouchard <amaury@amaury.net>
 * @copyright	© 2012, Fine Media
 * @see		Norme PSR-0.
 * @package	FineBase
 * @version	$Id$
 */
class FineAutoload {
	/**
	 * Lancement de l'autoloader.
	 * @param	string|array	$path	(optionnel) Chemin ou liste de chemins d'inclusion.
	 */
	static public function autoload($path=null) {
		FineLog::log('finebase', FineLog::DEBUG, "FineAutoload started.");
		// configuration de l'autoloader
		spl_autoload_register(function($name) {
			FineLog::log('finebase', FineLog::DEBUG, "Trying to load object '$name'.");
			// transformation du namespace en chemin
			$name = trim($name, '\\');
			$name = str_replace('\\', DIRECTORY_SEPARATOR, $name);
			//$name = str_replace('_', DIRECTORY_SEPARATOR, $name);
			// désactivation des logs de warning, pour gérer les objets introuvables
			$errorReporting = error_reporting();
			error_reporting($errorReporting & ~E_WARNING);
			$included = include("$name.php");
			if ($included === false)
				\FineLog::log('finebase', \FineLog::DEBUG, "Unable to load file '$name.php'.");
			// remise en place de l'ancien niveau de rapport d'erreurs
			error_reporting($errorReporting);
		}, true, true);
		if ($path)
			self::addIncludePath($path);
	}
	/**
	 * Ajout d'un ou plusieurs chemins d'inclusion.
	 * @param	string|array	$path	Chemin ou liste de chemins à ajouter.
	 */
	static public function addIncludePath($path) {
		$path = is_array($path) ? $path : array($path);
		$libPath = implode(PATH_SEPARATOR, $path);
		if (!empty($libPath))
			set_include_path($libPath . PATH_SEPARATOR . get_include_path());
	}
}

?>
