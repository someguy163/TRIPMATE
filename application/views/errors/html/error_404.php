<?php
defined('BASEPATH') OR exit('No direct script access allowed');
// Shown for a missing page AND for a group the person was not invited to: the wording is the same on purpose,
// so nobody can tell whether a group exists. Self-contained (no helpers) because the router can raise this too.
$home = html_escape(config_item('base_url'));
?><!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>찾을 수 없는 페이지 - TripMate</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Do+Hyeon&display=swap">
<style>
	:root { --paper:#F2F6F7; --ink:#14212B; --muted:#5A6A75; --sea:#0A7F74; --on-sea:#fff; --line:#D5DFE3; }
	@media (prefers-color-scheme: dark) { :root { --paper:#0E1519; --ink:#E9F0F2; --muted:#9BAAB4; --sea:#2CBBA9; --on-sea:#06201C; --line:#2B3A44; } }
	* { box-sizing:border-box; }
	body { margin:0; min-height:100vh; display:grid; place-items:center; padding:24px; background:var(--paper); color:var(--ink); font:16px/1.6 'Pretendard Variable',Pretendard,system-ui,'Malgun Gothic',sans-serif; word-break:keep-all; text-align:center; }
	main { max-width:420px; }
	.code { font:72px/1 'Do Hyeon',sans-serif; color:var(--sea); }
	h1 { margin:12px 0 8px; font:28px/1.25 'Do Hyeon',sans-serif; font-weight:400; }
	p { margin:0 0 24px; color:var(--muted); }
	a { display:inline-flex; align-items:center; justify-content:center; min-height:44px; padding:0 24px; border-radius:999px; background:var(--sea); color:var(--on-sea); font-weight:600; text-decoration:none; }
	a:focus-visible { outline:3px solid #3B6DF0; outline-offset:2px; }
</style>
</head>
<body>
	<main>
		<div class="code">404</div>
		<h1>찾을 수 없는 페이지예요</h1>
		<p>주소가 잘못됐거나, 초대받지 않은 모임이에요.<br>친구가 보낸 초대 링크로 들어왔는지 확인해 보세요.</p>
		<a href="<?php echo $home; ?>">내 모임으로 돌아가기</a>
	</main>
</body>
</html>
