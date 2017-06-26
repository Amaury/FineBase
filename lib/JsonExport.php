<?php

/**
 * Objet de génération de fichier JSON lisible par un être humain.
 *
 * @author	Amaury Bouchard <amaury@amaury.net>
 */
class JsonExport {
	/**
	 * Écrit les données au format JSON, lisible par un être humain.
	 * @param	mixed	$data	Les données à écrire.
	 * @param	int	$indent	(optionnel) Le nombre d'indentations.
	 */
	static public function writeData($data, $indent=0) {
		$indent++;
		if (is_null($data)) {
			print('null');
		} else if (is_bool($data)) {
			print($data ? 'true' : 'false');
		} else if (is_int($data) || is_float($data)) {
			print($data);
		} else if (is_string($data)) {
			print('"' . addcslashes($data, '"/') . '"');
		} else if (is_array($data)) {
			// vérification des clés
			$numericKeys = true;
			$i = 0;
			foreach ($data as $key => $subdata) {
				if (!is_numeric($key) || $key != $i) {
					$numericKeys = false;
					break;
				}
				$i++;
			}
			// écriture
			print(($numericKeys ? '[' : '{') . "\n");
			$loopNbr = 1;
			foreach ($data as $key => $subdata) {
				self::_indent($indent);
				if (!$numericKeys)
					print('"' . addcslashes($key, "\"'") . '": ');
				self::writeData($subdata, $indent);
				if ($loopNbr < count($data))
					print(',');
				print("\n");
				$loopNbr++;
			}
			self::_indent($indent - 1);
			print($numericKeys ? ']' : '}');
		} else
			throw new Exception("Non-scalar data\n" . print_r($data, true));
		$indent--;
	}
	/**
	 * Indente le texte du nombre de tabulations demandé.
	 * @param	int	$nbr	Nombre de tabulations.
	 */
	static private function _indent($nbr) {
		for ($i = 0; $i < $nbr; $i++)
			print("\t");
	}
}

?>
