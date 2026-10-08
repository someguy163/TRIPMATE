-- 먼저 DB 를 만들고, 그 DB 를 선택한 상태에서 이 파일을 실행한다. (DB 를 만드는 줄은 호스팅에서 막혀 있어 넣지 않았다)
--   로컬(XAMPP)   : mysql -u root -e "CREATE DATABASE IF NOT EXISTS tripmate CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
--                   mysql -u root tripmate < tripmate.sql
--   호스팅        : 컨트롤 패널에서 MySQL DB 를 만들고, phpMyAdmin 에서 그 DB 를 연 뒤 "가져오기"로 이 파일을 올린다

CREATE TABLE IF NOT EXISTS users (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kakao_id    BIGINT UNSIGNED NOT NULL UNIQUE,
  nickname    VARCHAR(100) NOT NULL,
  profile_img VARCHAR(500) NULL,
  is_admin    TINYINT(1) NOT NULL DEFAULT 0
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS trips (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(100) NOT NULL,
  start_date  DATE NULL,            -- 여행 기간: 이 안에서만 일정을 만들 수 있다
  end_date    DATE NULL,
  vote_deadline DATE NULL,          -- 이 날이 지나면 투표가 마감된다 (비우면 마감 없음)
  invite_code CHAR(12) NOT NULL UNIQUE,
  owner_id    INT UNSIGNED NOT NULL,
  chosen_place_id INT UNSIGNED NULL, -- 방장이 확정한 여행지
  FOREIGN KEY (owner_id) REFERENCES users(id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS trip_members (
  trip_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (trip_id, user_id),
  FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 여행지 후보
CREATE TABLE IF NOT EXISTS places (
  id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  trip_id  INT UNSIGNED NOT NULL,
  name     VARCHAR(100) NOT NULL,
  memo     VARCHAR(255) NOT NULL DEFAULT '',
  url      VARCHAR(255) NULL,
  lat      DECIMAL(9,6) NULL,             -- 카카오맵 검색으로 고른 장소의 좌표
  lng      DECIMAL(9,6) NULL,
  added_by INT UNSIGNED NOT NULL,
  FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
  FOREIGN KEY (added_by) REFERENCES users(id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS votes (
  place_id INT UNSIGNED NOT NULL,
  user_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (place_id, user_id),
  FOREIGN KEY (place_id) REFERENCES places(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 일정
CREATE TABLE IF NOT EXISTS plans (
  id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  trip_id  INT UNSIGNED NOT NULL,
  dest_id  INT UNSIGNED NULL,      -- 어느 여행지 후보의 일정인가 (앱이 항상 채운다. 후보를 지우면 그 일정도 함께 지운다)
  day      DATE NOT NULL,
  at_time  TIME NULL,              -- 시작 시간
  end_time TIME NULL,              -- 종료 시간
  title    VARCHAR(150) NOT NULL,
  place    VARCHAR(100) NULL,             -- 일정 장소 (lat/lng 가 있으면 지도에 표시)
  lat      DECIMAL(9,6) NULL,
  lng      DECIMAL(9,6) NULL,
  done     TINYINT(1) NOT NULL DEFAULT 0,
  added_by INT UNSIGNED NOT NULL,
  FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
  FOREIGN KEY (dest_id) REFERENCES places(id) ON DELETE SET NULL,
  FOREIGN KEY (added_by) REFERENCES users(id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 일정 메모(댓글)
CREATE TABLE IF NOT EXISTS comments (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plan_id    INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  body       VARCHAR(300) NOT NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 준비물 체크리스트 (taker_id = 챙기기로 한 친구)
CREATE TABLE IF NOT EXISTS items (
  id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  trip_id  INT UNSIGNED NOT NULL,
  name     VARCHAR(100) NOT NULL,
  taker_id INT UNSIGNED NULL,
  done     TINYINT(1) NOT NULL DEFAULT 0,
  added_by INT UNSIGNED NOT NULL,
  FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
  FOREIGN KEY (taker_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (added_by) REFERENCES users(id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 경비 (낸 사람 paid_by 가 적은 금액, 원 단위)
CREATE TABLE IF NOT EXISTS expenses (
  id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  trip_id INT UNSIGNED NOT NULL,
  paid_by INT UNSIGNED NOT NULL,
  title   VARCHAR(100) NOT NULL,
  amount  INT UNSIGNED NOT NULL,
  FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
  FOREIGN KEY (paid_by) REFERENCES users(id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
