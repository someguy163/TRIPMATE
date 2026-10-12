<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// The security token (CSRF) of a page that was kept open for a very long time, or whose cookies were cleared, no longer matches.
// CodeIgniter's English message tells nobody what to do: say it in Korean, with the way out.
class MY_Security extends CI_Security {

	public function csrf_show_error()
	{
		show_error('화면이 오래 열려 있었거나 보안 확인이 만료됐어요. 페이지를 새로고침한 뒤 다시 눌러 주세요.', 403);
	}
}
