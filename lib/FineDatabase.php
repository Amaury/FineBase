<?php

require_once("finebase/FineLog.php");
require_once("finebase/ApplicationException.php");
require_once("finebase/DatabaseException.php");

/**
 * Objet de gestion de la connexion à la base de données.
 *
 * Cet objet peut s'utiliser de 2 manières :
 * - En instanciant un objet via la méthode factory(), qui retourne une instance autonome.
 * - En utilisant la méthode singleton() qui retourne une instance unique.
 * L'utilisation du singleton permet d'avoir un seul objet de gestion de la base de données
 * dans l'application. Dans ce cas, il faut penser à fournir le DSN (Data Source Name,
 * paramètre de connexion à la base de données) lors du premier appel à la méthode singleton().
 *
 * Un DSN peut prendre 2 formes : soit un hash contenant les différents paramètres, soit une chaîne de caractères.
 * Les hashs doivent contenir 5 clés/valeurs :
 * - type : "mysqli" pour les bases de données MySQL
 * - host : nom de la machine hébergeant la base de données ("localhost" par exemple)
 * - login : nom de l'utilisateur de la base de données
 * - password : mot de passe de l'utilisateur
 * - base : nom de la base de données
 * Plus un clé optionnelle : "localRead", à mettre à true si la base de données est répliquée en local.
 *
 * Pour les DSN sous forme de chaînes de caractères, ils s'écrivent :
 * <pre>type://login:password@host/base</pre>
 * exemple : mysqli://user:pwd@localhost/database
 * Un paramètre supplémentaire peut être ajouté pour indiquer que la base est répliquée en local pour la
 * lecture. Cela se fait en ajoutant ":1" à la fin du DNS.
 *
 * Exemple simple d'utilisation :
 * <code>
 * try {
 *	 // création de l'objet, connexion à la base de données
 *	 $db = FineDatabase::factory("mysqli://user:pwd@localhost/database");
 *	 // exécution d'une requête simple
 *	 $db->exec("DELETE FROM tToto");
 *       // exécution d'une requête avec récupération d'une seule ligne de données
 *       $result = $db->queryOne("SELECT COUNT(*) AS nbr FROM tTiti");
 *       print($result['nbr']);
 *	 // exécution d'une requête avec récupération de plusieurs lignes de données
 *	 $result = $db->queryAll("SELECT id, name FROM tTiti");
 *	 // affichage des résultats
 *       foreach ($result as $titi)
 *               print($titi['id'] . " -> " . $titi['name'] . "\n");
 * } catch (DatabaseException $e) {
 *	 print("Erreur base de données: " . $e->getMessage());
 * }
 * </code>
 *
 * Exemple transactionnel :
 * <code>
 * // creation de l'objet et connexion à la base de données
 * try { $db = FineDatabase::factory("mysqli://user:pwd@localhost/database"); }
 * catch (DatabaseException $e) { }
 * // requêtes transactionnelles
 * try {
 *	 // début de transaction
 *	 $db->startTransaction();
 *	 // requête d'insertion
 *	 $db->exec("INSERT INTO tToto (name) VALUES ('pouet')");
 *	 // requête malformée, devrait lever une exception
 *	 $db->exec("INSERT trululu");
 *	 // commit de la transaction si tout s'est bien passé
 *	 $db->commit();
 * } catch (DatabaseException $e) {
 *	 // rollback de la transaction
 *	 $db->rollback();
 * }
 * </code>
 *
 * @author	Amaury Bouchard <amaury.bouchard@finemedia.fr>
 * @copyright	© 2010, FineMedia
 * @version	$Id: FineDatabase.php 581 2011-07-01 16:35:19Z abouchard $
 * @package	FineBase
 */
class FineDatabase {
	/** Instance unique de l'objet. */
	static private $_instance = null;
	/** DSN utilisé pour créer le singleton. */
	static private $_singletonDsn = null;
	/** Objet de connexion à la base de données. */
	private $_db = null;
	/** Paramètres de connexion à la base de données. */
	private $_params = null;

	/* ************************ CONSTRUCTION ********************** */
	/**
	 * Usine de fabrication de l'objet.
	 * Crée une instance d'une version concrête de l'objet, en fonction des paramètres fournis.
	 * @param	string|array	$dsn	Chaîne ou hash contenant les paramètres de connexion: type ('mysqli' ou 'sqlite'), host
	 *					(nom du serveur pour MySQLi), login (MySQLi), password (MySQLi), base (nom de la base de
	 *					données pour MySQLi), path (chemin vers le fichier pour SQLite), mode (mode d'ouverture
	 *					du fichier pour SQLite).
	 * @return	FineDatabase	L'objet FineDatabase créé.
	 * @throws	DatabaseException
	 */
	static public function factory($dsn) {
		FineLog::log("finebase", FineLog::DEBUG, "Database object creation with DSN: '$dsn'.");
		// extraction des paramètres de connexion
		$localRead = false;
		if (is_string($dsn) && preg_match("/^([^:]+):\/\/([^:@]+):?([^@]+)?@([^\/:]+):?(\d+)?\/(.*)$/", $dsn, $matches)) {
			$type = $matches[1];
			$login = $matches[2];
			$password = $matches[3];
			$host = $matches[4];
			$port = $matches[5];
			$base = $matches[6];
			if (strpos($base, ':') !== false) {
				$res = explode(':', $base);
				$base = $res[0];
				if (is_numeric($res[1]) && $res[1] == 1)
					$localRead = true;
			}
		} else if (is_array($dsn)) {
			$type = $dsn['type'];
			$host = $dsn['host'];
			$port = $dsn['port'];
			$login = $dsn['login'];
			$password = $dsn['password'];
			$base = $dsn['base'];
			$localRead = (isset($dsn['localRead']) && $dsn['localRead'] === true) ? true : false;
		} else
			throw new DatabaseException("No DSN provided.", DatabaseException::FUNDAMENTAL);
		// création de l'instance
		$instance = new FineDatabase($host, $login, $password, $base, (int)$port, $localRead);
		return ($instance);
	}
	/**
	 * Retourne une instance unique de l'objet.
	 * @param	string|array	$dsn	(optionnel) Paramètres de connexion à la base de données. Voir le constructeur pour plus d'infos.
	 * @return	FineDatabase	L'instance globale unique de l'objet.
	 * @throw	ApplicationException
	 */
	static public function singleton($dsn=null) {
		FineLog::log("finebase", FineLog::DEBUG, "Singleton database object creation with DSN: '$dsn'.");
		if (!isset(self::$_instance))
			self::$_instance = FineDatabase::factory($dsn);
		if (isset(self::$_singletonDsn) && $dsn != self::$_singletonDsn)
			throw new ApplicationException("Trying to get an existing singleton with a different DSN.", ApplicationException::API);
		return (self::$_instance);
	}
	/**
	 * Constructeur. Ouvre une connexion à la base de données.
	 * @param	string	$host		Nom de la machine sur laquelle se connecter.
	 * @param	string	$login		Nom de l'utilisateur de la base de données.
	 * @param	string	$password	Mot de passe de l'utilisateur.
	 * @param	string	$base		Nom de la base sur laquelle se connecter.
	 * @param	int	$port		Numéro de port sur lequel se connecter.
	 * @param	bool	$localRead	(optionnel) Indique si la base est répliquée en local pour lecture. False par défaut.
	 */
	public function __construct($host, $login, $password, $base, $port, $localRead=false) {
		FineLog::log("finebase", FineLog::DEBUG, "MySQL object creation. base: '$base'.");
		$this->_params = array(
			'host'		=> $host,
			'login'		=> $login,
			'password'	=> $password,
			'base'		=> $base,
			'port'		=> $port,
			'localRead'	=> $localRead
		);
	}
	/** Destructeur. Ferme la connexion. */
	public function __destruct() {
		//if (isset($this->_db))
		//	$this->_db->close();
	}

	/* ***************************** CONNEXION / DECONNEXION **************** */
	/** Ouverture de connexion. */
	private function _connect() {
		if ($this->_db)
			return;
		$this->_db = new mysqli($this->_params['host'], $this->_params['login'], $this->_params['password'], $this->_params['base'], (int)$this->_params['port']);
		if (mysqli_connect_errno())
			throw new DatabaseException("MySQLi database connexion error: " . mysqli_connect_error(), DatabaseException::FUNDAMENTAL);
	}
	/** Fermeture de connexion. */
	public function close() {
		if (isset($this->_db))
			$this->_db->close();
	}
	/**
	 * Définit l'encodage de caractères à utiliser.
	 * @param	string	$charset	(optionnel) L'encodage à utiliser. "utf8" par défaut.
	 */
	public function charset($charset="utf8") {
		$this->_connect();
		$this->_db->set_charset("utf8");
	}

	/* ***************************** TRANSACTIONS ************************ */
	/**
	 * Démarre une transaction SQL.
	 * @throws      DatabaseException
	 */
	public function startTransaction() {
		FineLog::log("finebase", FineLog::DEBUG, "Beginning transaction.");
		$this->_connect();
		if ($this->_db->query("START TRANSACTION") === false)
			throw new DatabaseException("Unable to open a new transaction.", DatabaseException::QUERY);
	}
	/**
	 * Effectue le commit d'une transaction SQL.
	 * @throws      DatabaseException
	 */
	public function commit() {
		FineLog::log("finebase", FineLog::DEBUG, "Commiting transaction.");
		$this->_connect();
		if ($this->_db->commit() === false)
			throw new DatabaseException("Error during transaction commit.", DatabaseException::QUERY);
	}
	/**
	 * Effectue le rollback d'une transaction SQL.
	 * @throws      DatabaseException
	 */
	public function rollback() {
		FineLog::log("finebase", FineLog::DEBUG, "Rollbacking transaction.");
		$this->_connect();
		if ($this->_db->rollback() === false)
			throw new DatabaseException("Error during transaction rollback.", DatabaseException::QUERY);
	}

	/* ********************** REQUETES *********************** */
	/**
	 * Retourne la dernière erreur SQL.
	 * @return	string	La dernière erreur SQL.
	 */
	public function getError() {
		$this->_connect();
		return ($this->_db->error);
	}
	/**
	 * Vérifie que la connexion à la base de données est toujours active.
	 * @return	bool	True si la connexion est active.
	 */
	public function ping() {
		$this->_connect();
		return ($this->_db->ping());
	}
	/**
	 * Echappe les chaînes de caractères.
	 * @param       string  $str    La chaîne à échapper.
	 * @return      string  La chaîne après échappement.
	 */
	public function quote($str) {
		$this->_connect();
		return ($this->_db->real_escape_string($str));
	}
	/**
	 * Exécute une requête SQL sans récupération des données.
	 * @param       string  $sql    La requête à exécuter.
	 * @throws      DatabaseException
	 */
	public function exec($sql) {
		$this->_query($sql);
	}
	/**
	 * Exécute une requête SQL avec récupération d'une seule ligne de données.
	 * @param	string	$sql	La requête à exécuter.
	 * @return	array	Un hash contenant la ligne de données. Ce n'est pas un set de données.
	 * @throws	DatabaseException
	 */
	public function queryOne($sql) {
		$result = $this->_query($sql);
		$line = $result->fetch_assoc();
		$result->free_result();
		return ($line);
	}
	/**
	 * Exécute une requête SQL avec récupération des données dans un tableau.
	 * @param	string	$sql	La requête à exécuter.
	 * @return	array	La liste de données.
	 * @throws	DatabaseException
	 */
	public function queryAll($sql) {
		$result = $this->_query($sql);
		$list = array();
		while (($element = $result->fetch_assoc()))
			$list[] = $element;
		$result->free_result();
		return ($list);
	}
	/**
	 * Retourne la clé primaire du dernier élément créé.
	 * @return      int     Le nombre entier de la clé primaire.
	 * @throws	DatabaseException
	 */
	public function lastInsertId() {
		$result = $this->_query("SELECT LAST_INSERT_ID() AS id");
		$res = $result->fetch_assoc();
		$result->free_result();
		return ($res['id']);
	}

	/* ********************** METHODES PRIVEES **************** */
	/**
	 * Exécute une requête et retourne son résultat.
	 * @param	string	$sql	La requête à exécuter.
	 * @return	mixed	La ressource du résultat.
	 * @throws	DatabaseException
	 */
	private function _query($sql) {
		FineLog::log("finebase", FineLog::DEBUG, "SQL query: $sql");
		$this->_connect();
		if (($result = $this->_db->query($sql)) === false)
			throw new DatabaseException("Request failed: " . $this->_db->error, DatabaseException::QUERY);
		return ($result);
	}
}

?>
