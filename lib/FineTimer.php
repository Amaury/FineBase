<?php

require_once("finebase/ApplicationException.php");

/**
 * Objet de chronométrage.
 *
 * @author	Amaury Bouchard <amaury.bouchard@finemedia.fr>
 * @copyright	© 2007, FineMedia
 * @package	FineBase
 * @version	$Id: FineTimer.php 596 2012-01-05 15:59:49Z abouchard $
 */
class FineTimer {
	/** Date de début de chronométrage. */
	private $_begin = null;
	/** Date de fin de chronométrage. */
	private $_end = null;

	/** Démarre un chronométrage. */
	public function start() {
		$this->_begin = microtime();
		$this->_end = null;
	}
	/** Termine un chronométrage. */
	public function stop() {
		$this->_end = microtime();
	}
	/** Relance un chronométrage sans repartir de zéro. */
	public function resume() {
		$this->_end = null;
	}
	/**
	 * Retourne le temps écoulé pendant le chronométrage.
	 * @return	int	Le temps écoulé en microsecondes.
	 * @throws	ApplicationException
	 */
	public function getTime() {
		if (is_null($this->_begin))
			throw new ApplicationException("Le chronomètre n'a pas été lancé correctement.", ApplicationException::API);
		list($uSecondeA, $secondeA) = explode(" ", $this->_begin);
		$end = is_null($this->_end) ? microtime() : $this->_end;
		list($uSecondeB, $secondeB) = explode(" ", $end);
		$total = ($secondeA - $secondeB) + ($uSecondeA - $uSecondeB);
		return (number_format(abs($total), 16));
	}
}

?>
