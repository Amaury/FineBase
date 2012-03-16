<?php

if (!class_exists("IOException")) {

/**
 * Objet de gestion des exceptions IO.
 *
 * @author	Amaury Bouchard <amaury.bouchard@finemedia.fr>
 * @copyright	© 2007-2009, FineMedia
 * @package	FineBase
 * @subpackage	Exception
 * @version	$Id: IOException.php 569 2011-04-15 17:06:33Z abouchard $
 */
class IOException extends Exception {
	/** Constante d'erreur fondamentale. */
	const FUNDAMENTAL = 0;
	/** Constante d'erreur de fichier introuvable. */
	const NOT_FOUND = 1;
	/** Constante d'erreur de lecture. */
	const UNREADABLE = 2;
	/** Constante d'erreur d'écriture. */
	const UNWRITABLE = 3;
	/** Constante d'erreur de fichier mal formé. */
	const BAD_FORMAT = 4;
	/** Constante d'erreur de fichier impossible à locker. */
	const UNLOCKABLE = 5;
}

} // class_exists

?>
