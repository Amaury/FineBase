<?php

require_once("finebase/FineLog.php");
require_once("finebase/IOException.php");

/**
 * Objet servant au stockage neutre de données, répondant au design pattern Registry.
 *
 * Cet objet peut être utilisé tel quel. Mais il est recommandé de l'utiliser comme
 * classe parente à des objets spécifiques, qui serviront à contenir des informations
 * plus spécialisées.
 * Exemple d'utilisation :
 * <code>
 * $registry = new FineRegistry();
 * $registry->readXml("/path/to/XML/file");
 * print($registry->get("pouet"));  // ces 2 lignes sont
 * print($registry->pouet);         // équivalentes
 * </code>
 * Mais on utilisera préférablement un objet dérivé de FineRegistry, qui peut par exemple
 * répondre au pattern Singleton, et qui contiendra des données spécifiques :
 * <code>
 * class Config extends FineRegistry {
 *     private static $_instance = null;
 *     public function __construct() {
 *         $this->readIni("/path/to/configuration/file");
 *     }
 *     public function singleton() {
 *         if (!isset(self::$_instance))
 *             self::$_instance = new Config();
 *         return (self::$_instance);
 *     }
 * }
 * $config = Config::singleton();
 * print("Data Source Name: " . $config->dsn);
 * </code>
 *
 * @author	Amaury Bouchard <amaury.bouchard@finemedia.fr>
 * @copyright	© 2007, FineMedia
 * @package	FineBase
 * @version	$Id: FineRegistry.php 569 2011-04-15 17:06:33Z abouchard $
 */
class FineRegistry {
	/** Hash ou objet contenant les données stockées par l'objet. */
	protected $_data = null;

	/* ************************ CONSTRUCTION ********************** */
	/** Constructeur. */
	public function __construct() {
		FineLog::log("finebase", FineLog::DEBUG, "Registry object creation.");
	}
	/** Destructeur. */
	public function __destruct() {
		if (!is_null($this->_data))
			unset($this->_data);
	}

	/* ****************** MANIPULATION DES DONNEES **************** */
	/**
	 * Retourne les données stockées par l'objet.
	 * @param	string	key	Nom de la donnée à récupérer.
	 * @return	mixed	La valeur de la donnée recherchée.
	 */
	public function __get($key) {
		if (is_object($this->_data))
			return ($this->_data->$key);
		if (is_array($this->_data))
			return ($this->_data[$key]);
		return (null);
	}
	/**
	 * Retourne les données stockées par l'objet.
	 * @param	string	key	Nom de la donnée à récupérer.
	 * @return	mixed	La valeur de la donnée recherchée.
	 */
	public function get($key) {
		if (is_object($this->_data))
			return ($this->_data->$key);
		if (is_array($this->_data))
			return ($this->_data[$key]);
		return (null);
	}
	/**
	 * Lit un fichier INI et stocke ses informations.
	 * @param	string	path	Chemin vers le fichier INI.
	 * @param	string	key	(optionnel) Nom sous lequel le contenu du fichier INI sera disponible.
	 *				Si cette clé n'est pas fournie, l'ensemble des données du
	 *				fichier INI remplacera les données actuellement stockées.
	 */
	public function readIni($path, $key=null) {
		FineLog::log("finebase", FineLog::DEBUG, "Reading INI file '$path'.");
		$result = parse_ini_file($path, true);
		if (is_null($key))
			$this->_data = $result;
		else
			$this->_data[$key] = $result;
	}
	/**
	 * Lit un fichier JSON et stocke ses informations.
	 * @param	string	path	Chemin vers le fichier JSON.
	 * @param	string	key	(optionnel) Nom sous lequel le contenu du fichier JSON sera disponible.
	 *				Si cette clé n'est pas fournie, l'ensemble des données du
	 *				fichier JSON remplacera les données actuellement stockées.
	 */
	public function readJson($path, $key=null) {
		FineLog::log("finebase", FineLog::DEBUG, "Reading JSON file '$path'.");
		$result = json_decode(file_get_contents($path));
		if (is_null($key))
			$this->_data = $result;
		else
			$this->_data[$key] = $result;
	}
	/**
	 * Lit un fichier XML et stocke ses informations.
	 * @param	string	path	Chemin vers le fichier XML.
	 * @param	string	key	(optionnel) Nom sous lequel le contenu du fichier JSON sera disponible.
	 *				Si cette clé n'est pas fournie, l'ensemble des données du
	 *				fichier JSON remplacera les données actuellement stockées.
	 * @throws	IOException
	 */
	public function readXml($path, $key=null) {
		FineLog::log("finebase", FineLog::DEBUG, "Reading XML file '$path'.");
		if (($xml = simplexml_load_file($path)) === false)
			throw new IOException("Impossible de lire le fichier XML '$path'.", IOException::BAD_FORMAT);
		if (is_null($key))
			$this->_data = $xml;
		else
			$this->_data[$key] = $xml;
	}
}

?>
