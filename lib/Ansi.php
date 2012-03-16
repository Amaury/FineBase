<?php

/**
 * Objet d'écriture de chaînes de caractères dans un terminal avec formatage ANSI.
 *
 * <b>Utilisation</b>
 *
 * <code>
 * // afficher du texte en gras
 * print(Ansi::bold("bla bla bla bla"));
 * // afficher du texte "mince"
 * print(Ansi::faint("bla bla bla"));
 * // afficher du texte souligné
 * print(Ansi::underline("bla bla bla"));
 * // afficher du texte en inversion vidéo
 * print(Ansi::negative("bla bla bla"));
 * // écrire du texte en rouge
 * // les couleurs disponibles sont : black, red, green, yellow, blue, magenta, cyan, white
 * print(Ansi::color("red", "bla bla bla"));
 * // écrire du texte en rouge avec un fond bleu
 * print(Ansi::backColor("blue", "red", "bla bla bla"));
 * </code>
 *
 * @author	Amaury Bouchard <amaury.bouchard@finemedia.fr>
 * @copyright	© 2008, Fine Media
 * @package	FineBase
 * @version	$Id$
 */
class Ansi {
	/** Définition des couleurs. */
	static public $colors = array(
		'black'		=> 0,
		'red'		=> 1,
		'green'		=> 2,
		'yellow'	=> 3,
		'blue'		=> 4,
		'magenta'	=> 5,
		'cyan'		=> 6,
		'white'		=> 7
	);

	/**
	 * Retourne une chaîne formatée en gras.
	 * @param	string	$text	Texte à formater.
	 * @return	string	La chaîne avec les codes de formatage.
	 */
	static public function bold($text) {
		return (chr(27) . "[1m" . $text . chr(27) . "[0m");
	}
	/**
	 * Retourne une chaîne formatée en "mince".
	 * @param	string	$text	Texte à formater.
	 * @return	string	La chaîne avec les codes de formatage.
	 */
	static public function faint($text) {
		return (chr(27) . "[2m" . $text . chr(27) . "[0m");
	}
	/**
	 * Retourne une chaîne formatée en souligné.
	 * @param	string	$text	Texte à formater.
	 * @return	string	La chaîne avec les codes de formatage.
	 */
	static public function underline($text) {
		return (chr(27) . "[4m" . $text . chr(27) . "[0m");
	}
	/**
	 * Retourne une chaîne formatée en inversion vidéo.
	 * @param	string	$text	Texte à formater.
	 * @return	string	La chaîne avec les codes de formatage.
	 */
	static public function negative($text) {
		return (chr(27) . "[7m" . $text . chr(27) . "[0m");
	}
	/**
	 * Retourne une chaîne formatée en couleur.
	 * @param	string	$color	Nom de la couleur à utiliser (black, red, green, yellow, blue, magenta, cyan, white).
	 * @param	string	$text	Texte à formater.
	 * @return	string	La chaîne avec les codes de formatage.
	 */
	static public function color($color, $text) {
		return (chr(27) . "[9" . self::$colors[$color] . "m" . $text . chr(27) . "[0m");
	}
	/**
	 * Retourne une chaîne formatée avec une couleur de fond.
	 * @param	string	$backColor	Couleur de fond.
	 * @param	string	$color		Couleur du texte.
	 * @param	string	$text		Texte à formater.
	 * @return	string	La chaîne avec les codes de formatage.
	 */
	static public function backColor($backColor, $color, $text) {
		return (chr(27) . "[4" . self::$colors[$backColor] . "m" . chr(27) . "[9" . self::$colors[$color] . "m" . $text . chr(27) . "[0m");
	}
}

?>
