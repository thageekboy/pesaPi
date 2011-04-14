<?php
/*	Copyright (c) 2011, PLUSPEOPLE Kenya Limited. 
		All rights reserved.

		Redistribution and use in source and binary forms, with or without
		modification, are permitted provided that the following conditions
		are met:
		1. Redistributions of source code must retain the above copyright
		   notice, this list of conditions and the following disclaimer.
		2. Redistributions in binary form must reproduce the above copyright
		   notice, this list of conditions and the following disclaimer in the
		   documentation and/or other materials provided with the distribution.
		3. Neither the name of PLUSPEOPLE nor the names of its contributors 
		   may be used to endorse or promote products derived from this software 
		   without specific prior written permission.
		
		THIS SOFTWARE IS PROVIDED BY THE REGENTS AND CONTRIBUTORS ``AS IS'' AND
		ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
		IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
		ARE DISCLAIMED.  IN NO EVENT SHALL THE REGENTS OR CONTRIBUTORS BE LIABLE
		FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
		DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
		OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
		HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
		LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
		OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
		SUCH DAMAGE.
 */
namespace PLUSPEOPLE\Mpesapi\loader;

class Loader {
	protected $baseUrl = "https://www.m-pesa.com/ke/";
	protected $config = null;
	protected $curl = null;
	protected $cookieFile = null;

	public function __construct() {
		$this->config = \PLUSPEOPLE\Mpesapi\Configuration::instantiate();
		$this->curl = curl_init($this->baseUrl);
	}

	public function retrieveData($fromTime) {
		$fromTime = (int)$fromTime;
		$pages = array();
		if ($fromTime > 0) {
			$cookiePath = $this->config->getConfig("CookieFolderPath") . '/' . time() . "jarjar.txt";
			$this->cookieFile = fopen($cookiePath, 'w');
			$login = $this->loadLoginPage();
			$search = $this->loadSearchPage($login);
			$pages = $this->loadResults($search, $fromTime);
			fclose($this->cookieFile);
			unlink($cookiePath);
		}
		// return the reverse array - we want the oldest data first.
		return array_reverse($pages);
	}

  ////////////////////////////////////////////////////////////////
  // private functions
  ////////////////////////////////////////////////////////////////
	private function loadLoginPage() {
		curl_setopt($this->curl, CURLOPT_URL, $this->baseUrl);
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->curl, CURLOPT_COOKIESESSION, true);
		curl_setopt($this->curl, CURLOPT_COOKIEJAR, $this->cookieFile);
		curl_setopt($this->curl, CURLOPT_HEADER, false);
		curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true);

		curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($this->curl, CURLOPT_SSLCERT, $this->config->getConfig("MpesaCertificatePath"));
		curl_setopt($this->curl, CURLOPT_SSLCERTTYPE, "PEM");

		$output1 = curl_exec($this->curl);
		// TODO: needs error reporting
		return $output1;
	}

	private function loadSearchPage($loginPage) {
		$viewState = $this->getViewState($loginPage);

		$postData = 
			'__VIEWSTATE=' . urlencode($viewState) . 
			'&LoginCtrl$UserName=' . urlencode($this->config->getConfig("MpesaLoginName")) . 
			'&LoginCtrl$Password=' . urlencode($this->config->getConfig("MpesaPassword")) . 
			'&LoginCtrl$txtOrganisationName=' . urlencode($this->config->getConfig("MpesaCorporation")) . 
			'&LoginCtrl$LoginButton=' . urlencode('Log In'); 

		curl_setopt($this->curl, CURLOPT_URL, $this->baseUrl . "default.aspx?ReturnUrl=%2fke%2fMain%2fhome2.aspx%3fMenuID%3d1826&MenuID=1826");
		curl_setopt($this->curl, CURLOPT_POST, true);
		curl_setopt($this->curl, CURLOPT_POSTFIELDS, $postData); 
		curl_setopt($this->curl, CURLOPT_COOKIEFILE, $this->cookieFile); 

		$searchPage = curl_exec($this->curl);
		// TODO: missing error detection
		return $searchPage;
	}

	private function loadResults($searchPage, $fromTime) {
		$fromTime = (int)$fromTime;
		$pages = array();
		if ($fromTime > 0) {
			$now = time();

			$viewState = $this->getViewState($searchPage);
			$postData = 
				'__VIEWSTATE=' . urlencode($viewState) .
				'&ctl00$Main$ctl00$ctlDatePicker$dlpagesize_Input=' . '500' .
				'&ctl00$Main$ctl00$ctlDatePicker$dlpagesize_text=' . '500' .
				'&ctl00$Main$ctl00$ctlDatePicker$dlpagesize_value=' . '500' .
				'&ctl00$Main$ctl00$ctlDatePicker$dlpagesize_index=' . '3' .
				'&ctl00_Main_ctl00_ctlDatePicker_datePickerStartDate=' . '2011-03-08' .
				'&ctl00$Main$ctl00$ctlDatePicker$datePickerStartDate$dateInput=' . urlencode('2011-3-8 0:0:0') .
				'&ctl00$Main$ctl00$ctlDatePicker$datePickerStartDate$dateInput_TextBox=' . '2011-03-08' .
				'&ctl00_Main_ctl00_ctlDatePicker_datePickerStartDate_calendar_SD=' . urlencode('[]') .
				'&ctl00_Main_ctl00_ctlDatePicker_datePickerEndDate=' . '2011-03-11' .
				'&ctl00$Main$ctl00$ctlDatePicker$datePickerEndDate$dateInput=' . urlencode('2011-3-11 0:0:0') .
				'&ctl00$Main$ctl00$ctlDatePicker$datePickerEndDate$dateInput_TextBox=' . '2011-03-11' .
				'&ctl00_Main_ctl00_ctlDatePicker_datePickerEndDate_calendar_SD=' . urlencode('[]') .
				'&ctl00$Main$ctl00$cbAccountType_Input=' . urlencode('Utility Account') .
				'&ctl00$Main$ctl00$cbAccountType_text=' . urlencode('Utility Account') .
				'&ctl00$Main$ctl00$cbAccountType_value=' . urlencode('9051523') . // very interesting has to be scrubbed
				'&ctl00$Main$ctl00$cbAccountType_index=' . '2' . // might be 2
				'&ctl00$Main$ctl00$rblTransType=' . 'All' .
				'&ctl00$Main$ctl00$btnSearch=' . 'Search' .
				'&ctl00$Main$ctl00$cpeExpandedFilter_ClientState=' . '' . // unkown 
				'&ctl00_Main_ctl00_AccountStatementGrid1_dgStatementPostDataValue=' . '' // unkown
				;
			
			curl_setopt($this->curl, CURLOPT_URL, $this->baseUrl . "/Main/home2.aspx?MenuID=1826");
			curl_setopt($this->curl, CURLOPT_POST, true);
			curl_setopt($this->curl, CURLOPT_POSTFIELDS, $postData); 
			curl_setopt($this->curl, CURLOPT_COOKIEFILE, $this->cookieFile); 

			// TODO: needs to retrieve the following pages, in case there is more than 500 entries
			$result = curl_exec($this->curl);
			// TODO: missing error detection
			$pages[] = $result;
		}
		return $pages;
	}

	private function getViewState($input) {
		$temp = array();
		preg_match("/(?<=__VIEWSTATE\" value=\")(?<val>.*?)(?=\")/", $input, $temp);
		return isset($temp[1]) ? $temp[1] : "";
	}

}

?>