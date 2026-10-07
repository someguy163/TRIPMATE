<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// 이 파일을 복사해서 kakao.php 로 이름을 바꾸고 값을 채운다. (kakao.php 는 .gitignore 로 저장소에 올라가지 않는다)
// 카카오 개발자 콘솔: https://developers.kakao.com

// 앱 > 플랫폼 키 > REST API 키
$config['kakao_rest_key'] = '';

// REST API 키 상세의 "클라이언트 시크릿"이 켜져 있으면 그 값을, 꺼져 있으면 비워 둔다
$config['kakao_client_secret'] = '';

// 지도(일정 한눈에 보기)에 쓰는 JavaScript 키: 앱 > 플랫폼 키 > JavaScript 키
// 같은 화면의 "JavaScript SDK 도메인"에 접속 주소(예: http://localhost)를 등록해야 한다. 비워 두면 지도는 숨겨진다.
$config['kakao_js_key'] = '';

// 관리자(모든 모임을 열람만 할 수 있음)로 만들 카카오 회원 번호(숫자). 로그인할 때 자동으로 관리자가 된다.
// 번호는 해당 계정으로 한 번 로그인한 뒤 DB 의 users.kakao_id 에서 확인한다. 예: [1234567890]
$config['admin_kakao_ids'] = [];

// 카카오가 이메일을 내려주는 비즈 앱일 때만 쓸 수 있다(인증된 이메일만 인정). 보통은 비워 둔다.
$config['admin_emails'] = [];
