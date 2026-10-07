<?php
defined('BASEPATH') OR exit('No direct script access allowed');
// Used by show_error(): the messages we pass in are already Korean sentences (e.g. "방장만 할 수 있어요.").
// Self-contained (no helpers) because it can also be raised before the app is fully loaded.
$home = html_escape(config_item('base_url'));
?><!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>문제가 생겼어요 - TripMate</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Do+Hyeon&display=swap">
<style>
	:root { --paper:#F2F6F7; --ink:#14212B; --muted:#5A6A75; --sea:#0A7F74; --on-sea:#fff; --line:#D5DFE3; }
	@media (prefers-color-scheme: dark) { :root { --paper:#0E1519; --ink:#E9F0F2; --muted:#9BAAB4; --sea:#2CBBA9; --on-sea:#06201C; --line:#2B3A44; } }
	* { box-sizing:border-box; }
	body { margin:0; min-height:100vh; display:grid; place-items:center; padding:24px; background:var(--paper); color:var(--ink); font:16px/1.6 'Pretendard Variable',Pretendard,system-ui,'Malgun Gothic',sans-serif; word-break:keep-all; text-align:center; }
	main { max-width:420px; }
	h1 { margin:0 0 12px; font:30px/1.25 'Do Hyeon',sans-serif; font-weight:400; }
	.msg p { margin:0 0 8px; color:var(--muted); }
	.msg { margin-bottom:24px; }
	a { display:inline-flex; align-items:center; justify-content:center; min-height:44px; padding:0 24px; border-radius:999px; background:var(--sea); color:var(--on-sea); font-weight:600; text-decoration:none; }
	a:focus-visible { outline:3px solid #3B6DF0; outline-offset:2px; }
</style>
</head>
<body>
	<main>
		<h1>문제가 생겼어요</h1>
		<div class="msg"><?php echo $message; ?></div>
		<a href="<?php echo $home; ?>">내 모임으로 돌아가기</a>
	</main>
</body>
</html>
