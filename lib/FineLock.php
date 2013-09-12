<?php

if (!class_exists("FineLock")) {

require_once("finebase/FineLog.php");
require_once("finebase/FineIOException.php");

/**
 * Objet de gestion des locks.
 *
 * Par défaut, cet objet essaye de poser un lock correspondant au script PHP en cours
 * d'exécution. Mais il est possible de demander explicitement à locker un autre fichier.
 * Exemple d'utilisation classique, pour prévenir une exécution concurrente du programme :
 * <code>
 * $lock = new FineLock();
 * try {
 *     $lock->lock();
 *     // traitements particuliers
 *     $lock->unlock();
 * } catch (FineIOException $e) {
 *     // erreur d'entrée-sortie, pouvant venir du lock
 * } catch ($e) {
 *     // erreur
 * }
 * </code>
 * Si le fichier est déjà locké, l'objet regarde depuis combien de temps. Si la durée
 * excède le timeout par défaut (10 minutes), il regarde si le processus qui avait posé
 * le lock existe toujours. Si ce n'est pas le cas, il tente de délocker puis relocker.
 *
 * @author	Amaury Bouchard <amaury.bouchard@finemedia.fr>
 * @copyright	© 2008, FineMedia
 * @package	FineBase
 * @version	$Id$
 */
class FineLock {
	/** Constante - suffixe ajouté aux noms de fichiers de lock. */
	const LOCK_SUFFIX = ".lck";
	/** Constante - durée du timeout de lock par défaut (en secondes). 10 minutes par défaut. */
	const LOCK_TIMEOUT = 600;
	/** Descripteur du fichier de lock. */
	private $_fileHandle = null;
	/** Chemin du fichier de lock. */
	private $_lockPath = null;

	/**
	 * Création d'un lock.
	 * @param	string	$path		(optionnel) Chemin complet du fichier à locker.
	 *					Par défaut, locke le programme qui s'exécute.
	 * @param	int	$timeout	(optionnel) Durée de lock autorisée sur ce fichier, en secondes.
	 * @throws	FineIOException
	 */
	public function lock($path=null, $timeout=null) {
		$filePath = is_null($path) ? $_SERVER['SCRIPT_FILENAME'] : $path;
		FineLog::log('finebase', FineLog::DEBUG, "Locking '$filePath'.");
		$lockPath = $filePath . self::LOCK_SUFFIX;
		$this->_lockPath = $lockPath;
		if (!($this->_fileHandle = @fopen($this->_lockPath, "a+"))) {
			$lockPath = $this->_lockPath;
			$this->_reset();
			throw new FineIOException("Unable to open file '$lockPath'.", FineIOException::UNREADABLE);
		}
		if (!flock($this->_fileHandle, LOCK_EX + LOCK_NB)) {
			// impossible de locker le fichier : on vérifie son âge
			if (($stat = stat($this->_lockPath)) !== false) {
				$usedTimeout = is_null($timeout) ? self::LOCK_TIMEOUT : $timeout;
				if (($stat['ctime'] + $usedTimeout) < time()) {
					// le timeout est expiré : on regarde si le processus existe encore
					$pid = trim(file_get_contents($this->_lockPath));
					$cmd = 'ps -p ' . escapeshellarg($pid) . ' | wc -l';
					$nbr = trim(shell_exec($cmd));
					if ($nbr < 2) {
						// le process n'existe plus : on essaye de délocker puis de relocker
						$this->unlock();
						$this->lock($filePath, $usedTimeout);
						return;
					}
				}
			}
			fclose($this->_fileHandle);
			$this->_reset();
			throw new FineIOException("Unable to lock file '$lockPath'.", FineIOException::UNLOCKABLE);
		}
		// lock OK : on écrit dedans le PID
		ftruncate($this->_fileHandle, 0);
		fwrite($this->_fileHandle, getmypid());
	}
	/**
	 * Libération d'un lock.
	 * @throws	FineIOException
	 */
	public function unlock() {
		if (is_null($this->_fileHandle) || is_null($this->_lockPath)) {
			throw new FineIOException("No file to unlock.", FineIOException::NOT_FOUND);
		}
		flock($this->_fileHandle, LOCK_UN);
		if (!fclose($this->_fileHandle)) {
			$this->_reset();
			throw new FineIOException("Unable to close lock file.", FineIOException::FUNDAMENTAL);
		}
		if (!unlink($this->_lockPath)) {
			$this->_reset();
			throw new FineIOException("Unable to delete lock file.", FineIOException::FUNDAMENTAL);
		}
		$this->_reset();
	}
	/** Vide les attributs privés. */
	private function _reset() {
		$this->_fileHandle = null;
		$this->_lockPath = null;
	}
}

} // class_exists

?>
