<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// GET (or form POST when $post is given, or another $method such as DELETE) -> decoded JSON, null on failure
function http_json($url, $post = null, $headers = [], $method = null)
{
	$ch = curl_init($url);
	curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_HTTPHEADER => $headers, CURLOPT_USERAGENT => 'TripMate']);
	// some shared hosts ship no CA list (cURL error 60); a cacert.pem placed here is used, certificates stay verified
	$ca = APPPATH . 'third_party/cacert.pem';
	if (is_file($ca)) curl_setopt($ch, CURLOPT_CAINFO, $ca);
	if ($method) curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
	if ($post)
	{
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
	}
	$res = json_decode((string) curl_exec($ch), true);
	curl_close($ch);
	return $res;
}
