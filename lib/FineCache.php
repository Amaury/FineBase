<?php

require_once("finebase/FineLog.php");
require_once("finebase/ApplicationException.php");

/**
 * Objet de gestion des données en cache.
 *
 * Cet objet permet de stocker des données en cache mémoire, en utilisant un serveur Memcache.
 *
 * <b>Utilisation</b>
 *
 * <code>
 * // instanciation
 * $cache = FineCache::singleton();
 * // ajout d'une variable en cache
 * $cache->set("nom de la variable", $data);
 * // récupération d'une variable de cache
 * $data = $cache->get("nom de la variable");
 * // effacement d'une variable
 * $cache->set("nom de la variable", null);
 * </code>
 *
 * @author	Amaury Bouchard <amaury.bouchard@finemedia.fr>
 * @copyright	© 2009-2010, FineMedia
 * @package	FineBase
 * @version	$Id$
 */
class FineCache {
	/** Constante : Préfixe des variables de cache contenant le "sel" de préfixe. */
	const PREFIX_SALT_PREFIX = '|_cache_salt';
	/** Instance unique de l'objet. */
	static private $_instance = null;
	/** Indique si on doit utiliser le cache ou non. */
	private $_enabled = false;
	/** Objet de connexion au serveur memcache. */
	private $_memcache = null;
	/** Durée de mise en cache par défaut (24 heures). */
	private $_defaultExpiration = 86400;
	/** Préfixe de regroupement des variables de cache. */
	private $_prefix = '';

	/* ************************** CONSTRUCTION ******************** */
	/**
	 * Retourne l'instance unique.
	 * @param	string	$cacheServer	(optionnel) Chaîne de connexion aux serveurs de cache.
	 * @return	FineCache	L'instance.
	 */
	static public function singleton($cacheServer=null) {
		FineLog::log('finebase', FineLog::DEBUG, "Singleton FineCache object creation.");
		if (!isset(self::$_instance))
			self::$_instance = new FineCache($cacheServer);
		return (self::$_instance);
	}
	/**
	 * Constructeur. Effectue une connexion au serveur memcache.
	 * @param	string	$cacheServer	(optionnel) Chaîne de connexion aux serveurs de cache.
	 */
	public function __construct($cacheServer=null) {
		if (extension_loaded('memcache')) {
			$serverList = !empty($cacheServer) ? $cacheServer : getenv('FINE_MEMCACHE_SERVERS');
			if (!empty($serverList)) {
				$memcache = new MemCached();
				$memcache->setOption(Memcached::OPT_COMPRESSION, true);
				$servers = explode(';', $serverList);
				foreach ($servers as &$server) {
					if (empty($server))
						continue;
					list($host, $port) = explode(':', $server);
					$server = array(
						$host,
						($port ? $port : 11211)
					);
				}
				if ($memcache->addServers($servers)) {
					$this->_memcache = $memcache;
					$this->_enabled = true;
					return;
				}
			}
		}
	}

	/* ****************** GESTION DE L'UTILISATION DU CACHE ************ */
	/**
	 * Modifie la durée de mise en cache par défaut.
	 * @param	int	$expiration	Durée de mise en cache par défaut. 24 heures par défaut.
	 * @return	FineCache	L'instance de l'objet courant.
	 */
	public function setExpiration($expiration=86400) {
		$this->_defaultExpiration = $expiration;
		return ($this);
	}
	/**
	 * Désactive temporairement l'utilisation du cache.
	 * @return	FineCache	L'instance de l'objet courant.
	 */
	public function disable() {
		$this->_enabled = false;
		return ($this);
	}
	/**
	 * Réactive l'utilisation du cache (si la connexion au serveur Memcache fonctionne).
	 * @return	FineCache	L'instance de l'objet courant.
	 */
	public function enable() {
		$this->_enabled = true;
		return ($this);
	}
	/**
	 * Indique si le cache est actif ou non.
	 * @return	bool	True si le cache est actif.
	 */
	public function isEnabled() {
		return ($this->_enabled);
	}

	/* ************************ GESTION DES DONNÉES ****************** */
	/**
	 * Définit le préfixe servant à regrouper les données.
	 * @param	string	$prefix	(optionnel) Nom du préfixe. Laisser vide pour ne pas utiliser les préfixes.
	 * @return	FineCache	L'instance de l'objet courant.
	 */
	public function setPrefix($prefix='') {
		$this->_prefix = (empty($prefix) || !is_string($prefix)) ? '' : "|$prefix|";
		return ($this);
	}
	/**
	 * Ajoute une donnée dans le cache.
	 * @param	string	$key	Clé d'indexation de la donnée.
	 * @param	mixed	$data	(optionnel) Valeur de la donnée. La donnée est effacée si cette valeur vaut null ou si elle n'est pas fournie.
	 * @param	int	$expire	(optionnel) Durée d'expiration de la donnée, en secondes. S'il n'est pas présent ou égale à zéro, la durée
	 *				d'expiration vaudra la valeur par défaut (24 heures). S'il vaut -1 ou une valeur supérieure à 2592000 secondes,
	 *				la durée d'expiration sera de 30 jours.
	 * @return	FineCache	L'instance de l'objet courant.
	 */
	public function set($key, $data=null, $expire=0) {
		$key = $this->_getSaltedPrefix() . $key;
		if (is_null($data)) {
			// effacement de la donnée
			FineLog::log('finebase', FineLog::DEBUG, "Remove data from cache ($key).");
			if ($this->_enabled && $this->_memcache)
				$this->_memcache->delete($key, 0);
			return;
		}
		// ajout de la donnée en cache
		FineLog::log('finebase', FineLog::DEBUG, "Add data in cache ($key).");
		$expire = (!is_numeric($expire) || !$expire) ? $this->_defaultExpiration : $expire;
		$expire = ($expire == -1 || $expire > 2592000) ? 2592000 : $expire;
		if ($this->_enabled && $this->_memcache)
			$this->_memcache->set($key, $data, $expire);
		return ($this);
	}
	/**
	 * Récupération d'une donnée dans le cache.
	 * @param	string		$key		Clé d'indexation de la donnée.
	 * @param	function	$callback	(optionnel) Fonction appelée si la donnée n'a pas été trouvée en cache.
	 *						Les données retournées par cette fonction seront ajoutées en cache, et retournées
	 *						par la méthode.
	 * @return	mixed	La donnée, ou NULL si la donnée n'était pas présente dans le cache.
	 */
	public function get($key, $callback=null) {
		$key = $this->_getSaltedPrefix() . $key;
		$data = null;
		if ($this->_enabled && $this->_memcache) {
			$data = $this->_memcache->get($key);
			if ($data === false && $this->_memcache->getResultCode() != Memcached::RES_SUCCESS)
				$data = null;
		}
		if (is_null($data) && $callback instanceof Closure) {
			$data = $callback();
			$this->set($key, $data);
		}
		return ($data);
	}
	/**
	 * Efface toutes les variables de cache qui répondent à un préfixe donné.
	 * @param	string	$prefix	Nom du préfixe à effacer.
	 * @return	FineCache	L'instance de l'objet courant.
	 */
	public function clear($prefix) {
		if (empty($prefix))
			return;
		$saltKey = self::PREFIX_SALT_PREFIX . "|$prefix|";
		$salt = substr(hash('md5', time() . mt_rand()), 0, mt_rand(4, 8));
		if ($this->_enabled && $this->_memcache)
			$this->_memcache->set($saltKey, $salt, 0);
		return ($this);
	}

	/* ************************** METHODES PRIVEES ******************** */
	/**
	 * Retourne le préfixe en fonction du "sel" stocké en cache.
	 * @return	string	Le préfixe généré.
	 */
	private function _getSaltedPrefix() {
		// gestion du préfixe
		if (empty($this->_prefix))
			return ('');
		// récupération du sel
		$saltKey = self::PREFIX_SALT_PREFIX . $this->_prefix;
		if ($this->_enabled && $this->_memcache)
			$salt = $this->_memcache->get($saltKey);
		// génération du sel si besoin
		if (!is_string($salt)) {
			$salt = substr(hash('md5', time() . mt_rand()), 0, mt_rand(4, 8));
			if ($this->_enabled && $this->_memcache)
				$this->_memcache->set($saltKey, $salt, 0);
		}
		return ("[$salt" . $this->_prefix);
	}
}

?>
