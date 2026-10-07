<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// GET (or form POST when $post is given) -> decoded JSON, null on failure
function http_json($url, $post = null, $headers = [])
{
	$ch = curl_init($url);
	curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_HTTPHEADER => $headers]);
	if ($post)
	{
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
	}
	$res = json_decode((string) curl_exec($ch), true);
	curl_close($ch);
	return $res;
}
