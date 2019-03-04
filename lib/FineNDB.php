<?php

require_once('finebase/FineDatasource.php');

/**
 * Objet de gestion de la connexion à une base de données non relationnelle.
 *
 * Cet objet s'instancie en utilisant la méthode statique factory.
 *
 * Les paramétrages de connexion sont fournis sous forme d'une chaîne de caractères :
 * Pour les DSN sous forme de chaînes de caractères, ils s'écrivent :
 * <pre>type://host[:port][/base]</pre>
 * exemples :
 *  redis://localhost/1
 *  redis://db.finemedia.fr:6379/2
 *  redis-sock:///var/run/redis/redis-server.sock
 *  redis-sock:///var/run/redis/redis-server.sock#2
 *
 * Exemple simple d'utilisation :
 * <code>
 * try {
 *	 // création de l'objet, connexion à la base de données
 *	 $ndb = FineNDB::factory("redis://localhost");
 *	 // insertion d'une parie clé/valeur
 *	 $ndb->set('key', 'value');
 *	 // insertion de plusieurs valeurs
 *	 $ndb->set(array(
 *		'key1' => 'val1',
 *		'key2' => 'val2'
 *	 ));
 *	 // récupération d'une valeur
 *	 $result = $ndb->get('key');
 *	 print($result);
 *	 // suppression d'une entrée
 *	 $ndb->remove('key');
 *	 // récupération de plusieurs valeurs
 *	 $result = $ndb->get(array('key1', 'key2', 'key3'));
 *	 // affichage des résultats
 *	 foreach ($result as $key => $val)
 *		print("$key -> $val\n");
 *	 // recherche de plusieurs valeurs
 *	 $result = $ndb->search('ugc:*');
 * } catch (Exception $e) {
 *	 print("Erreur base de données: " . $e->getMessage());
 * }
 * </code>
 *
 * @author	Amaury Bouchard <amaury.bouchard@finemedia.fr>
 * @copyright	© 2012, FineMedia
 * @version	$Id$
 * @package	FineBase
 */
class FineNDB extends FineDatasource {
	/** Port de connexion Redis par défaut. */
	const DEFAULT_REDIS_PORT = 6379;
	/** Base de donnée Redis par défaut. */
	const DEFAULT_REDIS_BASE = 0;
	/** Objet de connexion à la base de données. */
	private $_ndb = null;
	/** Paramètres de connexion à la base de données. */
	private $_params = null;

	/* ************************ CONSTRUCTION ********************** */
	/**
	 * Usine de fabrication de l'objet.
	 * Crée une instance d'une version concrête de l'objet, en fonction des paramètres fournis.
	 * @param	string	$dsn	DSN de connexion.
	 * @return	FineNDB	L'objet FineNDB créé.
	 * @throws	Exception	Si le DSN est incorrect.
	 */
	static public function factory($dsn) {
		FineLog::log('finebase', FineLog::DEBUG, "FineNDB object creation with DSN: '$dsn'.");
		// extraction des paramètres de connexion
		if (preg_match("/^redis-sock:\/\/([^#]+)#?(.*)$/", $dsn, $matches)) {
			$type = 'redis';
			$host = $matches[1];
			$base = $matches[2];
			$port = null;
		} else if (preg_match("/^([^:]+):\/\/([^\/:]+):?(\d+)?\/?(.*)$/", $dsn, $matches)) {
			$type = $matches[1];
			$host = $matches[2];
			$port = (!empty($matches[3]) && ctype_digit($matches[3])) ? $matches[3] : self::DEFAULT_REDIS_PORT;
			$base = $matches[4];
		}
		if ($type != 'redis')
			throw new Exception("DSN '$dsn' is invalid.");
		$base = !empty($base) ? $base : self::DEFAULT_REDIS_BASE;
		// création de l'instance
		$instance = new FineNDB($host, $base, $port);
		return ($instance);
	}
	/**
	 * Constructeur. Ouvre une connexion à la base de données.
	 * @param	string	$host		Nom de la machine sur laquelle se connecter, ou chemin vers la socket Unix.
	 * @param	string	$base		Nom de la base sur laquelle se connecter.
	 * @param	int	$port		(optionnel) Numéro de port sur lequel se connecter.
	 */
	private function __construct($host, $base, $port=null) {
		FineLog::log('finebase', FineLog::DEBUG, "FineDB object creation.");
		$this->_params = array(
			'host'		=> $host,
			'base'		=> $base,
			'port'		=> $port
		);
	}
	/** Destructeur. Ferme la connexion. */
	public function __destruct() {
		if (isset($this->_ndb))
			$this->_ndb->close();
	}

	/* ***************************** CONNEXION / DECONNEXION ************************ */
	/** Ouverture de connexion. */
	private function _connect() {
		if ($this->_ndb)
			return;
		try {
			$this->_ndb = new \Redis();
			$this->_ndb->connect($this->_params['host'], (isset($this->_params['port']) ? $this->_params['port'] : null));
			if ($this->_params['base'] != self::DEFAULT_REDIS_BASE && !$this->_ndb->select($this->_params['base']))
				throw new Exception("Unable to select dabase '" . $this->_params['base'] . "'.");
		} catch (Exception $e) {
			throw new Exception('Redis database connexion error: ' . $e->getMessage());
		}
	}
	/** Fermeture de connexion. */
	public function close() {
		if (isset($this->_ndb))
			$this->_ndb->close();
	}

	/* ********************** REQUETES *********************** */
	/**
	 * Insère une ou plusieurs paires clé/valeur.
	 * @param	string|array	$key		Clé, ou tableau associatif de paires clé/valeur.
	 * @param	string		$value		(optionnel) Valeur associée à la clé.
	 * @param	bool		$createOnly	(optionnel) Indique s'il faut ajouter la paire que si elle n'existait pas. Faux par défaut.
	 * @param	int		$timeout	(optionnel) Indique le nombre de secondes avant l'expiration de la clé. 0 par défaut, pour ne pas avoir d'expiration.
	 * @return	FineNDB	L'objet courant.
	 * @throws      Exception
	 */
	public function set($key, $value=null, $createOnly=false, $timeout=0) {
		$this->_connect();
		if (!is_array($key)) {
			$value = json_encode($value);
			if ($createOnly)
				$this->_ndb->setnx($key, $value);
			else if (isset($timeout) && is_numeric($timeout) && $timeout > 0)
				$this->_ndb->setex($key, $timeout, $value);
			else
				$this->_ndb->set($key, $value);
		} else {
			$key = array_map('json_encode', $key);
			if ($createOnly)
				$this->_ndb->msetnx($key);
			else
				$this->_ndb->mset($key);
		}
		return ($this);
	}
	/**
	 * Récupère une ou plusieurs paires clé/valeur.
	 * @param	string|array	$key		Clé, ou liste de clés.
	 * @param	function	$callback	(optionnel) Fonction appelée si la donnée n'a pas été trouvée, uniquement si la clé était unique.
         *						Les données retournées par cette fonction seront ajoutées, et retournées par la méthode.
	 * @return	mixed	La valeur associée à la clé, ou un tableau associatif listant les différentes paires clé/valeur.
	 *			Si une valeur n'existe pas, elle prend la valeur null.
	 */
	public function get($key, Closure $callback=null) {
		$this->_connect();
		if (is_array($key)) {
			$values = $this->_ndb->mget($key);
			$result = array_combine($key, $values);
			foreach ($result as $k => &$v)
				$v = ($v === false) ? null : json_decode($v, true);
			return ($result);
		}
		$value = $this->_ndb->get($key);
		if ($value === false && isset($callback)) {
			$value = $callback();
			$this->set($key, $value);
			return ($value);
		}
		return (($value === false) ? null : json_decode($value, true));
	}
	/**
	 * Retourne la liste des clés qui matchent un pattern.
	 * @param	string	$pattern	Le pattern à matcher. Utiliser l'étoile comme wildcard.
	 * @param	bool	$getValues	(optionnel) Indique s'il faut récupérer les valeurs des clés. Faux par défaut.
	 * @return	array	Liste de clés, ou tableau associatif de paires clé/valeur.
	 */
	public function search($pattern, $getValues=false) {
		$this->_connect();
		$keys = $this->_ndb->keys($pattern);
		if (!$getValues)
			return ($keys);
		$values = $this->get($keys);
		return ($values);
	}
	/**
	 * Efface une ou plusieurs paires clé/valeur.
	 * @param	string|array	$key	Clé, ou liste de clés.
	 * @return	FineNDB	L'objet courant.
	 */
	public function remove($key) {
		$this->_connect();
		$this->_ndb->delete($key);
	}
}

?>
