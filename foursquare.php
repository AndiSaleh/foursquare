<?php

/*
 * Memangil class foursquare dan menyimpan hasilnya ke dalam file
 * 
 * @param $token untuk mengakses Foursquare API
 * 
 * @versi 0.1
 * @tanggal 25/03/14 
 * @penulis Andi Saleh
 */

function fsq($token) {
	// Set waktu kadaluarsa, lokasi file cache, dan waktu pembuatannya
	$cachetime = 60*5; // 5 menit
	$cache_file = dirname(__FILE__) . '/checkin.txt';
	$cache_file_created  = ((file_exists($cache_file))) ? filemtime($cache_file) : 0;
	// Periska bila file telah kadaluarsa
	if (time() - $cache_file_created > $cachetime ) {
		// Inisiasi data baru
		$fsq = new foursquare($token);
		// Hanya lanjut jika Fousquare tidak down dan data ditemukan
		if($fsq->venueID != '') {
			// Simpan data checkin ke file
			$fqcheckin = serialize($fsq);
			file_put_contents($cache_file, $fqcheckin);
		} 
	}
	/*
	 * Tarik data dan peta dari file cache
	 * Data tetap ditarik diluar fungsi `if` di atas karena jika waktu kadaluarsa
	 * telah lewat namun data checkin baru gagal ditemukan, kita masih bisa
	 * menampilkan data lama dari cahce
	 */
	$fqcheckin = file_get_contents($cache_file);
	$fqcheckin = unserialize($fqcheckin);
	return $fqcheckin;
}

/*
 * Menghubungkan ke akun Foursquare dan menampilkan data chekin terakhir beserta Google Map dan menampilkannya di halaman
 * Modifikasi class foursquare-php dari Elie Bursztein http://elie.im / @elie di Twitter
 * Penulis: Andi Saleh http://andisaleh.com / @andisaleh di Twitter
 * 
 * Versi: 0.1
 * Licensi: GPL v3
 */

class foursquare {

	private $token = "";
	private $rawData = "";
	private $url = "https://api.foursquare.com/v2/users/self/checkins?v=20131016&limit=1&locale=id&oauth_token=";
	public $venueNama = "";
	public $venueKategori = "";
	public $venueIcon = "http://foursquare.com/img/categories/question.png";
	public $venueTipe = "";
	public $venueAlamat = "";
	public $venueKota = "";
	public $venuePropinsi = "";
	public $venueNegara = "";
	public $venueLat = "";
	public $venueLong = "";
	public $checkinTgl = "";
	public $checkinStatus = "";

	/*
	 * Parse dan ekstrak data checkin Foursquare
	 *
	 * @param $number data checkin yang diambil (0: checkin terakhir, n: n checkin sebelumnya)
	 * 
	 * @versi 0.1
	 * @tanggal 25/03/14 
	 * @penulis Andi Saleh
	 */

	function getCheckin($position = 0) {
		
		try {

			$root = $this->rawData->{"response"}->{"checkins"}->{"items"}{$position};
			$this->venueID = $root->{"id"};

			$this->venueNama = $root->{"venue"}->{"name"}; 
			if (isset($root->{"venue"}->{"categories"}[0])) {
				$this->venueKategori = $root->{"venue"}->{"categories"}[0]->{"name"};
				$this->venueIcon = $root->{"venue"}->{"categories"}[0]->{"icon"};
				if (isset($root->{"venue"}->{"categories"}[0]->{"parents"}[0]))
					$this->venueTipe = $root->{"venue"}->{"categories"}[0]->{"parents"}[0];
			}

			if (isset($root->{"venue"}->{"location"})) {
				if (isset($root->{"venue"}->{"location"}->{"address"}))
					$this->venueAlamat = $root->{"venue"}->{"location"}->{"address"}; 
				if (isset($root->{"venue"}->{"location"}->{"city"})) 
					$this->venueKota = $root->{"venue"}->{"location"}->{"city"};
				if (isset($root->{"venue"}->{"location"}->{"state"})) 
					$this->venuePropinsi = $root->{"venue"}->{"location"}->{"state"};
				if (isset($root->{"venue"}->{"location"}->{"country"})) 
					$this->venueNegara = $root->{"venue"}->{"location"}->{"country"};
				$this->venueLat = $root->{"venue"}->{"location"}->{"lat"}; 
				$this->venueLong = $root->{"venue"}->{"location"}->{"lng"}; 
			}

			$timestamp = $root->{"createdAt"};
			$timezone = $root->{"timeZone"};
			date_default_timezone_set($timezone);
			$this->checkinTgl = date("F j, Y, g:i a", $timestamp);

			if (isset($root->{"shout"})) {
				$this->checkinStatus = $root->{"shout"};
			}

		} 
		catch (Exception $e) {
		}
	}

	/*
	 * Simpan data checkin Foursquare sebagai objek
	 *
	 * @param $token Foursquare oAuth token v2
	 * @param $safe nonaktifkan validasi sertifikat SSL
	 * 
	 * @versi 0.1
	 * @tanggal 25/03/14 
	 * @penulis Andi Saleh
	 */

	function __construct($token, $safe = false) {

		$req = $this->url . $token;
		// tarik data
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $req);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($ch, CURLOPT_USERAGENT, "fetcher " . time());
		curl_setopt($ch, CURLOPT_SSLVERSION, 3);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $safe);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		$data = curl_exec($ch);
		curl_close($ch);

		// decode data
		$this->rawData = json_decode($data);

		if ($this->rawData->{"meta"}->{"code"} != 200) {
			return;
		}
		#parse the last checking data ($number == 0)
		$this->getCheckin(0);
	}

	/*
	 * Bangun URL Google Map berdasarkan data checkin
	 * 
	 * @param $width lebar peta
	 * @param $height tinggi peta
	 * @param $zoom tingkat pembesaran, default: 12
	 * @param $mobile untuk halaman mobile, default: false
	 * @param $maptype tipe peta, pilihan: "roadmap", "satellite", "hybrid", dan "terrain". Default: "roadmap"
	 * 
	 * @return URL peta
	 * 
	 * @version 0.2
	 * @date 08/24/11 
	 * @author Elie
	 * 
	 */

	public function getMapUrl($width = 300, $height = 300, $zoom = 12, $mobile = FALSE, $maptype = "roadmap") {
		$mapUrl  = "http://maps.google.com/maps/api/staticmap?";
		$mapUrl .= "center=" . $this->venueLat . "," . $this->venueLong;
		$mapUrl .= "&maptype=" . $maptype;
		$mapUrl .= "&size=" . $width . "x" . $height;
		$mapUrl .= "&zoom=" . $zoom;
		$mapUrl .= "&sensor=true";
		$rems = str_replace('https', 'http', $this->venueIcon->prefix);
		$mapUrl .= "&markers=icon:" . $rems . "bg_32" . $this->venueIcon->suffix . "|" . $this->venueLat . "," . $this->venueLong . "|";
		return $mapUrl;
	}
}

?>