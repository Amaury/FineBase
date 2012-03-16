<?php

if (!class_exists("DatabaseException")) {

/**
 * Objet de gestion des exceptions de base de données.
 *
 * @author	Amaury Bouchard <amaury.bouchard@finemedia.fr>
 * @copyright	© 2007, FineMedia
 * @package	FineBase
 * @subpackage	Exception
 * @version	$Id: DatabaseException.php 569 2011-04-15 17:06:33Z abouchard $
 */
class DatabaseException extends Exception {
	/** Constante d'erreur fondamentale. */
	const FUNDAMENTAL = 0;
	/** Constante d'erreur de connexion. */
	const CONNECTION = 1;
	/** Constante d'erreur de requête. */
	const QUERY = 2;
}

} // class_exists

?>
