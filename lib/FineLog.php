<?php

if (!class_exists('\FineLog')) {

require_once('finebase/FineIOException.php');
require_once('finebase/FineApplicationException.php');

/**
 * Objet de gestion des messages de log.
 *
 * <b>Utilisation basique</b>
 *
 * Cet objet s'utilise de manière statique, pour écrire dans un fichier de log centralisé.
 * Il s'utilise de cette manière :
 * <code>
 * // utilisation basique, niveau de criticité INFO par défaut 
 * FineLog::log("Message de log");
 * // utilisation avancée, en spécifiant la crititité
 * FineLog::log(FineLog::WARN, "Message de warning");
 * // appel depuis l'exécution globale (ni à l'intérieur d'une fonction, ni à l'intérieur d'une méthode).
 * FineLog::fullLog(FineLog::ERROR, "Message d'erreur", __FILE__, __LINE__);
 * </code>
 *
 * <b>Seuils de log</b>
 *
 * Il est aussi possible de définir le seuil de criticité. Tous les message dont le niveau
 * sera inférieur à ce seuil ne seront pas affichés.
 * Il existe 6 niveaux de criticité :
 * - DEBUG : message de débuggage (criticité la plus faible)
 * - INFO : message d'information (niveau par défaut des messages dont le niveau n'est pas précisé)
 * - NOTE : notification ; message normal mais significatif (seuil par défaut)
 * - WARN : message d'alerte ; l'application ne fonctionne pas normalement mais elle peut continuer à fonctionner.
 * - ERROR : message d'erreur ; l'application ne fonctionne pas normalement et elle doit s'arrêter.
 * - CRIT : message d'erreur critique ; l'application risque d'endommager son environnement (filesystem ou base de données).
 *
 * <code>
 * // définition du seuil
 * FineLog::setThreshold(FineLog::INFO);
 * // ce message ne sera pas écrit
 * FineLog::log(FineLog::DEBUG, "Message de débug");
 * // ce message sera écrit
 * FineLog::log(FineLog::NOTE, "Notification);
 * </code>
 *
 * Le chemin vers le fichier de log est défini en appelant la méthode FineLog::setLogFile($path)
 *
 * <b>Classes de log</b>
 *
 * Il est possible de désigner un ensemble de classes de log, qui sont des labels permettant de regrouper les logs.
 * Il est possible de définir un seuil de criticité différent pour chaque classe.
 * <code>
 * // définition du seuil de log par défaut
 * FineLog::setThreshold(FineLog::ERROR);
 * // ce message n'atteint pas le seuil nécessaire, il ne sera pas écrit
 * FineLog::log(FineLog::WARN, "Message sans classe");
 * // initialisation des seuils de log en fonction des classes
 * $thresholds = array(
 *         "default" => FineLog::ERROR,
 *         "testing" => FineLog::DEBUG
 * );
 * FineLog::setThreshold($thresholds);
 * // ajout d'un seuil de log spécifique
 * FineLog::setThreshold("pouet", FineLog::INFO);
 * // ce message sera écrit
 * FineLog::log("default", FineLog::ERROR, "Message utilisant la classe par défaut");
 * // ce message sera écrit
 * FineLog::log("testing", FineLog::CRIT, "Message applicatif");
 * </code>
 *
 * A noter que les messages sans classe désignée sont liés à la classe FineLog::DEFAULT_CLASS (qui a la valeur "default").
 *
 * @author	Amaury Bouchard <amaury.bouchard@finemedia.fr>
 * @copyright	© 2007-2012, FineMedia
 * @package	FineBase
 * @version	$Id: FineLog.php 641 2013-02-11 12:57:59Z abouchard $
 */
class FineLog {
	/** Constante - message de débuggage (priorité la plus faible). */
	const DEBUG = 'DEBUG';
	/** Constante - message d'information (niveau par défaut). */
	const INFO = 'INFO';
	/** Constante - notification ; message normal mais significatif (seuil par défaut). */
	const NOTE = 'NOTE';
	/** Constante - message d'alerte ; l'application ne fonctionne pas normalement mais elle peut continuer à fonctionner. */
	const WARN = 'WARN';
	/** Constante - message d'erreur ; l'application ne fonctionne pas normalement et elle doit s'arrêter. */
	const ERROR = 'ERROR';
	/** Constante - message d'erreur critique ; l'application risque d'endommager son environnement (filesystem ou base de données). */
	const CRIT = 'CRIT';
	/** Nom de la classe de log par défaut. */
	const DEFAULT_CLASS = 'default';
	/** Chemin vers le fichier dans lequel écrire les messages de log. */
	static private $_logPath = null;
	/** Tableau de fonctions à exécuter pour écrire les messages de log de manière personnalisée. */
	static private $_logCallbacks = array();
	/** Seuil actuel de criticité des messages affichés. */
	static private $_threshold = array();
	/** Seuil par défaut pour les messages de log dont la classe n'est pas connue. */
	static private $_defaultThreshold = self::NOTE;
	/** Tableau contenant l'ordre affecté à chaque niveau de log. */
	static private $_levels = array(
		'DEBUG'	=> 10,
		'INFO'	=> 20,
		'NOTE'	=> 30,
		'WARN'	=> 40,
		'ERROR'	=> 50,
		'CRIT'	=> 60
	);
	/** Tableau contenant les labels correspondant aux niveaux de log. */
	static private $_labels = array(
		'DEBUG'	=> 'DEBUG',
		'INFO'	=> 'INFO ',
		'NOTE'	=> 'NOTE ',
		'WARN'	=> 'WARN ',
		'ERROR'	=> 'ERROR',
		'CRIT'	=> 'CRIT '
	);

	/* ******************** METHODES PUBLIQUES ****************** */
	/**
	 * Définit le chemin vers le fichier de log à utiliser.
	 * @param	string	path	Chemin vers le fichier de log.
	 */
	static public function setLogFile($path) {
		self::$_logPath = $path;
	}
	/**
	 * Ajoute une méthode de callback qui sera utilisée pour générer des logs personnalisés.
	 * @param	Closure	$func	La fonction à exécuter.
	 */
	static public function addCallback(Closure $func) {
		self::$_logCallbacks[] = $func;
	}
	/**
	 * Définit le seuil de criticité minimum en-dessous duquel les messages ne sont pas écrits.
	 * @param	string|int|array	$classOrThreshold	Nom de la classe dont le seuil est défini (dans le second paramètre),
	 *								ou valeur de seuil pour le seuil par défaut, ou liste de seuils pour
	 *								pour chaque classe de log.
	 * @param	int|array		$threshold		(optionnel) Valeur de seuil pour le seuil par défaut, ou liste de
	 *								seuils pour chaque classe de log.
	 */
	static public function setThreshold($classOrThreshold, $threshold=null) {
		if (is_string($classOrThreshold) && is_int($threshold))
			self::$_threshold[$classOrThreshold] = $threshold;
		else {
			if (is_array($classOrThreshold))
				self::$_threshold = $classOrThreshold;
			else if (is_int($classOrThreshold))
				self::$_threshold[self::DEFAULT_CLASS] = $classOrThreshold;
		}
	}
	/**
	 * Ecrit un message de log, soit en spécifiant le niveau de priorité, soit avec le niveau INFO.
	 * A n'utiliser que pour ajouter rapidement un message de log temporaire.
	 * Utilisez la méthode fullLog() pour les messages définitifs.
	 * @param	mixed	$classOrMessageOrPriority	Message de log ou niveau de priorité du log ou classe de log.
	 * @param	mixed	$messageOrPriority		(optionnel) Message de log ou niveau de priorité du log.
	 * @param	string	$message				(optionnel) Message de log à écrire.
	 */
	static public function log($classOrMessageOrPriority, $messageOrPriority=null, $message=null) {
		// traitement des paramètres
		if (!is_null($message) && !is_null($messageOrPriority)) {
			$class = $classOrMessageOrPriority;
			$priority = $messageOrPriority;
		} else if (!is_null($messageOrPriority)) {
			$class = self::DEFAULT_CLASS;
			$priority = $classOrMessageOrPriority;
			$message = $messageOrPriority;
		} else {
			$class = self::DEFAULT_CLASS;
			$priority = self::INFO;
			$message = $classOrMessageOrPriority;
		}
		// le message n'est pas écrit si sa priorité est inférieure au seuil défini
		if ((isset(self::$_threshold[$class]) && self::$_levels[$priority] < self::$_levels[self::$_threshold[$class]]) ||
		    (!isset(self::$_threshold[$class]) && self::$_levels[$priority] < self::$_levels[self::$_defaultThreshold]))
			return;
		// traitement du log
		$backtrace = debug_backtrace();
		if (is_array($backtrace) && count($backtrace) > 1) {
			$txt = '';
			if (isset($backtrace[1]['file']) && isset($backtrace[1]['line']))
				$txt .= '[' . basename($backtrace[1]['file']) . ':' . $backtrace[1]['line'] . '] ';
			if (isset($backtrace[1]['class']) && isset($backtrace[1]['type']))
				$txt .= $backtrace[1]['class'] . $backtrace[1]['type'];
			if (isset($backtrace[1]['function']))
				$txt .= $backtrace[1]['function'] . "(): ";
			$txt .= $message;
			if ($priority == self::CRIT) {
				$offset = 0;
				foreach ($backtrace as $trace) {
					if (++$offset < 2)
						continue;
					$txt .= "\n\t#" . ($offset - 1) . '  ' . 
						(isset($trace['class']) ? $trace['class'] : '') . 
						(isset($trace['type']) ? $trace['type'] : '') . 
						(isset($trace['function']) ? $trace['function'] : '') . '() called at [' .
						(isset($trace['file']) ? $trace['file'] : '') . ':' . 
						(isset($trace['line']) ? $trace['line'] : '') . ']';
				}
			}
			$message = $txt;
		}
		self::_writeLog($class, $priority, $message);
	}
	/**
	 * Ecrit un message de log détaillé.
	 * Le premier paramètre est optionnel.
	 * @param	string	$classOrPriority	Classe de log ou niveau de priorité du message.
	 * @param	int	$priorityOrMessage	Niveau de priorité du message, ou message de log à écrire.
	 * @param	string	$messageOrFile		Message de log à écrire ou nom du fichier où la méthode a été appelée.
	 * @param	string	$fileOrLine		Nom du fichier ou numéro de la ligne où la méthode a été appelée.
	 * @param	int	$lineOrCaller		Numéro de la ligne où la méthode a été appelée ou nom de la fonction
	 *						ou de la méthode appelante.
	 * @param	string	$caller			(optionnel) Nom de la fonction ou de la méthode appelante (optionnel).
	 */
	static public function fullLog($classOrPriority, $priorityOrMessage, $messageOrFile, $fileOrLine, $lineOrCaller, $caller=null) {
		// traitement des paramètres
		if (is_null($caller)) {
			// 5 paramètres : pas de classe de log
			$caller = $lineOrCaller;
			$line = $fileOrLine;
			$file = $messageOrFile;
			$message = $priorityOrMessage;
			$priority = $classOrPriority;
			$class = self::DEFAULT_CLASS;
		} else {
			// 6 paramètres : classe de log
			$line = $lineOrCaller;
			$file = $fileOrLine;
			$message = $messageOrFile;
			$priority = $priorityOrMessage;
			$class = $classOrPriority;
		}
		// le message n'est pas écrit si sa priorité est inférieure au seuil défini
		if (!isset(self::$_threshold[$class]) || self::$_levels[$priority] < self::$_levels[self::$_threshold[$class]])
			return;
		// traitement
		$txt = '[' . basename($file) . ":$line]";
		if (!empty($caller))
			$txt .= " $caller()";
		self::_writeLog($class, $priority, "$txt: $message");
	}

	/* ********************** METHODES PRIVEES *************** */
	/**
	 * Ecrit un message dans le fichier de log si sa priorité atteint le seuil défini.
	 * @param	string	$class			Classe de log du message.
	 * @param	int	$priority		Niveau de priorité du message.
	 * @param	string	$message		Message de log à écrire.
	 * @throws	FineApplicationException	Aucun fichier de log n'est défini.
	 * @throws	FineIOException			Problème d'écriture.
	 */
	static private function _writeLog($class, $priority, $message) {
		// ouvre le fichier si nécessaire
		if (isset(self::$_logPath) && !empty(self::$_logPath))
			$path = self::$_logPath;
		else if (empty(self::$_logCallbacks))
			throw new FineApplicationException('No log file set.', FineApplicationException::API);
		$text = date('c') . ' ' . (isset(self::$_labels[$priority]) ? (self::$_labels[$priority] . ' ') : '');
		if (!empty($class) && $class != self::DEFAULT_CLASS)
			$text .= "-$class- ";
		$text .= $message . "\n";
		if (isset($path))
			if (file_put_contents($path, $text, (substr($path, 0, 6) != 'php://' ? FILE_APPEND : null)) === false)
				throw new FineIOException("Unable to write on log file '$path'.", FineIOException::UNWRITABLE);
		foreach (self::$_logCallbacks as $callback)
			$callback($message, self::$_labels[$priority], $class);
	}
}

} // class_exists

?>
