<?php

require_once('finebase/FineDatasource.php');

/**
 * Objet servant à créer une source de données qui ne fait absolument rien.
 *
 * @author	Amaury Bouchard <amaury.bouchard@finemedia.fr>
 * @copyright	© 2012, Fine Media
 * @package	FineBase
 * @version	$Id$
 */
class FineDummyDatasource extends FineDatasource {
	/**
	 * Usine de fabrication de l'objet.
	 * @param	string	$dsn	DSN de connexion (non utilisé).
	 * @return	FineDummyDatasource	L'objet FineDummyDatasource créé.
	 */
	static public function factory($dsn=null) {
		return (new FineDummyDatasource());
	}
	/**
	 * Quelque soit la méthode appelée sur cet objet, l'appel fonctionnera.
	 * @param	string	$name	Nom de la méthode appelée.
	 * @param	array	$args	Liste des paramètres transmis à la méthode.
	 * @return	null	Cette méthode renvoie toujours null.
	 */
	public function __call($name, $args) {
		FineLog::log('finebase', FineLog::DEBUG, "DummyDatasource called on method '$name'.");
		return (null);
	}
	/**
	 * Fonction qui transmet les données générées par la fonction anonyme passée en paramètre.
	 * @param	string	$key		Clé d'indexation de la donnée.
	 * @param	Closure	$callback	(optionnel) Fonction anonyme qui sera exécutée.
	 * @return	mixed	Les données retournées par la fonction anonyme, ou null.
	 */
	public function get($key, Closure $callback=null) {
		FineLog::log('finebase', FineLog::DEBUG, "DummyDatasource get() call.");
		if (isset($callback))
			return ($callback());
		return (null);
	}
}

?>
