<?php $this->load->helper('ui'); ?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#F2F6F7" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0E1519" media="(prefers-color-scheme: dark)">
<title><?= isset($title) ? html_escape($title) . ' - ' : '' ?>TripMate</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='9' fill='%230A7F74'/%3E%3Cpath d='M7 21c4-9 9-9 12-4s5 2 6-6' fill='none' stroke='white' stroke-width='2.5' stroke-linecap='round'/%3E%3Ccircle cx='25' cy='11' r='2.5' fill='%23FFB02E'/%3E%3C/svg%3E">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Do+Hyeon&display=swap">
<link rel="stylesheet" href="<?= base_url('assets/app.css') ?>?v=<?= filemtime(FCPATH . 'assets/app.css') ?>">
<script src="<?= base_url('assets/app.js') ?>?v=<?= filemtime(FCPATH . 'assets/app.js') ?>" defer></script>
<noscript><style>#topbar { display:none; }</style></noscript><!-- without JS nothing would ever finish the bar -->
</head>
<body>
<div id="topbar" aria-hidden="true"></div>
<script>/* start the bar right after the first paint so it grows from 0 (a class present at first render would not animate);
   the timer is for tabs that do not paint (hidden / background). app.js finishes the bar on window load */
(function () { var b = document.getElementById('topbar'), go = function () { if (document.readyState !== 'complete') b.classList.add('run'); }; requestAnimationFrame(go); setTimeout(go, 60); })();</script>
<div id="pageloader" class="pageloader" role="status" hidden><span class="spin"></span><span>불러오는 중…</span></div>
<header class="top">
	<div class="wrap">
		<a class="logo" href="<?= site_url('/') ?>">
			<svg viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="9" fill="#0A7F74"/><path d="M7 21c4-9 9-9 12-4s5 2 6-6" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/><circle cx="25" cy="11" r="2.5" fill="#FFB02E"/></svg>
			TripMate
		</a>
		<?php if ($this->session->userdata('uid')): ?>
			<?= form_open('logout', ['class' => 'me']) ?>
				<?= avatar($this->session->userdata('nick'), $this->session->userdata('img')) ?>
				<span class="name"><?= html_escape($this->session->userdata('nick')) ?></span>
				<?php if (!empty($is_admin)): ?><span class="owner">관리자</span><?php endif ?>
				<button class="btn btn-quiet">로그아웃</button>
			</form>
		<?php endif ?>
	</div>
</header>
<main class="wrap">
<?php if ($ok = $this->session->flashdata('ok')): ?><p class="notice" role="status"><?= html_escape($ok) ?></p><?php endif ?>
<?php if ($err = $this->session->flashdata('err')): ?><p class="alert" role="alert"><?= html_escape($err) ?></p><?php endif ?>
