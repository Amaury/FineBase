<?php

require_once('finebase/FineLog.php');
require_once('finebase/FineApplicationException.php');

/**
 * Objet de gestion de la connexion à une source de données.
 *
 * @author	Amaury Bouchard <amaury.bouchard@finemedia.fr>
 * @copyright	© 2012, FineMedia
 * @version	$Id$
 * @package	FineBase
 */
abstract class FineDatasource {
	/* ************************ CONSTRUCTION ********************** */
	/**
	 * Usine de fabrication de l'objet.
	 * Crée une instance d'une version concrête de l'objet, en fonction des paramètres fournis.
	 * @param	string	$dsn	Chaîne contenant les paramètres de connexion.
	 * @return	FineDatasource	L'objet FineDatasource créé.
	 * @throws	Exception	Si le DSN fourni est incorrect.
	 */
	static public function factory($dsn) {
		FineLog::log('finebase', FineLog::DEBUG, "Datasource object creation with DSN: '$dsn'.");
		if (substr($dsn, 0, 9) === 'mysqli://') {
			require_once('finebase/FineDatabase.php');
			return (FineDatabase::factory($dsn));
		} else if (substr($dsn, 0, 11) === 'memcache://') {
			require_once('finebase/FineCache.php');
			return (FineCache::factory($dsn));
		} else if (substr($dsn, 0, 8) === 'redis://' || substr($dsn, 0, 13) === 'redis-sock://') {
			require_once('finebase/FineNDB.php');
			return (FineNDB::factory($dsn));
		} else if (substr($dsn, 0, 8) == 'dummy://') {
			require_once('finebase/FineDummyDatasource.php');
			return (FineDummyDatasource::factory());
		} else if (substr($dsn, 0, 6) == 'env://') {
			$dsn = getenv(substr($dsn, 6));
			return (self::factory($dsn));
		} else
			throw new Exception("No valid DSN provided '$dsn'.");
	}
	/**
	 * Indique si le cache est actif ou non.
	 * @return	bool	True si le cache est actif.
	 */
	public function isEnabled() {
		return (true);
	}
}

?>
