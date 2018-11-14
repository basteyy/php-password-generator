<?php

/**
 * A password generator that generates memorable passwords similar to the
 * macOS keychain. For this it uses a public RSS feed to build up a list of
 * words to be used in passwords.
 *
 * After successfully creating a list of words that list is written to disk
 * in the file <i>wordlist.json</i>. The next time you create an instance
 * of this class and the URL is unavailable that cached version is then used.
 *
 * Consecutive password generations with the very same instance won't
 * recreate the word list but reuse the former one.
 *
 * @example PasswordGenerator.example.php Class in action.
 *
 * @author Johann Werner <johann.werner@posteo.de>
 * @version 1.0.0
 * @license MIT License
 */
class PasswordGenerator {
	private $url;
	private $minlength;
	private $maxlength;
	private $wordlist  = [];
	private $wordcache = 'wordlist.json';

	/**
	 * Creates an instance of the password generator. You can pass an optional
	 * array with values that should override the default values for the keys:
	 * <dl>
	 *   <dt>url</dt>
	 *   <dd>The URL to fetch XML from which is used to create a wordlist from
	 *   it's description nodes.</dd>
	 *   <dt>minlength</dt>
	 *   <dd>The miminum length of characters a word must have.</dd>
	 *   <dt>maxlength</dt>
	 *   <dd>The maxinum length of characters a word must have.</dd>
	 * </dl>
	 *
	 * @param array optional config array
	 * @param boolean true if data from URL should be fetched, false to use
	 * only cached wordlist; defaults to true
	 *
	 * @throws InvalidArgumentException if the URL is not valid
	 */
	public function __construct($params = [], $fetch = true) {
		foreach($params as $key => $value) {
			$this->$key = $value;
		}
		if ($fetch) {
			if (!isset($this->url) || !filter_var($this->url, FILTER_VALIDATE_URL)) {
				throw new InvalidArgumentException('Invalid URL: ' . $this->url);
			}
			if ($this->minlength > $this->maxlength) {
				throw new InvalidArgumentException('Invalid word lengths: min='
					. $this->minlength . ' max=' . $this->maxlength);
			}
			$this->populate_wordlist();
		} else {
			$this->read_wordlist();
		}
	}

	/**
	 * Creates an instance of password generator that will use German wordlist.
	 *
	 * @static
	 * @return PasswordGenerator configured instance
	 */
	public static function DE() {
		return new self([
			'url'       => 'http://www.tagesschau.de/newsticker.rdf',
			'minlength' => 8,
			'maxlength' => 15,
		]);
	}

	/**
	 * Creates an instance of password generator that will use English wordlist.
	 *
	 * @static
	 * @return PasswordGenerator configured instance
	 */
	public static function EN() {
		return new self([
			'url'       => 'http://rss.dw.com/rdf/rss-en-all',
			'minlength' => 4,
			'maxlength' => 12,
		]);
	}

	/**
	 * Creates an instance of password generator that will use the cached wordlist.
	 * No HTTP reqeust to the URL source will be made. Be sure that you have an
	 * appropriate file <i>wordlist.json</i> present.
	 *
	 * @static
	 * @return PasswordGenerator configured instance that uses cache only
	 */
	public static function CACHED() {
		return new self([], false);
	}

	/**
	 * Generates a password and returns it. If the used wordlist is empty and no
	 * password can be generated the value null is returned.
	 *
	 * @return string|null generated password or null if there is no wordlist
	 */
	public function generate() {
		$listlength = count($this->wordlist);
		if ($listlength < 1) {
			$this->read_wordlist();
			$listlength = count($this->wordlist);
			if ($listlength < 1) {
				return null;
			}
		}

		$words = [];
		$times = 2;
		while ($times--) {
			$r = $this->random_int(0, $listlength - 1);
			$words[] = $this->wordlist[$r];
		}

		return $words[0] . $this->random_int(1, 999) . chr($this->random_int(33, 47)) . $words[1];
	}

	private function populate_wordlist() {
		$input = $this->get_url_data($this->url);
		$doc = new DOMDocument();
		@$doc->loadXML($input);
		$descriptions = $doc->getElementsByTagName('description');
		$wordlist = array();
		foreach($descriptions as $description) {
			$text = $description->textContent;
			$words = explode(' ', $text);
			foreach($words as $word) {
				$cleanword = preg_replace('/[,.;:?!\'"]+/', '', trim($word));
				$wordlength = strlen($cleanword);

				if ($wordlength >= $this->minlength && $wordlength <= $this->maxlength && ctype_alpha($cleanword)) {
					$wordlist[strtoupper(substr($cleanword, 0, 1)) . strtolower(substr($cleanword, 1))] = 1;
				}
			}
		}
		$this->wordlist = array_keys($wordlist);

		if (count($wordlist) > 0) {
			$this->save_wordlist();
		}
	}

	private function save_wordlist() {
		file_put_contents($this->wordcache, json_encode($this->wordlist));
	}

	private function read_wordlist() {
		if (file_exists($this->wordcache)) {
			$this->wordlist = json_decode(file_get_contents($this->wordcache), true);
		}
	}

	private function random_int($low, $high) {
		if (version_compare(PHP_VERSION, '7.0.0', '<')) {
			return rand($low, $high);
		}
		return random_int($low, $high);
	}

	private function get_url_data($url) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		$data = curl_exec($ch);
		curl_close($ch);

		return $data;
	}
}

?>