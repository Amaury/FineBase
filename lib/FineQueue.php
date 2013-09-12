<?php

require_once('finebase/FineDatabase.php');
require_once('finebase/FineApplicationException.php');

/**
 * Objet de gestion d'une file de messages.
 *
 * <code>
 * // création de l'objet de gestion des files de messages
 * $queue = new FineQueue($db);
 *
 * // définition de la file de messages
 * $queue->setQueueName("ma_file_de_message");
 *
 * // ajout d'un message dans la file
 * $data = array(
 *     'toto' => "pouet",
 *     'aaaa' => "bbbb"
 * );
 * $queue->sendMessage($data);
 *
 * // récupération du prochain message à traiter
 * $message = $queue->getMessage();
 * $data = $message['content'];
 *
 * // effacement du message après traitement
  * $queue->removeMessage($message['id']);
 * </code>
 *
 * @author	Amaury Bouchard <amaury.bouchard@finemedia.fr>
 * @copyright	© 2011, Fine Media
 * @package	FineBase
 * @version	$Id$
 */
class FineQueue {
	/** Connexion à la base de données. */
	private $_db = null;
	/** Identifiant de la file de messages courante. */
	private $_queueId = null;

	/**
	 * Constructeur.
	 * @param	FineDatabase	$db	Connexion à la base de données.
	 */
	public function __construct(FineDatabase $db) {
		$this->_db = $db;
	}
	/**
	 * Définition du nom de la file de messages courante.
	 * @param	string	$queueName		Nom de la file de messages.
	 * @throws	FineApplicationException	Si le nom est invalide.
	 */
	public function setQueueName($queueName) {
		if (empty($queueName) || mb_strlen($queueName) > 25)
			throw new FineApplicationException("Bad queue name.", FineApplicationException::API);
		// création de la file de messages
		$sql = "INSERT INTO queue.tQueue
			SET que_s_name = '" . $this->_db->quote($queueName) . "'
			ON DUPLICATE KEY UPDATE que_i_id = que_i_id";
		$this->_db->exec($sql);
		// récupération de l'identifiant de la file de messages
		$sql = "SELECT que_i_id AS id
			FROM queue.tQueue
			WHERE que_s_name = '" . $this->_db->quote($queueName) . "'";
		$res = $this->_db->queryOne($sql);
		if (!isset($res['id']) || empty($res['id']))
			throw new Exception("Unable to create queue '$queueName'.");
		$this->_queueId = $res['id'];
	}
	/**
	 * Crée un message dans la file courante.
	 * @param	mixed	$content	Contenu du message, qui sera sérialisé en JSON.
	 * @return	int	Identifiant unique du message.
	 * @throws	FineApplicationException	Si la file courante n'a pas été définie ou si le message est trop gros.
	 */
	public function sendMessage($content) {
		if (!$this->_queueId)
			throw new FineApplicationException("Current message queue not set.", FineApplicationException::API);
		$content = json_encode($content);
		if (mb_strlen($content, 'ASCII') > 65535)
			throw new FineApplicationException("Content size exceed the limit.", FineApplicationException::API);
		$sql = "INSERT INTO queue.tMessage
			SET mes_d_creation = NOW(),
			    mes_e_status = 'pending',
			    mes_s_token = '',
			    mes_s_content = '" . $this->_db->quote($content) . "',
			    que_i_id = '" . $this->_queueId . "'";
		$this->_db->exec($sql);
		return ($this->_db->lastInsertId());
	}
	/**
	 * Réclame le prochain message de la file courante, et le marque "en cours de travail".
	 * @return	array	Un tableau associatif avec les clés 'id' et 'content'.
	 * @throws	Exception	Si la file courante n'a pas été définie.
	 */
	public function getMessage() {
		if (!$this->_queueId)
			throw new Exception("Current message queue not set.");
		$messages = $this->getMessages(1, "worker");
		return (isset($messages[0]) ? $messages[0] : null);
	}
	/**
	 * Réclame le prochain message de la file courante.
	 * @param	int	$nbr	Nombre maximal de messages à récupérer. 1 par défaut.
	 * @param	string	$type	(optionnel) Type de récupération ("worker" par défaut) :
	 *				- "reader" : Récupère le(s) prochain(s) message(s) sans le(s) modifier.
	 *				- "worker" : Récupère le(s) prochain(s) message(s) et le(s) marque "en cours de travail".
	 *				- "eater" : Récupère le(s) prochain(s) message(s) et les efface.
	 * @return	array	Une liste de tableaux associatifs avec les clés 'id' et 'content'.
	 * @throws	Exception	Si la file courante n'a pas été définie.
	 */
	public function getMessages($nbr=1, $type="worker") {
		if (!$this->_queueId)
			throw new Exception("Current message queue not set.");
		if (!is_int($nbr) && !ctype_digit($nbr))
			throw new Exception("Bad number of messages.");
		if ($type != "reader") {
			$token = hash('md5', time() . mt_rand() . mt_rand() . mt_rand() . mt_rand());
			// "réservation" du ou des messages
			$sql = "UPDATE queue.tMessage
				SET mes_s_token = '" . $this->_db->quote($token) . "',
				    mes_e_status = 'processing'
				WHERE que_i_id = '" . $this->_db->quote($this->_queueId) . "'
				  AND mes_e_status = 'pending'
				LIMIT $nbr";
			$this->_db->exec($sql);
		}
		// récupération du ou des messages
		$sql = "SELECT	mes_i_id AS id,
				mes_d_creation AS creationDate,
				mes_t_update AS updateDate,
				mes_e_status AS status,
				mes_s_content AS content
			FROM queue.tMessage
			WHERE que_i_id = '" . $this->_db->quote($this->_queueId) . "' ";
		if ($type == "reader")
			$sql .= "AND mes_e_status = 'pending' ";
		else
			$sql .= "AND mes_s_token = '" . $this->_db->quote($token) . "' ";
		$sql .= "LIMIT $nbr";
		$messages = $this->_db->queryAll($sql);
		foreach ($messages as &$message)
			$message['content'] = json_decode($message['content'], true);
		if ($type == "reader" || $type == "worker")
			return ($messages);
		// type == "eater"
		$sql = "DELETE FROM queue.tMessage
			WHERE que_i_id = '" . $this->_db->quote($this->_queueId) . "'
			  AND mes_s_token = '" . $this->_db->quote($token) . "'";
		$this->_db->exec($sql);
		return ($messages);
	}
	/**
	 * Efface un message de la file courante.
	 * @param	int	$id	Identifiant du message à effacer.
	 * @throws	Exception	Si la file courante n'a pas été définie.
	 */
	public function removeMessage($id) {
		if (!$this->_queueId)
			throw new Exception("Current message queue not set.");
		$sql = "DELETE FROM queue.tMessage
			WHERE mes_i_id = '" . $this->_db->quote($id) . "'
			  AND que_i_id = '" . $this->_db->quote($this->_queueId) . "'";
		$this->_db->exec($sql);
	}
}

?>
