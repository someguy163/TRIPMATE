<?php
// 무료 호스팅 후보에 "이 파일 하나만" 올려서 TripMate가 돌아갈 수 있는지 확인한다.
// 주소창에서 열면 결과가 나온다. 확인이 끝나면 반드시 서버에서 지울 것.
header('Content-Type: text/html; charset=utf-8');
$rows = [];
$add = function ($name, $ok, $detail = '') use (&$rows) { $rows[] = [$name, $ok, $detail]; };

$add('PHP 7.4 이상', version_compare(PHP_VERSION, '7.4', '>='), PHP_VERSION);
foreach (['curl', 'mysqli', 'mbstring', 'openssl', 'json'] as $ext) $add("확장 $ext", extension_loaded($ext));
$save = session_save_path() ?: sys_get_temp_dir();
$add('세션 저장 폴더 쓰기 가능', is_writable($save), $save);
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
$add('https 로 접속 중', $https, '로그인은 https 에서 하는 것을 권장');
$add('서버 시간대(참고)', true, date_default_timezone_get() . '  ' . date('c'));

// the make-or-break check: can this server reach Kakao? Kakao answers these calls with a JSON error,
// so a JSON body means we got through; an HTML body means a proxy/anti-bot page or a block.
if (function_exists('curl_init')) {
	$calls = [
		'카카오 로그인 서버 (kauth)' => ['https://kauth.kakao.com/oauth/token', true],
		'카카오 회원정보 서버 (kapi)' => ['https://kapi.kakao.com/v2/user/me', false],
		'카카오맵 검색 서버 (dapi)'  => ['https://dapi.kakao.com/v2/local/search/keyword.json?query=a', false],
	];
	foreach ($calls as $label => list($url, $post)) {
		$ch = curl_init($url);
		curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_USERAGENT => 'TripMate-hostcheck', CURLOPT_POST => $post]);
		if ($post) curl_setopt($ch, CURLOPT_POSTFIELDS, '');
		$body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $errno = curl_errno($ch); $err = curl_error($ch);
		$ok = $body !== false && json_decode($body, true) !== null;
		$note = $ok ? "응답 $code (JSON)" : ($err ?: "응답 $code, JSON 이 아님 → 막혔을 가능성: " . substr(strip_tags((string) $body), 0, 60));
		// SSL error (60 = no CA list, 77 = unreadable CA file): try once without checking the certificate, to tell
		// "blocked" from "reachable but this server has no CA list" (the second one is fixable: ship a CA bundle)
		if (!$ok && in_array($errno, [60, 77], true)) {
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			$body2 = curl_exec($ch);
			if ($body2 !== false && json_decode($body2, true) !== null) {
				$note = "연결은 되지만 이 서버에 인증서 목록이 없어요(cURL 오류 $errno). 해결 가능: cacert.pem 을 application/third_party/ 에 두면 돼요";
			}
		}
		curl_close($ch);
		$add("서버에서 $label 로 연결", $ok, $note);
	}
}

// optional: database check (credentials are used for this request only, never stored)
$db = null;
if (isset($_POST['host'])) {
	mysqli_report(MYSQLI_REPORT_OFF);
	$m = @new mysqli($_POST['host'], $_POST['user'], $_POST['pass'], $_POST['name']);
	if ($m->connect_errno) {
		$add('DB 접속', false, $m->connect_error);
	} else {
		$add('DB 접속', true, 'MySQL/MariaDB ' . $m->server_info);
		// TripMate relies on InnoDB foreign keys with ON DELETE CASCADE and utf8mb4 (Korean + emoji)
		$m->query('DROP TABLE IF EXISTS hc_child'); $m->query('DROP TABLE IF EXISTS hc_parent');
		$a = $m->query('CREATE TABLE hc_parent (id INT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
		$b = $a && $m->query('CREATE TABLE hc_child (id INT, t VARCHAR(20), FOREIGN KEY (id) REFERENCES hc_parent(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
		$m->set_charset('utf8mb4');
		$c = $b && $m->query("INSERT INTO hc_parent VALUES (1)") && $m->query("INSERT INTO hc_child VALUES (1, '한글🙂')");
		$row = $c ? $m->query('SELECT t FROM hc_child')->fetch_row() : null;
		$m->query('DELETE FROM hc_parent'); $cascade = $c ? (int) $m->query('SELECT COUNT(*) FROM hc_child')->fetch_row()[0] === 0 : false;
		$m->query('DROP TABLE IF EXISTS hc_child'); $m->query('DROP TABLE IF EXISTS hc_parent');
		$add('InnoDB 테이블 만들기', (bool) $a, $a ? '' : $m->error);
		$add('외래키 + 삭제 연쇄(CASCADE)', $b && $cascade, $b ? ($cascade ? '' : '연쇄 삭제가 안 됨') : $m->error);
		$add('한글·이모지 저장(utf8mb4)', $row && $row[0] === '한글🙂', $row ? $row[0] : '저장 실패');
		$m->close();
	}
}
$bad = array_filter($rows, function ($r) { return !$r[1]; });
?><!doctype html>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>호스팅 점검</title>
<style>
	body { font:16px/1.6 system-ui,'Malgun Gothic',sans-serif; max-width:720px; margin:24px auto; padding:0 16px; }
	table { border-collapse:collapse; width:100%; } td { padding:8px 6px; border-bottom:1px solid #ddd; vertical-align:top; }
	.ok { color:#0a7f74; font-weight:700; } .no { color:#d93b52; font-weight:700; } small { color:#666; word-break:break-all; }
	.sum { padding:12px 16px; border-radius:10px; margin:16px 0; background:#eef; } form input { padding:6px; margin:2px 0; width:100%; box-sizing:border-box; }
</style>
<h1>TripMate 호스팅 점검</h1>
<p class="sum"><?= $bad ? '<b class="no">안 되는 항목이 ' . count($bad) . '개 있어요.</b> 특히 카카오 서버 연결이 ✘ 이면 로그인이 동작하지 않아요.' : '<b class="ok">모두 통과!</b> 이 호스팅에서 돌릴 수 있어요.' ?></p>
<table>
<?php foreach ($rows as $r): ?>
	<tr><td class="<?= $r[1] ? 'ok' : 'no' ?>"><?= $r[1] ? '✔' : '✘' ?></td><td><?= htmlspecialchars($r[0]) ?><br><small><?= htmlspecialchars($r[2]) ?></small></td></tr>
<?php endforeach ?>
</table>
<h2>DB 점검 (선택)</h2>
<p><small>호스팅에서 만든 DB 정보를 넣으면 외래키와 한글 저장을 확인해요. 입력한 값은 저장하지 않아요.</small></p>
<form method="post" autocomplete="off">
	<input name="host" placeholder="DB 호스트 (예: sql123.example.com)" required>
	<input name="user" placeholder="DB 사용자" required>
	<input name="pass" type="password" placeholder="DB 비밀번호">
	<input name="name" placeholder="DB 이름" required>
	<button>DB 확인</button>
</form>
<p><b>확인이 끝나면 이 파일을 서버에서 삭제하세요.</b></p>
