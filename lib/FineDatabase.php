<?php

require_once('finebase/FineDatasource.php');

/**
 * Objet de gestion de la connexion à la base de données.
 *
 * Cet objet s'instancie via la méthode factory(), qui retourne une instance autonome.
 *
 * Le paramétrage de la connexion à la base de données se fait sous la forme d'une chaîne de caractères :
 * <pre>type://login:password@host/base</pre>
 * exemple : <tt>mysqli://user:pwd@localhost/database</tt>
 *
 * Pour une connexion MySQL via socket Unix :
 * <pre>mysqli://login:password@localhost/database#chemin.sock</pre>
 * exemple : <tt>mysqli://user:pwd@localhost/database#/var/run/mysqld/mysqld.sock</tt>
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
 * } catch (Exception $e) {
 *	 print("Erreur base de données: " . $e->getMessage());
 * }
 * </code>
 *
 * Exemple transactionnel :
 * <code>
 * // creation de l'objet et connexion à la base de données
 * try { $db = FineDatabase::factory("mysqli://user:pwd@localhost/database"); }
 * catch (Exception $e) { }
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
 * } catch (Exception $e) {
 *	 // rollback de la transaction
 *	 $db->rollback();
 * }
 * </code>
 *
 * @author	Amaury Bouchard <amaury.bouchard@finemedia.fr>
 * @copyright	© 2007-2012, FineMedia
 * @version	$Id: FineDatabase.php 655 2013-09-12 08:39:20Z abouchard $
 * @package	FineBase
 */
class FineDatabase extends FineDatasource {
	/** Objet de connexion à la base de données. */
	private $_db = null;
	/** Paramètres de connexion à la base de données. */
	private $_params = null;
	/** Indique qu'une requête synchrone a été exécutée. */
	private $_sync = false;
	/** Liste des requêtes asynchrones en attente. */
	private $_asyncRequests = null;

	/* ************************ CONSTRUCTION ********************** */
	/**
	 * Usine de fabrication de l'objet.
	 * Crée une instance d'une version concrête de l'objet, en fonction des paramètres fournis.
	 * @param	string	$dsn	DSN de connexion à la base de données.
	 * @return	FineDatabase	L'objet FineDatabase créé.
	 * @throws	Exception
	 */
	static public function factory($dsn) {
		FineLog::log('finebase', FineLog::DEBUG, "Database object creation with DSN: '$dsn'.");
		// extraction des paramètres de connexion
		if (preg_match("/^([^:]+):\/\/([^:@]+):?([^@]+)?@([^\/:]+):?(\d+)?\/([^#]*)#?(.*)$/", $dsn, $matches)) {
			$type = $matches[1];
			$login = $matches[2];
			$password = $matches[3];
			$host = $matches[4];
			$port = $matches[5];
			$base = $matches[6];
			$sock = $matches[7];
		}
		if (!isset($type) || $type != 'mysqli')
			throw new Exception("No DSN provided.");
		// création de l'instance
		$instance = new FineDatabase($host, $login, $password, $base, (int)$port, $sock);
		return ($instance);
	}
	/**
	 * Constructeur. Ouvre une connexion à la base de données.
	 * @param	string	$host		Nom de la machine sur laquelle se connecter.
	 * @param	string	$login		Nom de l'utilisateur de la base de données.
	 * @param	string	$password	Mot de passe de l'utilisateur.
	 * @param	string	$base		Nom de la base sur laquelle se connecter.
	 * @param	int	$port		Numéro de port sur lequel se connecter.
	 * @param	string	$sock		(optionnel) Chemin vers la socket Unix locale (si le paramètre $host vaut 'localhost').
	 */
	private function __construct($host, $login, $password, $base, $port, $sock=null) {
		FineLog::log('finebase', FineLog::DEBUG, "MySQL object creation. base: '$base'.");
		$this->_params = array(
			'host'		=> $host,
			'login'		=> $login,
			'password'	=> $password,
			'base'		=> $base,
			'port'		=> $port,
			'sock'		=> $sock,
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
		$this->_db = new mysqli($this->_params['host'], $this->_params['login'], $this->_params['password'],
		                        $this->_params['base'], (int)$this->_params['port'], $this->_params['sock']);
		$this->charset();
		if (mysqli_connect_errno())
			throw new Exception("MySQLi database connexion error: " . mysqli_connect_error());
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
	public function charset($charset='utf8') {
		$this->_connect();
		$this->_db->set_charset($charset);
	}

	/* ***************************** TRANSACTIONS ************************ */
	/**
	 * Démarre une transaction SQL.
	 * @throws      Exception
	 */
	public function startTransaction() {
		FineLog::log('finebase', FineLog::DEBUG, "Beginning transaction.");
		$this->_connect();
		if ($this->_db->query('START TRANSACTION') === false)
			throw new Exception("Unable to open a new transaction.");
	}
	/**
	 * Effectue le commit d'une transaction SQL.
	 * @throws      Exception
	 */
	public function commit() {
		FineLog::log('finebase', FineLog::DEBUG, "Committing transaction.");
		$this->_connect();
		if ($this->_db->commit() === false)
			throw new Exception("Error during transaction commit.");
	}
	/**
	 * Effectue le rollback d'une transaction SQL.
	 * @throws      Exception
	 */
	public function rollback() {
		FineLog::log('finebase', FineLog::DEBUG, "Rollbacking transaction.");
		$this->_connect();
		if ($this->_db->rollback() === false)
			throw new Exception("Error during transaction rollback.");
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
	 * @param	bool	$async	(optionnel) Indique que la requête peut être mise en attente
	 *				jusqu'à ce qu'une requête synchrone soit exécutée. Les requêtes
	 *				asynchrones sont alors exécutées en premier, dans l'ordre de leurs
	 *				appels. False par défaut.
	 * @return	int	Le nombre de lignes modifiées. Dans le cas d'une requête asynchrone pour laquelle
	 *			la connexion au serveur n'aurait pas encore été ouverte, cette méthode retourne null.
	 * @throws      Exception
	 */
	public function exec($sql, $async=false) {
		$this->_query($sql, $async);
		if ($this->_db)
			return ($this->_db->affected_rows);
	}
	/**
	 * Exécute une requête SQL avec récupération d'une seule ligne de données.
	 * @param	string	$sql	La requête à exécuter.
	 * @return	array	Un hash contenant la ligne de données. Ce n'est pas un set de données.
	 * @throws	Exception
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
	 * @throws	Exception
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
	 * @throws	Exception
	 */
	public function lastInsertId() {
		$this->_connect();
		return ($this->_db->insert_id);
	}

	/* ********************** METHODES PRIVEES **************** */
	/**
	 * Exécute une requête et retourne son résultat.
	 * @param	string	$sql	La requête à exécuter.
	 * @param	bool	$async	(optionnel) Indique si la requête peut être asynchrone. False par défaut.
	 * @return	mixed	La ressource du résultat.
	 * @throws	Exception
	 */
	private function _query($sql, $async=false) {
		FineLog::log('finebase', FineLog::DEBUG, "SQL query: $sql");
		if ($async && !$this->_sync) {
			if (is_null($this->_asyncRequests))
				$this->_asyncRequests = array();
			$this->_asyncRequests[] = $sql;
			return;
		}
		$this->_connect();
		$this->_sync = true;
		if (!empty($this->_asyncRequests)) {
			foreach ($this->_asyncRequests as $request) {
				if ($this->_db->query($request) === false)
					throw new Exception("Async request failed: " . $this->_db->error);
			}
			$this->_asyncRequests = null;
		}
		if (($result = $this->_db->query($sql)) === false)
			throw new Exception("Request failed: " . $this->_db->error);
		return ($result);
	}
}

?>
