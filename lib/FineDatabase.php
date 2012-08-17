<?php

require_once('finebase/FineDatasource.php');

/**
 * Objet de gestion de la connexion à la base de données.
 *
 * Cet objet s'instancie via la méthode factory(), qui retourne une instance autonome.
 *
 * Le paramétrage de la connexion à la base de données se fait sous la forme d'une chaîne de caractères :
 * <pre>type://login:password@host/base</pre>
 * exemple : mysqli://user:pwd@localhost/database
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
 * @copyright	© 2007-2012, FineMedia
 * @version	$Id: FineDatabase.php 629 2012-06-26 11:39:42Z abouchard $
 * @package	FineBase
 */
class FineDatabase extends FineDatasource {
	/** Objet de connexion à la base de données. */
	private $_db = null;
	/** Paramètres de connexion à la base de données. */
	private $_params = null;

	/* ************************ CONSTRUCTION ********************** */
	/**
	 * Usine de fabrication de l'objet.
	 * Crée une instance d'une version concrête de l'objet, en fonction des paramètres fournis.
	 * @param	string	$dsn	DSN de connexion à la base de données.
	 * @return	FineDatabase	L'objet FineDatabase créé.
	 * @throws	DatabaseException
	 */
	static public function factory($dsn) {
		FineLog::log('finebase', FineLog::DEBUG, "Database object creation with DSN: '$dsn'.");
		// extraction des paramètres de connexion
		if (preg_match("/^([^:]+):\/\/([^:@]+):?([^@]+)?@([^\/:]+):?(\d+)?\/(.*)$/", $dsn, $matches)) {
			$type = $matches[1];
			$login = $matches[2];
			$password = $matches[3];
			$host = $matches[4];
			$port = $matches[5];
			$base = $matches[6];
		}
		if ($type != 'mysqli')
			throw new DatabaseException("No DSN provided.", DatabaseException::FUNDAMENTAL);
		// création de l'instance
		$instance = new FineDatabase($host, $login, $password, $base, (int)$port);
		return ($instance);
	}
	/**
	 * Constructeur. Ouvre une connexion à la base de données.
	 * @param	string	$host		Nom de la machine sur laquelle se connecter.
	 * @param	string	$login		Nom de l'utilisateur de la base de données.
	 * @param	string	$password	Mot de passe de l'utilisateur.
	 * @param	string	$base		Nom de la base sur laquelle se connecter.
	 * @param	int	$port		Numéro de port sur lequel se connecter.
	 */
	private function __construct($host, $login, $password, $base, $port) {
		FineLog::log('finebase', FineLog::DEBUG, "MySQL object creation. base: '$base'.");
		$this->_params = array(
			'host'		=> $host,
			'login'		=> $login,
			'password'	=> $password,
			'base'		=> $base,
			'port'		=> $port
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
	public function charset($charset='utf8') {
		$this->_connect();
		$this->_db->set_charset($charset);
	}

	/* ***************************** TRANSACTIONS ************************ */
	/**
	 * Démarre une transaction SQL.
	 * @throws      DatabaseException
	 */
	public function startTransaction() {
		FineLog::log('finebase', FineLog::DEBUG, "Beginning transaction.");
		$this->_connect();
		if ($this->_db->query('START TRANSACTION') === false)
			throw new DatabaseException("Unable to open a new transaction.", DatabaseException::QUERY);
	}
	/**
	 * Effectue le commit d'une transaction SQL.
	 * @throws      DatabaseException
	 */
	public function commit() {
		FineLog::log('finebase', FineLog::DEBUG, "Commiting transaction.");
		$this->_connect();
		if ($this->_db->commit() === false)
			throw new DatabaseException("Error during transaction commit.", DatabaseException::QUERY);
	}
	/**
	 * Effectue le rollback d'une transaction SQL.
	 * @throws      DatabaseException
	 */
	public function rollback() {
		FineLog::log('finebase', FineLog::DEBUG, "Rollbacking transaction.");
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
		$this->_connect();
		return ($this->_db->insert_id);
	}

	/* ********************** METHODES PRIVEES **************** */
	/**
	 * Exécute une requête et retourne son résultat.
	 * @param	string	$sql	La requête à exécuter.
	 * @return	mixed	La ressource du résultat.
	 * @throws	DatabaseException
	 */
	private function _query($sql) {
		FineLog::log('finebase', FineLog::DEBUG, "SQL query: $sql");
		$this->_connect();
		if (($result = $this->_db->query($sql)) === false)
			throw new DatabaseException("Request failed: " . $this->_db->error, DatabaseException::QUERY);
		return ($result);
	}
}

?>
