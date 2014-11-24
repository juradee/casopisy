<?php

namespace Casopisy;

use Nette,
    Nette\Environment;
use \dibi;

/**
	CREATE TABLE `cislo` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`casopis_id` tinyint(2) NOT NULL,
	`rocnik` varchar(10) COLLATE utf8_czech_ci NOT NULL,
	`cislo` tinyint(2) NOT NULL,
	`rok` smallint(4) NOT NULL,
	`mesic` tinyint(2) NOT NULL,
	`verejne` tinyint(1) NOT NULL,
	`priloha` tinyint(1) NOT NULL,
	`hotovo` tinyint(1) NOT NULL,
	`poznamka` text COLLATE utf8_czech_ci NOT NULL,
	`nazev` varchar(255) COLLATE utf8_czech_ci NOT NULL,
	`popis` text COLLATE utf8_czech_ci NOT NULL,
	`pocet_stran` int(11) NOT NULL,
	PRIMARY KEY (`id`)

 * @property-read array $stranky
 */
class Cislo extends Entity {

	private $obsah = array();
	private $dvoustrankyCache = array();
	private $strankyCache = array();




    /**
     * @return Obsah[] indexed by strana
     */
    function getObsah() {
		if (count($this->obsah))
			return $this->obsah;

	    // set obsah
	    for ($pagenum = 1; $pagenum <= $this->pocet_stran; $pagenum++) {
		    if (isset($this->stranky[$pagenum]))
			    $obsah = new Obsah($this->stranky[$pagenum]);
		    else
			    $obsah = new Obsah(array("cislo_id" => $this->id, "strana" => $pagenum));

		    $this->obsah[$pagenum] = $obsah;
		    $pagenum += $obsah->strany_navic;
	    }

        return $this->obsah;
    }

	/** @return array index [strana] */
	function getStranky(){
		if (count($this->strankyCache))
			return $this->strankyCache;

		$this->strankyCache = dibi::query("SELECT * FROM obsah WHERE cislo_id=%i", $this->id)->fetchAssoc('strana');
		//todo: cache tags here

		return $this->strankyCache;
	}

	/** @return Obsah[] */
	function getDvoustranky(){
		if (count($this->dvoustrankyCache))
			return $this->dvoustrankyCache;

		$dvoustranka = array();
		for ($pagenum = 1; $pagenum <= $this->pocet_stran; $pagenum++)
		{
			if (isset($this->stranky[$pagenum]))
				$obsah = new Obsah($this->stranky[$pagenum]);
			else
				$obsah = new Obsah(array("cislo_id" => $this->id, "strana" => $pagenum));
			$dvoustranka[] = $obsah;

			//každou lichou OR poslední stránku přidáme do pole
			if ($pagenum % 2 == 1 OR $pagenum == $this->pocet_stran) {
				$this->dvoustrankyCache[] = $dvoustranka;
				$dvoustranka = array();
			}
		}

		return $this->dvoustrankyCache;
	}

	function getPrilohy() {
        $prilohy = dibi::query("
            SELECT *
            FROM cislo
            WHERE casopis_id=%i", $this->casopis_id, "
                AND rocnik=%i", $this->rocnik, "
                AND cislo=%i", $this->cislo, "
                AND priloha=1
                %if", !CasopisModel::$showUnpublished, " AND verejne = 1 %end
          ");
        $result = array();
        foreach ($prilohy as $r)
            $result[] = new Cislo($r);

        return $result;
    }

    /** WARNING: NOT FILLED FROM DATABASE BEFORE getObsah() CALLED
     * @return \Casopisy\Obsah
     */
    function getPage($strana = 1) {
		if (isset($this->obsah[$strana]))
			return $this->obsah[$strana];
        return new Obsah(array("cislo_id" => $this->id, "strana" => $strana));
    }

    function getCoverLink($size = "") {
        return $this->getPage()->getLink(1, $size);
    }

    function deleteObsah($strana) {
        dibi::query("DELETE FROM obsah WHERE cislo_id=%i", $this->id, " AND strana=%i", $strana);
        dibi::query("DELETE FROM [tag] WHERE cislo_id=%i", $this->id, " AND strana=%i", $strana);
    }

    function getLogFile() {
        return Environment::getVariable("dataDir") . "/pdf/$this->id.log";
    }

	function getBookmarksFile() {
		return Environment::getVariable("dataDir") . "/pdf/$this->id.pdf.bookmarks";
	}

    function getPdfPath() {
        return Environment::getVariable("dataDir") . "/pdf/$this->id.pdf";
    }

    function getPdfLink() {
        return Environment::getHttpRequest()->getUrl()->getBasePath()
                . "data/pdf/$this->id.pdf";
    }

	function getPdfFilename() { //Roverský kmen 2004-05.pdf
		if (CasopisModel::config($this->casopis_id)->knihovna)
			return "$this->nazev ($this->rocnik).pdf";

		$spec = $this->nazev ? " - $this->nazev" : '';
		return "$this->casopis $this->rok-$this->mesic$spec.pdf";
	}

    function getPdfSize(){
        $size = @filesize($this->getPdfPath());
        return round($size / 1024 / 1024, $size>10e7 ? 0 : 1) . " MB";
    }

    function getCasopis() {
        return CasopisModel::getCasopisLongById($this->casopis_id);
    }

    function getMesicTxt() {
        if (!$this->mesic)
            return "N/A";
        return CasopisModel::$months[$this->mesic];
    }

    function getMesicrok() {
        return "$this->mesicTxt $this->rok";
    }

	function getCelynazev() {
		if (CasopisModel::config($this->casopis_id)->knihovna)
			return "$this->nazev ($this->rocnik)";
		$spec = $this->nazev ? " - $this->nazev" : '';
		return "$this->casopis $this->mesicrok$spec";
	}

	/** @see Rocnik::getRocnikTxt() */
	function getRocnikTxt() {
		if (CasopisModel::config($this->casopis_id)->knihovna)
			return ucfirst($this->rocnik);
		return (strlen($this->rocnik) < 4) ? "$this->rocnik. ročník" : "ročník $this->rocnik";
	}

	function getPercentDone() {
		$hotovo = $this->pocet_tagu + $this->pocet_nazvu;
		$obsahu = 2*$this->pocet_obsahu -3; // 3 tolerujeme chybějící za Titulku a reklamu
		return $hotovo >= $obsahu ? 100 : round($hotovo / $obsahu *100);
	}

    function save($newdata = false) {
        //(is_array or arrayHash)
        if ($newdata) {
            foreach ($newdata as $k => $v) //merge changes to orig data array
                $this[$k] = $v;
        }
        return dibi::query("UPDATE cislo SET ", $this->getData(), " WHERE id=%i", $this->id);
    }

	public function getBookmarks() {
		$obsah = $this->getObsah();
		$bookmarks = array();
		$bookmark = new \stdClass;

		if(!file_exists($this->getBookmarksFile()))
			return array();

		$toc = file($this->getBookmarksFile());
		foreach ($toc as $row) {
			list($key, $val) = explode(':', $row, 2);
			$val = trim(html_entity_decode($val, NULL, 'UTF-8'));
			if($key == 'BookmarkTitle') {
				$bookmark->title = $val;
			}
			if($key == 'BookmarkLevel') {
				$bookmark->level = $val;
			}
			if($key == 'BookmarkPageNumber') {
				$p = $bookmark->page = $val;
				$bookmark->obsah = @$obsah[$p];

				if (isset($bookmarks[$p])) //more headings per page
					$bookmarks[$p]->title .= " / " . $bookmark->title;
				else
					$bookmarks[$p] = $bookmark;

				$bookmark = new \stdClass;
			}
		}
		return $bookmarks;
	}

	public function indexFulltext() {
		$pdf = escapeshellarg($this->getPdfPath());
		$len = 0;

		for($p=1; $p<=$this->pocet_stran; $p++)
		{
			$outputarr = array();
			exec("pdftotext -f $p -l $p $pdf  -", $outputarr, $returnval);
			$output = implode("\n", $outputarr);

			//save only meaningful content AND title page for note=already indexed
			if(trim($output) OR $p==1){
				dibi::query('REPLACE INTO `fulltext`', array('cislo_id' => $this->id, 'strana'=>$p, 'text'=>trim($output)));
				$len += strlen($output);
			}
		}
		return $len;
	}
}
