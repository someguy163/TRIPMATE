<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// profile photo, or the first letter of the nickname when there is none
function avatar($name, $img = null)
{
	$n = html_escape($name);
	if ($img)
	{
		$img = preg_replace('#^http://#', 'https://', $img); // avoid mixed-content block behind https (ngrok)
		return '<img class="avatar" src="' . html_escape($img) . '" alt="' . $n . '" title="' . $n . '" referrerpolicy="no-referrer">';
	}
	return '<span class="avatar" title="' . $n . '">' . html_escape(mb_substr($name, 0, 1)) . '</span>';
}
