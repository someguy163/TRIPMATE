# TripMate

친구들과 **여행지를 투표로 정하고, 일정을 같이 짜는** 웹 앱입니다. 로그인은 카카오톡으로 합니다.

모임을 만들고 → 친구를 초대하고 → 그 안에서 후보를 올려 투표하고 → 일정을 만들어 지도에서 한눈에 봅니다.

## 주요 기능

- **카카오 로그인**: 닉네임과 프로필 사진을 가져와 오른쪽 위에 표시
- **모임**: 여행 기간(시작일~종료일)을 정해 만들기, 초대 링크로 친구 초대, 참여 인원 목록, 모임 나가기(방장이 나가면 다음 사람에게 자동 위임)
- **여행지 후보**: 카카오맵 검색으로 추가, 👍 투표, 방장이 "여기로 확정"
- **일정**: 여행 기간 안의 날짜만 가능, 시작~종료 시간 입력, 장소 검색, 완료 체크
- **지금 할 일**: 현재 시각 기준으로 "지금 할 일 / 다음 일정 / 밀린 일정" 안내
- **일정 지도**: 장소가 있는 일정을 카카오 지도에 번호 핀과 일차별 색 경로로 표시
- **수정**: 일정은 **작성한 본인**만, 모임 이름·여행 기간은 **방장**만 고칠 수 있음. 관리자는 모든 일정과 모임을 고칠 수 있음
- **접근 제어**: 초대받은 모임만 볼 수 있음(주소를 바꿔도 404). 관리자는 속하지 않은 모임도 열람하고 수정할 수 있음(투표·추가·삭제는 불가)
- 모바일/PC 반응형, 다크 모드

## 기술 스택

PHP 8.x · [CodeIgniter 3.1](https://codeigniter.com) · MySQL / MariaDB · Kakao 로그인 / 로컬(장소 검색) / 지도 API

## 로컬에서 실행하기 (XAMPP)

1. 이 저장소를 `C:\xampp\htdocs\TRIPMATE` 에 내려받습니다.
2. XAMPP 에서 **Apache** 와 **MySQL** 을 시작합니다.
3. DB 를 만들고 구조를 가져옵니다.
   ```bash
   mysql -u root -e "CREATE DATABASE IF NOT EXISTS tripmate CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   ```
   ```bash
   mysql -u root tripmate < tripmate.sql
   ```
   (phpMyAdmin 에서는 `tripmate` DB 를 만들고, 그 DB 를 연 뒤 **가져오기**로 `tripmate.sql` 을 올립니다.)
4. 설정 파일을 만듭니다. 양식 파일을 복사해서 이름을 바꾸고 값을 채웁니다.
   - `application/config/kakao.example.php` → `application/config/kakao.php`
   - `application/config/database.example.php` → `application/config/database.php`
5. 브라우저에서 `http://localhost/TRIPMATE/` 로 접속합니다.

> `kakao.php` 와 `database.php` 에는 비밀 키와 비밀번호가 들어가므로 `.gitignore` 로 저장소에서 제외되어 있습니다. 올리지 마세요.

## 카카오 설정 (키는 어디서 확인하나요?)

[카카오 개발자 콘솔](https://developers.kakao.com) 에 로그인해 **앱 만들기**로 앱을 하나 만든 뒤, 만든 앱을 선택하면 왼쪽에 메뉴가 나옵니다.
(콘솔 화면은 개편될 수 있어 메뉴 이름이 조금 다를 수 있어요. 비슷한 이름을 찾아보세요.)

### 1. 키 값 확인해서 `kakao.php` 에 넣기

`application/config/kakao.php` 를 열고 아래 값을 채웁니다. **키는 이 파일에만 넣고, 저장소나 채팅에 올리지 마세요.**

| 필요한 값 | 확인하는 곳 | `kakao.php` 항목 |
|---|---|---|
| **REST API 키** | 왼쪽 **앱 → 플랫폼 키 → `REST API 키`** 를 눌러 상세 화면에서 키 값 복사 | `kakao_rest_key` |
| **클라이언트 시크릿** | 위와 같은 **REST API 키 상세 화면 아래쪽 "클라이언트 시크릿"**. 켜져 있으면 그 값을 넣고, 꺼져 있으면 비워 둡니다 | `kakao_client_secret` |
| **JavaScript 키** | 왼쪽 **앱 → 플랫폼 키 → `JavaScript 키`** 를 눌러 상세 화면에서 키 값 복사 | `kakao_js_key` |
| **카카오 회원 번호** (관리자용) | 아래 [관리자 지정](#관리자-지정) 참고 | `admin_kakao_ids` |

### 2. 콘솔에서 켜고 등록할 것

| 설정 | 경로 | 값 |
|---|---|---|
| 카카오 로그인 사용 | 왼쪽 **제품 설정 → 카카오 로그인 → 일반** 의 사용 설정 | **ON** |
| 동의항목 | **제품 설정 → 카카오 로그인 → 동의항목** | **닉네임**, **프로필 사진** 을 **필수 동의** 로 |
| 로그인 리다이렉트 URI | **앱 → 플랫폼 키 → REST API 키** 상세의 **카카오 로그인 리다이렉트 URI** 칸에 입력하고 **`+`**, 화면 맨 아래 **저장** | `http://localhost/TRIPMATE/auth/callback` |
| 카카오맵 (장소 검색) | 왼쪽 **제품 설정 → 카카오맵** | **ON** |
| JavaScript SDK 도메인 (지도) | **앱 → 플랫폼 키 → JavaScript 키** 상세의 **JavaScript SDK 도메인** 칸에 입력하고 **`+`**, **저장** | `http://localhost` |
| 호출 허용 IP 주소 | REST API 키 상세 | **비워 둡니다** (넣으면 그 IP 밖에서는 호출이 막힘) |

- 주소는 `http://` 부터 한 글자도 다르지 않게, 끝에 `/` 없이 입력합니다.
- 배포하면 같은 곳에 **배포 주소도 추가**합니다. 예: `https://내주소/auth/callback`, `https://내주소`
- 접속은 등록한 주소와 같게 `localhost` 로 하세요. `127.0.0.1` 로 열면 지도가 안 뜹니다.

### 3. 자주 나는 문제

| 증상 | 원인 / 해결 |
|---|---|
| 로그인 때 "앱 관리자 설정 오류" | `kakao_rest_key` 가 비었거나 틀렸어요. JavaScript 키가 아니라 **REST API 키**를 넣었는지 확인 |
| 오류 코드 `KOE004` | **제품 설정 → 카카오 로그인** 이 OFF 예요 |
| 오류 코드 `KOE010` / "Bad client credentials" | 콘솔에서 클라이언트 시크릿이 켜져 있는데 `kakao_client_secret` 가 비어 있어요 |
| 로그인 후 Redirect URI 관련 오류 | 위 2번의 리다이렉트 URI 가 등록되지 않았거나 주소가 달라요 |
| 내 이름이 "친구"로 나옴 | 동의항목의 **닉네임**이 필수 동의가 아니에요. 바꾼 뒤 로그아웃/재로그인 |
| 장소 검색에 `disabled OPEN_MAP_AND_LOCAL` 메시지 | **제품 설정 → 카카오맵** 이 OFF 예요 |
| 지도가 안 뜸 | `kakao_js_key` 가 비었거나, JavaScript SDK 도메인에 접속 주소가 없거나, `localhost` 가 아닌 주소로 열었어요 |

### 관리자 지정

`kakao.php` 의 `admin_kakao_ids` 에 관리자로 만들 **카카오 회원 번호**(숫자)를 적습니다. 해당 계정으로 로그인하면 자동으로 관리자가 되고, 모든 모임을 열람하고 일정과 모임 정보를 수정할 수 있습니다(투표, 추가, 삭제는 모임에 속해 있어야 합니다).

회원 번호는 그 계정으로 **한 번 로그인한 뒤** DB 에서 확인합니다.

```bash
mysql -u root tripmate -e "SELECT id, nickname, kakao_id FROM users"
```

`kakao_id` 열의 숫자가 회원 번호입니다. 예: `$config['admin_kakao_ids'] = [1234567890];`
(phpMyAdmin 에서는 `tripmate` DB 의 `users` 표를 열면 보입니다.)

### DB 접속 정보

`application/config/database.php` 의 `hostname` / `username` / `password` / `database` 를 채웁니다.
XAMPP 기본값은 `localhost` / `root` / (비밀번호 없음) / `tripmate` 입니다. 호스팅에서는 그 서비스의 **MySQL 데이터베이스 관리 화면**(cPanel 등)에서 만든 DB 이름, 사용자, 비밀번호, 호스트를 넣습니다.

## 폴더 구조

```
application/
  controllers/   Auth.php (카카오 로그인), Trips.php (모임·후보·일정)
  views/         화면 (home, trip, header ...) + errors/ (404 등 오류 화면)
  config/        routes.php (주소 규칙), kakao.php / database.php (비공개)
  helpers/       http_helper.php (카카오 호출), ui_helper.php (아바타)
assets/           app.css (스타일), app.js (로딩 표시)
tripmate.sql     DB 구조 (사용자 데이터 없음)
deploy/hostcheck.php   무료 호스팅이 이 앱을 돌릴 수 있는지 점검하는 도구
system/          CodeIgniter 본체
```

## 배포할 때

사이트 주소 설정은 따로 고칠 필요가 없습니다. 접속한 주소를 따라가고, 폴더 이름(`/TRIPMATE/`)도 자동으로 맞춥니다. 이 컴퓨터(localhost)에서는 오류를 그대로 보여 주는 **개발 모드**, 그 밖의 주소(배포 서버)에서는 오류를 숨기는 **운영 모드**로 자동 전환됩니다. 서버 시간대와 상관없이 날짜는 한국 시간(`Asia/Seoul`)으로 계산합니다.

1. **호스팅 점검**: 후보 호스팅에 `deploy/hostcheck.php` 하나만 올려서 열어 봅니다. **서버에서 카카오로 연결**되는 줄이 모두 ✔ 이어야 합니다. 점검이 끝나면 지웁니다.
2. **DB**: 호스팅 컨트롤 패널에서 MySQL DB 를 만들고, phpMyAdmin 에서 그 DB 를 연 뒤 `tripmate.sql` 을 **가져오기**합니다. (DB 이름, 사용자, 비밀번호, 호스트를 메모해 둡니다)
3. **파일 올리기**: 이 저장소는 `main` 에 푸시하면 GitHub Actions(`.github/workflows/deploy.yml`)가 FTP 로 자동 배포합니다. 수동으로는 `index.php`, `.htaccess`, `application/`, `assets/`, `system/` 을 웹 폴더(`htdocs`)에 올립니다. `tripmate.sql`, `deploy/`, `.git` 은 올리지 않습니다. 배포에 필요한 비밀 값(`FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`)은 GitHub 저장소의 Settings > Secrets and variables > Actions 에 등록합니다. **DB 표 구조가 바뀌면 자동 반영되지 않으니** 서버 phpMyAdmin 에서 변경 SQL 을 직접 실행합니다.
4. **설정 파일 만들기** (서버에서 직접, 양식 파일 이름을 바꾸고 값을 채웁니다)
   - `application/config/database.example.php` → `database.php` : 2번에서 메모한 DB 정보
   - `application/config/kakao.example.php` → `kakao.php` : 카카오 키들과 관리자 번호
   - (선택, 권장) `application/config/site.example.php` → `site.php` : 실제 사이트 주소를 고정
5. **카카오 콘솔에 배포 주소 추가**: 로그인 리다이렉트 URI `https://내주소/auth/callback`, JavaScript SDK 도메인 `https://내주소`
6. 접속해서 로그인까지 확인합니다.

> 키와 비밀번호가 들어간 `kakao.php`, `database.php`, `site.php` 는 `.gitignore` 로 제외되어 있습니다. 저장소에 올리지 마세요.

## 라이선스

CodeIgniter 는 MIT 라이선스입니다 (`license.txt`).
