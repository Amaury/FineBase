<?php

require_once('finebase/FineDatasource.php');

/**
 * Objet de gestion des données en cache.
 *
 * Cet objet permet de stocker des données en cache mémoire, en utilisant un serveur Memcache.
 *
 * <b>Utilisation</b>
 *
 * <code>
 * // instanciation
 * $cache = FineCache::factory('memcache://localhost');
 * // ajout d'une variable en cache
 * $cache->set("nom de la variable", $data);
 * // récupération d'une variable de cache
 * $data = $cache->get("nom de la variable");
 * // effacement d'une variable
 * $cache->set("nom de la variable", null);
 * </code>
 *
 * Socket Unix :
 * <tt>memcache:///var/run/memcached.sock:0
 *
 * @author	Amaury Bouchard <amaury.bouchard@finemedia.fr>
 * @copyright	© 2009-2012, FineMedia
 * @package	FineBase
 * @version	$Id$
 */
class FineCache extends FineDatasource {
	/** Constante : Préfixe des variables de cache contenant le "sel" de préfixe. */
	const PREFIX_SALT_PREFIX = '|_cache_salt';
	/** Constante : Numéro de port Memcached par défaut. */
	const DEFAULT_MEMCACHE_PORT = 11211;
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
	 * Crée un objet FineCache.
	 * @param	string	$dsn	Chaîne de connexion aux serveurs de cache.
	 * @return	FineCache	L'objet FineCache créé.
	 */
	static public function factory($dsn) {
		FineLog::log('finebase', FineLog::DEBUG, "FineCache object creation.");
		return (new FineCache($dsn));
	}
	/**
	 * Constructeur. Effectue une connexion au serveur memcache.
	 * @param	string	$dsn	Chaîne de connexion aux serveurs de cache.
	 * @throws	Exception	Si le DSN fourni est incorrect.
	 */
	private function __construct($dsn) {
		if (substr($dsn, 0, 11) !== 'memcache://')
			throw new Exception("Invalid cache DSN '$dsn'.");
		$dsn = substr($dsn, 11);
		if (!extension_loaded('memcached') || empty($dsn))
			return;
		$memcache = new MemCached();
		$memcache->setOption(Memcached::OPT_COMPRESSION, true);
		$servers = explode(';', $dsn);
		foreach ($servers as &$server) {
			if (empty($server))
				continue;
			if (strpos($server, ':') === false)
				$server = array($server, self::DEFAULT_MEMCACHE_PORT);
			else {
				list($host, $port) = explode(':', $server);
				$server = array(
					$host,
					($port ? $port : self::DEFAULT_MEMCACHE_PORT)
				);
			}
		}
		if (count($servers) == 1) {
			if ($memcache->addServer($servers[0][0], $servers[0][1])) {
				$this->_memcache = $memcache;
				$this->_enabled = true;
			}
		} else if (count($servers) > 1) {
			if ($memcache->addServers($servers)) {
				$this->_memcache = $memcache;
				$this->_enabled = true;
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
	 * Retourne le préfixe courant
	 * @return	string	Nom du préfixe courant;
	 */
	public function getPrefix() {
		if (empty($this->_prefix))
			return ('');
		$res = trim($this->_prefix, "|");
		return ($res);
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
	 * @param	Closure		$callback	(optionnel) Fonction appelée si la donnée n'a pas été trouvée en cache.
	 *						Les données retournées par cette fonction seront ajoutées en cache, et retournées
	 *						par la méthode.
	 * @param	int		$expire		(optionnel) Durée d'expiration de la donnée, en secondes. S'il n'est pas présent ou égale à zéro, la durée
	 *						d'expiration vaudra la valeur par défaut (24 heures). S'il vaut -1 ou une valeur supérieure à 2592000 secondes,
	 *						la durée d'expiration sera de 30 jours. Cette variable n'est utilisée seulement si la variable $callback est
	 * 						renseignée.
	 * @return	mixed	La donnée, le retour de la callback, ou NULL si la donnée n'était pas présente dans le cache.
	 */
	public function get($key, Closure $callback=null, $expire=0) {
		$origPrefix = $this->_prefix;
		$origKey = $key;
		$key = $this->_getSaltedPrefix() . $key;
		$data = null;
		if ($this->_enabled && $this->_memcache) {
			$data = $this->_memcache->get($key);
			if ($data === false && $this->_memcache->getResultCode() != Memcached::RES_SUCCESS)
				$data = null;
		}
		if (is_null($data) && isset($callback)) {
			$data = $callback();
			$this->_prefix = $origPrefix;
			$this->set($origKey, $data, $expire);
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
		$salt = substr(hash('md5', time() . mt_rand()), 0, 8);
		if ($this->_enabled && $this->_memcache)
			$this->_memcache->set($saltKey, $salt, 0);
		return ($this);
	}
	/**
	 * Vérifie si une variable est définie en cache.
	 * @param	string	$key	Clé d'indexation de la donnée.
	 * @return	bool	True si la donnée existe, false sinon.
	 */
	public function isSet($key) {
		if (empty($key) || !$this->_enabled || !$this->_memcache)
			return (false);
		$origPrefix = $this->_prefix;
		$origKey = $key;
		$key = $this->_getSaltedPrevix() . $key;
		if ($this->_memcache->get($key) === false && $this->_memcache->getResultCode() == Memcached::RES_NOTFOUND)
			return (false);
		return (true);
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
		if (!isset($salt) || !is_string($salt)) {
			$salt = substr(hash('md5', time() . mt_rand()), 0, 8);
			if ($this->_enabled && $this->_memcache)
				$this->_memcache->set($saltKey, $salt, 0);
		}
		return ("[$salt" . $this->_prefix);
	}
}

?>
