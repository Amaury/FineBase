<?php

if (!class_exists("ApplicationException")) {

/**
 * Objet de gestion des exceptions applicatives.
 *
 * @author	Amaury Bouchard <amaury.bouchard@finemedia.fr>
 * @copyright	© 2007-2010, FineMedia
 * @package	FineBase
 * @subpackage	Exception
 * @version	$Id: ApplicationException.php 569 2011-04-15 17:06:33Z abouchard $
 */
class ApplicationException extends Exception {
	/** Constante d'erreur inconnue. */
	const UNKNOWN = -1;
	/** Constante d'erreur d'appel à une API. */
	const API = 0;
	/** Constante d'erreur système. */
	const SYSTEM = 1;
	/** Constante d'erreur d'authentification. */
	const AUTHENTICATION = 2;
	/** Constante d'erreur d'autorisation */
	const UNAUTHORIZED = 3;
}

} // class_exists

?>
