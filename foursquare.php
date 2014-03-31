<?php

/*
 * Menghubungkan ke akun Foursquare dan menampilkan data checkin terakhir
 * Modifikasi class foursquare-php dari Elie Bursztein http://elie.im / @elie di Twitter
 * Penulis: Andi Saleh http://andisaleh.com / @andisaleh di Twitter
 * 
 * Versi: 0.1.4
 * Lisensi: GPL v3
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
	 */

	function getCheckin($position = 0) {
		
		try {

			$root = $this->rawData->{"response"}->{"checkins"}->{"items"}{$position};
			$this->venueID = $root->{"id"};

			$this->venueNama = $root->{"venue"}->{"name"}; 
			if (isset($root->{"venue"}->{"categories"}[0])) {
				$this->venueKategori = $root->{"venue"}->{"categories"}[0]->{"name"};
				$this->venueIcon = $root->{"venue"}->{"categories"}[0]->{"icon"}->{"prefix"} 
							 . 'bg_32' . $root->{"venue"}->{"categories"}[0]->{"icon"}->{"suffix"} ;
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
				$this->checkinStatus = '&ldquo;' . $root->{"shout"} . '&rdquo;';
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
		$this->getCheckin(0);
	}

	/*
	 * Bangun URL Google Map berdasarkan data checkin
	 * 
	 * @param $width lebar peta
	 * @param $height tinggi peta
	 * @param $zoom tingkat pembesaran, default: 14
	 * @param $mobile untuk halaman mobile, default: false
	 * @param $maptype tipe peta, pilihan: "roadmap", "satellite", "hybrid", dan "terrain". Default: "roadmap"
	 * 
	 * @return URL peta
	 */

	public function getMapUrl($width = 300, $height = 300, $zoom = 14, $markerText = "", $markerColor = "blue", $mobile = FALSE, $maptype = "roadmap") {
		$mapUrl  = "http://maps.google.com/maps/api/staticmap?";
		$mapUrl .= "center=" . $this->venueLat . "," . $this->venueLong;
		$mapUrl .= "&maptype=" . $maptype;
		$mapUrl .= "&size=" . $width . "x" . $height;
		$mapUrl .= "&zoom=" . $zoom;
		$mapUrl .= "&sensor=true";
		$markerText = strtoupper(substr($markerText, 0, 1));
		$mapUrl .= "&markers=color:" . $markerColor . "|label:" . $markerText . "|" . $this->venueLat . "," . $this->venueLong . "|";
		return $mapUrl;
	}
}

?>