<?php
$this->load->view('header', ['title' => $trip->title]);
$n    = count($members);
$max  = $places ? (int) $places[0]->votes : 0;                      // places arrive sorted by votes
$base = $trip->start_date ? strtotime($trip->start_date) : ($plans ? strtotime($plans[0]->day) : 0); // day 1 of the trip
$last = $plans ? end($plans)->day : (string) $trip->start_date;     // pre-fill the next plan with the last day used
if ($trip->start_date && ($last < $trip->start_date || $last > $trip->end_date)) $last = $trip->start_date;
$week = ['일', '월', '화', '수', '목', '금', '토'];
$owner  = $trip->owner_id == $me;
$can_edit_trip = $owner || !empty($is_admin); // the owner (who made the group) or an admin
$chosen = null;
foreach ($places as $p) if ($p->id == $trip->chosen_place_id) $chosen = $p;
$nights = $trip->start_date ? (int) round((strtotime($trip->end_date) - strtotime($trip->start_date)) / 86400) : 0;
$md = function ($d) use ($week) { return date('n월 j일', strtotime($d)) . '(' . $week[date('w', strtotime($d))] . ')'; };
// plans per candidate destination
$destName = []; $destCount = [];
foreach ($places as $p) { $destName[(int) $p->id] = $p->name; $destCount[(int) $p->id] = 0; }
foreach ($plans as $pl) if (isset($destCount[(int) $pl->dest_id])) $destCount[(int) $pl->dest_id]++;
$jf = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE; // for values placed inside <script>
$today  = date('Y-m-d');
$closed = $trip->vote_deadline && $trip->vote_deadline < $today; // the vote is over
$leaders = []; foreach ($places as $p) if ($max > 0 && (int) $p->votes === $max) $leaders[] = $p->name;
$wx = []; // candidates whose position is known: the weather is looked up for them
foreach ($places as $p) if ($p->lat !== null && $p->lng !== null) $wx[(int) $p->id] = [(float) $p->lat, (float) $p->lng];
$inviteUrl = site_url('join/' . $trip->invite_code);
$inviteBtns = function () use ($inviteUrl, $js_key) { // copy the link, or send it through KakaoTalk
	$u = html_escape($inviteUrl);
	echo '<button type="button" class="btn btn-primary" data-copy data-url="' . $u . '">초대 링크 복사</button>';
	if ($js_key !== '') echo '<button type="button" class="btn btn-kakao sm" data-share data-url="' . $u . '">카카오톡으로 보내기</button>';
};
$mapped = []; // plans that have coordinates, in time order; the day number picks the line colour
foreach ($plans as $pl) if ($pl->lat !== null && $pl->lng !== null) $mapped[] = [
	'dest' => isset($destName[(int) $pl->dest_id]) ? (int) $pl->dest_id : 0,
	'day' => $pl->day, 'dn' => (int) round((strtotime($pl->day) - $base) / 86400) + 1, 'done' => (int) $pl->done,
	'start' => $pl->at_time ? substr($pl->at_time, 0, 5) : '', 'end' => $pl->end_time ? substr($pl->end_time, 0, 5) : '',
	'title' => $pl->title, 'place' => (string) $pl->place, 'lat' => (float) $pl->lat, 'lng' => (float) $pl->lng,
];
?>
<div<?= $view_only ? ' class="view-only"' : '' ?>>
<?php if ($view_only): ?><p class="admin-note">관리자 보기예요. 이 모임의 멤버가 아니라서 볼 수만 있어요.</p><?php endif ?>

<section class="trip-head">
	<div>
		<h1><?= html_escape($trip->title) ?></h1>
		<?php if ($trip->start_date): ?>
			<p class="period"><?= $md($trip->start_date) ?>부터 <?= $md($trip->end_date) ?>까지, <?= $nights ? "{$nights}박 " . ($nights + 1) . '일' : '당일치기' ?></p>
		<?php endif ?>
		<button type="button" class="members-btn" id="memberBtn" aria-haspopup="dialog">
			<span class="stack"><?php foreach (array_slice($members, 0, 5) as $m) echo avatar($m->nickname, $m->profile_img) ?></span>
			함께하는 친구 <?= $n ?>명 <span aria-hidden="true">›</span>
		</button>
	</div>
	<?php if ($n > 1 || !$owner): // while the owner is alone, the big invite card below has the buttons ?>
		<div class="invite-btns"><?php $inviteBtns() ?></div>
	<?php endif ?>
</section>

<dialog id="memberDlg" class="dlg" aria-labelledby="memberDlgTitle">
	<div class="dlg-head">
		<h2 id="memberDlgTitle">함께하는 친구 <?= $n ?>명</h2>
		<button type="button" class="x" data-close aria-label="닫기">✕</button>
	</div>
	<ul class="mlist">
		<?php foreach ($members as $m): ?>
			<li>
				<span class="mname"><?= avatar($m->nickname, $m->profile_img) ?><b><?= html_escape($m->nickname) ?></b><?= $m->id == $trip->owner_id ? '<span class="owner">방장</span>' : '' ?><?= $m->id == $me ? '<span class="me-tag">나</span>' : '' ?></span>
				<?php if ($can_edit_trip && $m->id != $trip->owner_id): // the owner or an admin: hand the group over, or send them away ?>
					<div class="macts">
					<?= form_open("trip/$trip->id/owner/$m->id", ['onsubmit' => 'return confirm(' . html_escape(json_encode($m->nickname . '님에게 방장을 넘길까요?', JSON_UNESCAPED_UNICODE)) . ')']) ?><button class="btn btn-soft">방장 넘기기</button></form>
					<?= form_open("trip/$trip->id/kick/$m->id", ['onsubmit' => 'return confirm(' . html_escape(json_encode($m->nickname . '님을 내보낼까요? 투표한 내용이 사라지고, 초대 링크로 다시 들어올 수 있어요.', JSON_UNESCAPED_UNICODE)) . ')']) ?><button class="btn btn-danger sm">내보내기</button></form>
					</div>
				<?php endif ?>
			</li>
		<?php endforeach ?>
	</ul>
	<?php if ($can_edit_trip): // a fresh link: the old one stops working, so a removed friend can't come back with it ?>
		<p class="muted dlg-note">내보낸 친구가 다시 들어오지 못하게 하거나 링크가 퍼졌다면, 새로 만들어요. 이전에 보낸 링크는 못 쓰게 돼요.</p>
		<?= form_open("trip/$trip->id/newlink", ['class' => 'dlg-foot', 'onsubmit' => "return confirm('이전에 보낸 초대 링크는 더 이상 쓸 수 없게 돼요. 새 링크를 만들까요?')"]) ?>
			<button class="btn btn-soft">🔗 초대 링크 새로 만들기</button>
		</form>
	<?php endif ?>
</dialog>

<?php if ($can_edit_trip): ?>
	<button type="button" class="btn btn-quiet" id="editTripBtn">✎ 모임 이름·기간 수정</button>
	<?= form_open("trip/$trip->id/edit", ['id' => 'tripEdit', 'class' => 'trip-edit', 'hidden' => 'hidden']) ?>
		<label class="f wide">모임 이름<input type="text" name="title" value="<?= html_escape($trip->title) ?>" maxlength="100" required></label>
		<label class="f">여행 시작<input type="date" name="start_date" value="<?= html_escape($trip->start_date) ?>" required></label>
		<label class="f">여행 끝<input type="date" name="end_date" value="<?= html_escape($trip->end_date) ?>" required></label>
		<label class="f wide">투표 마감일 (선택, 비우면 마감 없음)<input type="date" name="vote_deadline" value="<?= html_escape($trip->vote_deadline) ?>"></label>
		<p class="muted wide">이미 만든 일정이 새 기간 밖에 있으면 저장되지 않아요. 일정을 먼저 고쳐 주세요.</p>
		<div class="wide edit-actions">
			<button class="btn btn-primary">저장</button>
			<button type="button" class="btn btn-quiet" id="editTripCancel">취소</button>
		</div>
	</form>
<?php endif ?>

<?php if ($n === 1 && $owner): ?>
	<section class="invite-first">
		<h2>먼저 친구를 초대해요</h2>
		<p>링크를 카카오톡으로 보내 보세요. 친구가 들어오면 같이 후보를 고르고 일정을 짤 수 있어요.</p>
		<div class="invite-btns"><?php $inviteBtns() ?></div>
	</section>
<?php endif ?>

<?php if ($chosen): ?>
	<p class="chosen-banner"><span>여행지 확정</span><b><?= html_escape($chosen->name) ?></b></p>
<?php endif ?>

<?php if ($plans): ?>
	<section id="nowcard" class="nowcard" hidden>
		<div>
			<p class="nc-label"></p>
			<p class="nc-title"></p>
			<p class="nc-sub"></p>
		</div>
		<button type="button" class="btn btn-citrus" id="nc-done" hidden>완료했어요</button>
	</section>
<?php endif ?>

<div class="seg" role="tablist">
	<button type="button" role="tab" data-tab="places" aria-selected="true">여행지</button>
	<button type="button" role="tab" data-tab="plans" aria-selected="false">일정</button>
	<button type="button" role="tab" data-tab="items" aria-selected="false">준비물</button>
	<button type="button" role="tab" data-tab="expenses" aria-selected="false">정산</button>
</div>

<div class="cols" data-tab="places">
	<section id="places">
		<h2>어디로 갈까요?</h2>
		<?php if ($trip->vote_deadline): // the vote has an end date: count down, and after it show the winner ?>
			<p class="deadline<?= $closed ? ' over' : '' ?>">
				<?php if (!$closed): $left = (int) round((strtotime($trip->vote_deadline) - strtotime($today)) / 86400); ?>
					🗳️ 투표 마감 <b><?= $md($trip->vote_deadline) ?></b> <?= $left === 0 ? '(오늘까지)' : "(D-$left)" ?>
				<?php elseif ($leaders): ?>
					🗳️ 투표가 마감됐어요. <?= count($leaders) > 1 ? '공동 1위' : '1위' ?> <b><?= html_escape(implode(', ', $leaders)) ?></b> (<?= $max ?>표)<?= $owner && !$chosen ? ' · 아래에서 “여기로 확정”을 눌러 주세요' : '' ?>
				<?php else: ?>
					🗳️ 투표가 마감됐어요. 투표한 사람이 없어요.
				<?php endif ?>
			</p>
		<?php endif ?>
		<form id="search" class="searchbar" role="search">
			<input type="search" id="q" placeholder="장소 검색 (예: 성산일출봉)" aria-label="카카오맵에서 장소 검색" required>
			<button class="btn btn-primary">검색</button>
		</form>
		<ul id="results" class="results"></ul>

		<?php foreach ($places as $p):
			$lead = $max > 0 && (int) $p->votes === $max;
			$pct  = $n ? min(100, (int) round($p->votes / $n * 100)) : 0;
			$isc  = $chosen && $chosen->id == $p->id; ?>
			<article class="ticket<?= $lead ? ' lead' : '' ?><?= $isc ? ' chosen' : '' ?>">
				<div class="ticket-body">
					<h3><?= html_escape($p->name) ?><?= $isc ? '<span class="badge fixed">확정</span>' : ($lead ? '<span class="badge">1위</span>' : '') ?></h3>
					<?php if ($p->memo !== ''): ?><p class="addr"><?= html_escape($p->memo) ?></p><?php endif ?>
					<p class="by who"><?= avatar($p->nickname, $p->profile_img) ?>추가한 친구 <b><?= html_escape($p->nickname) ?></b>
						<?php if ($p->url): ?><a href="<?= html_escape($p->url) ?>" target="_blank" rel="noopener">카카오맵에서 보기</a><?php endif ?>
					</p>
					<div class="bar" role="img" aria-label="<?= $n ?>명 중 <?= (int) $p->votes ?>명 선택"><i style="width:<?= $pct ?>%"></i></div>
					<div class="acts">
						<button type="button" class="btn btn-soft" data-plan="<?= html_escape($p->name) ?>" data-dest="<?= (int) $p->id ?>" data-lat="<?= $p->lat ?>" data-lng="<?= $p->lng ?>">일정에 넣기</button>
						<?php if ($owner): ?>
							<?= form_open("place/$p->id/choose") ?><button class="btn btn-soft<?= $isc ? ' on' : '' ?>"><?= $isc ? '확정 취소' : '여기로 확정' ?></button></form>
						<?php endif ?>
						<?php if ($owner || $p->added_by == $me): ?>
							<?= form_open("place/$p->id/delete", ['onsubmit' => "return confirm('" . ($destCount[(int) $p->id] ? "이 후보의 일정 {$destCount[(int) $p->id]}개도 함께 지워져요. " : '') . "이 후보를 삭제할까요?')"]) ?><button class="btn btn-quiet">삭제</button></form>
						<?php endif ?>
					</div>
				</div>
				<?= form_open("place/$p->id/vote", ['class' => 'ticket-stub']) ?>
					<button class="vote<?= $p->mine ? ' on' : '' ?>" aria-pressed="<?= $p->mine ? 'true' : 'false' ?>" aria-label="<?= $closed ? '투표가 마감됐어요' : '이 장소에 투표' ?>"<?= $closed ? ' disabled' : '' ?>>👍<b><?= (int) $p->votes ?></b></button>
				</form>
			</article>
		<?php endforeach ?>
		<?php if (!$places): ?><p class="empty" style="margin-top:12px">아직 후보가 없어요. 위에서 장소를 검색해 첫 후보를 올려 보세요.</p><?php endif ?>

		<details class="manual">
			<summary>검색에 없는 곳은 직접 입력</summary>
			<?= form_open("trip/$trip->id/place", ['id' => 'placeForm']) ?>
				<input type="text" name="name" placeholder="장소 이름 (예: 친구네 집)" maxlength="100" aria-label="장소 이름" required>
				<input type="text" name="memo" placeholder="메모 (선택)" maxlength="255" aria-label="메모">
				<input type="hidden" name="url">
				<input type="hidden" name="lat">
				<input type="hidden" name="lng">
				<button class="btn btn-primary">후보 추가</button>
			</form>
		</details>
	</section>

	<section id="plans">
		<h2>일정</h2>
		<?php if ($places): // pick a destination to see only its plans ?>
			<div class="dests" role="group" aria-label="여행지별로 보기">
				<button type="button" class="dest" data-dest="all" aria-pressed="true">전체<small><?= count($plans) ?></small></button>
				<?php foreach ($places as $p): ?>
					<button type="button" class="dest" data-dest="<?= (int) $p->id ?>" data-name="<?= html_escape($p->name) ?>" aria-pressed="false"><?= html_escape($p->name) ?><?= $chosen && $chosen->id == $p->id ? '<span class="fix">확정</span>' : '' ?><small><?= $destCount[(int) $p->id] ?></small></button>
				<?php endforeach ?>
			</div>
		<?php endif ?>
		<div id="wx" class="wx" hidden></div>
		<?php if ($plans): ?>
			<p class="ics">
				<a class="btn btn-soft" id="icsLink" href="<?= site_url("trip/$trip->id/calendar") ?>" download="tripmate-<?= (int) $trip->id ?>.ics">📅 캘린더에 넣기</a>
				<span class="muted">고른 여행지의 일정을 내 캘린더로 받아요. 30분 전에 알려 줘요.</span>
			</p>
			<?= form_open("trip/$trip->id/talkcal", ['class' => 'tc', 'id' => 'talkcalForm', 'onsubmit' => "return confirm('고른 여행지의 일정을 카카오톡 캘린더와 맞춰요. 이미 들어 있는 일정은 그대로 두고, 같은 일정이 여러 개면 하나만 남기고 지워요. 계속할까요?')"]) ?>
				<input type="hidden" name="dest" value="all">
				<button class="btn btn-kakao sm">💬 카카오톡 캘린더에 넣기</button>
				<span class="muted">이미 넣은 일정은 건너뛰고, 중복은 하나만 남겨요.</span>
			</form>
		<?php endif ?>
		<?php if ($mapped && $js_key): ?>
			<div class="mapbox">
				<div id="maploading" class="maploading" role="status"><span class="spin"></span>지도를 불러오는 중…</div>
				<div id="map" aria-label="일정 장소를 한눈에 보는 지도"></div>
				<div class="legend">
					<?php foreach (array_unique(array_column($mapped, 'dn')) as $d): ?><span class="legend-i line-<?= ($d - 1) % 5 ?>"><span class="day-pill"><?= $d ?>일차</span></span><?php endforeach ?>
				</div>
			</div>
		<?php elseif ($mapped && $owner): ?>
			<p class="empty" style="margin-bottom:16px">지도로 보려면 kakao.php 의 kakao_js_key 에 카카오 JavaScript 키를 넣어 주세요.</p>
		<?php endif ?>
		<?php if (!$plans): ?><p class="empty"><?= $places ? '아직 일정이 없어요. 아래에서 첫 일정을 추가해 보세요.' : '일정은 여행지마다 따로 만들어요. 먼저 ‘여행지 후보’ 탭에서 후보를 추가해 주세요.' ?></p><?php endif ?>
		<?php $day = null; foreach ($plans as $pl):
			if ($pl->day !== $day):
				if ($day !== null) echo '</ol></div>';
				$day = $pl->day;
				$dn  = (int) round((strtotime($day) - $base) / 86400) + 1; ?>
				<div class="route line-<?= ($dn - 1) % 5 ?>" data-day="<?= $day ?>">
					<div class="route-head">
						<span class="day-pill"><?= $dn ?>일차</span>
						<span class="muted"><?= date('n월 j일', strtotime($day)) ?> (<?= $week[date('w', strtotime($day))] ?>)</span>
					</div>
					<ol class="stops">
			<?php endif ?>
				<li class="stop<?= $pl->done ? ' done' : '' ?>" data-start="<?= $pl->day . 'T' . ($pl->at_time ? substr($pl->at_time, 0, 5) : '00:00') ?>" data-end="<?= $pl->end_time ? $pl->day . 'T' . substr($pl->end_time, 0, 5) : '' ?>" data-timed="<?= $pl->at_time ? 1 : 0 ?>" data-done="<?= (int) $pl->done ?>" data-dest="<?= isset($destName[(int) $pl->dest_id]) ? (int) $pl->dest_id : 0 ?>">
					<?= form_open("plan/$pl->id/done", ['class' => 'tick']) ?>
						<button class="dot" aria-pressed="<?= $pl->done ? 'true' : 'false' ?>" aria-label="<?= $pl->done ? '완료 취소' : '완료로 표시' ?>"><i></i></button>
					</form>
					<span class="time"><?php if ($pl->at_time): ?><b><?= substr($pl->at_time, 0, 5) ?></b><?php if ($pl->end_time): ?><small><?= substr($pl->end_time, 0, 5) ?></small><?php endif ?><?php endif ?></span>
					<div class="body">
						<span class="what"><?= html_escape($pl->title) ?></span>
						<?php if ($pl->place): ?><small class="where">📍 <?= html_escape($pl->place) ?></small><?php endif ?>
						<?php if ($pl->lat !== null && $pl->lng !== null): // a placed plan: directions from where the person is now
							$nm = trim(str_replace([',', '/', '#', '?'], ' ', (string) ($pl->place ?: $pl->title))) ?: '목적지'; ?>
							<span class="nav">
								<a class="mini" href="https://map.kakao.com/link/to/<?= rawurlencode($nm) ?>,<?= (float) $pl->lat ?>,<?= (float) $pl->lng ?>" target="_blank" rel="noopener">🧭 길찾기</a>
								<button type="button" class="mini" data-navi data-name="<?= html_escape($nm) ?>" data-lat="<?= (float) $pl->lat ?>" data-lng="<?= (float) $pl->lng ?>" title="카카오내비로 길 안내 시작" hidden>🚗 내비</button>
							</span>
						<?php endif ?>
						<?php if (isset($destName[(int) $pl->dest_id])): ?><small class="tag"><?= html_escape($destName[(int) $pl->dest_id]) ?></small><?php endif ?>
						<small class="who"><?= avatar($pl->author, $pl->author_img) ?>작성 <b><?= html_escape($pl->author) ?></b></small>
						<?php $cl = isset($comments[$pl->id]) ? $comments[$pl->id] : []; // notes on this plan ?>
						<details class="notes" data-plan-id="<?= (int) $pl->id ?>">
							<summary>💬 메모<?= $cl ? ' ' . count($cl) : '' ?></summary>
							<ul class="clist">
								<?php foreach ($cl as $c): ?>
									<li>
										<span><b><?= html_escape($c->nickname) ?></b> <?= html_escape($c->body) ?> <small class="muted"><?= date('n/j H:i', strtotime($c->created_at)) ?></small></span>
										<?php if ($owner || !empty($is_admin) || $c->user_id == $me): ?>
											<?= form_open("comment/$c->id/delete") ?><button class="x" aria-label="메모 삭제">✕</button></form>
										<?php endif ?>
									</li>
								<?php endforeach ?>
							</ul>
							<?= form_open("plan/$pl->id/comment", ['class' => 'cform']) ?>
								<input type="text" name="body" placeholder="메모 남기기" maxlength="300" aria-label="메모" required>
								<button class="btn btn-soft">남기기</button>
							</form>
						</details>
					</div>
					<?php if (!empty($is_admin) || $pl->added_by == $me): // only the writer (or an admin) sees the edit button ?>
						<button type="button" class="pencil" aria-label="일정 수정" data-edit-plan="<?= html_escape(json_encode([
							'id' => (int) $pl->id, 'dest' => isset($destName[(int) $pl->dest_id]) ? (int) $pl->dest_id : 0, 'day' => $pl->day, 'start' => substr((string) $pl->at_time, 0, 5), 'end' => substr((string) $pl->end_time, 0, 5),
							'title' => $pl->title, 'place' => (string) $pl->place, 'lat' => $pl->lat, 'lng' => $pl->lng,
						], JSON_UNESCAPED_UNICODE)) ?>">✎</button>
					<?php endif ?>
					<?php if ($owner || !empty($is_admin) || $pl->added_by == $me): // writer, trip owner or admin ?>
						<?= form_open("plan/$pl->id/delete") ?><button class="x" aria-label="일정 삭제">✕</button></form>
					<?php endif ?>
				</li>
		<?php endforeach; if ($plans) echo '</ol></div>'; ?>
		<p class="empty" id="destEmpty" hidden>이 여행지의 일정이 아직 없어요. 아래에서 추가해 보세요.</p>

		<div<?= $places ? '' : ' hidden' ?>>
		<?= form_open("trip/$trip->id/plan", ['class' => 'plan-form']) ?>
			<p class="edit-note wide" id="editNote" hidden>일정을 수정하는 중이에요</p>
			<?php // the destination follows the chip picked above: shown here, but not changeable (the hidden field carries it) ?>
			<label class="f wide">어느 여행지 일정이에요? (위에서 고른 여행지로 정해져요)
				<select id="destView" disabled>
					<option value="">위에서 여행지를 먼저 골라 주세요</option>
					<?php foreach ($places as $p): ?><option value="<?= (int) $p->id ?>"><?= html_escape($p->name) ?></option><?php endforeach ?>
				</select>
				<input type="hidden" name="dest_id" value="">
			</label>
			<p class="muted wide" id="destHint" hidden>위의 여행지 버튼(전체 옆)을 눌러 어느 여행지의 일정인지 먼저 골라 주세요.</p>
			<label class="f wide">날짜<input type="date" name="day" value="<?= html_escape($last) ?>"<?= $trip->start_date ? ' min="' . $trip->start_date . '" max="' . $trip->end_date . '"' : '' ?> required></label>
			<?php // times are two dropdowns (hour, minute in 5-minute steps): nothing finer can be picked on any device. The hidden field carries "HH:MM".
			$hours = []; for ($h = 0; $h < 24; $h++) $hours[sprintf('%02d', $h)] = ($h < 12 ? '오전 ' : '오후 ') . ($h % 12 ?: 12) . '시';
			foreach (['at_time' => '몇 시부터', 'end_time' => '몇 시까지'] as $tf => $tl): ?>
				<div class="f tsel" data-time="<?= $tf ?>">
					<span><?= $tl ?></span>
					<div class="tsel-row">
						<select aria-label="<?= $tl ?> 시" autocomplete="off" required><option value="">시</option><?php foreach ($hours as $v => $t): ?><option value="<?= $v ?>"><?= $t ?></option><?php endforeach ?></select>
						<select aria-label="<?= $tl ?> 분" autocomplete="off" required><option value="">분</option><?php for ($m = 0; $m < 60; $m += 5): ?><option value="<?= sprintf('%02d', $m) ?>"><?= sprintf('%02d', $m) ?>분</option><?php endfor ?></select>
					</div>
					<input type="hidden" name="<?= $tf ?>" value="">
				</div>
			<?php endforeach ?>
			<p class="hint" id="timehint" role="alert" hidden></p>
			<input type="text" name="title" placeholder="무엇을 할까요? (예: 흑돼지 맛집)" maxlength="150" aria-label="일정 내용" required>
			<div class="f wide">
				<span>장소 (선택)</span>
				<div class="placepick">
					<input type="search" name="place" placeholder="장소 검색 또는 직접 입력" maxlength="100" aria-label="장소">
					<button type="button" class="btn btn-soft" id="planSearch">검색</button>
				</div>
			</div>
			<ul id="planres" class="results wide"></ul>
			<input type="hidden" name="lat">
			<input type="hidden" name="lng">
			<p class="muted wide">검색해서 고르면 위 지도에 표시돼요.</p>
			<button class="btn btn-primary">일정 추가</button>
		</form>
		</div>
	</section>

	<section id="items">
		<h2>준비물</h2>
		<p class="muted lead-in">같이 챙길 것을 적고, 누가 가져갈지 정해요.</p>
		<?= form_open("trip/$trip->id/item", ['class' => 'inline-add']) ?>
			<input type="text" name="name" placeholder="준비물 (예: 보조배터리)" maxlength="100" aria-label="준비물" required>
			<button class="btn btn-primary">추가</button>
		</form>
		<?php if (!$items): ?><p class="empty">아직 준비물이 없어요. 텐트, 충전기, 간식처럼 적어 보세요.</p><?php endif ?>
		<ul class="rows">
			<?php foreach ($items as $it): $mine = $it->taker_id == $me; ?>
				<li class="item<?= $it->done ? ' done' : '' ?>">
					<?= form_open("item/$it->id/done") ?>
						<button class="dot" aria-pressed="<?= $it->done ? 'true' : 'false' ?>" aria-label="<?= $it->done ? '챙김 취소' : '챙겼어요' ?>"><i></i></button>
					</form>
					<span class="what"><?= html_escape($it->name) ?></span>
					<?php if ($it->taker_id && !$mine): ?>
						<span class="tag">🎒 <?= html_escape($it->taker) ?></span>
					<?php else: ?>
						<?= form_open("item/$it->id/take", ['class' => 'take']) ?><button class="btn btn-soft<?= $mine ? ' on' : '' ?>"><?= $mine ? '내가 챙겨요 ✓' : '내가 챙길게요' ?></button></form>
					<?php endif ?>
					<?php if ($owner || !empty($is_admin) || $it->added_by == $me): ?>
						<?= form_open("item/$it->id/delete") ?><button class="x" aria-label="준비물 삭제">✕</button></form>
					<?php endif ?>
				</li>
			<?php endforeach ?>
		</ul>
	</section>

	<section id="expenses">
		<h2>정산</h2>
		<?php $total = array_sum(array_column($expenses, 'amount')); ?>
		<p class="muted lead-in">돈을 낸 사람이 적고, 함께한 사람을 고르면 그 사람들끼리 똑같이 나눠서 계산해요.</p>
		<?= form_open("trip/$trip->id/expense", ['class' => 'exp-form']) ?>
			<input type="text" name="title" placeholder="어디에 썼나요? (예: 점심 식사)" maxlength="100" aria-label="쓴 내용" required>
			<input type="text" name="amount" inputmode="numeric" placeholder="낸 금액 (원)" aria-label="낸 금액" required>
			<fieldset class="who-pick">
				<legend>함께한 사람 (이 금액을 나눠 내요)</legend>
				<?php foreach ($members as $m): ?><label class="pick"><input type="checkbox" name="who[]" value="<?= (int) $m->id ?>" checked><?= html_escape($m->nickname) ?></label><?php endforeach ?>
			</fieldset>
			<button class="btn btn-primary">내가 냈어요</button>
		</form>
		<?php if ($expenses):
			$mine = ['share' => 0]; foreach ($settle['rows'] as $r) if ($r['id'] == $me) $mine = $r; ?>
			<div class="sum">
				<div><small>총 지출</small><b><?= number_format($total) ?>원</b></div>
				<div><small>내가 부담할 금액</small><b><?= number_format($mine['share']) ?>원</b></div>
			</div>
			<h3>이렇게 보내면 끝이에요</h3>
			<?php if ($settle['transfers']): ?>
				<ul class="settle">
					<?php foreach ($settle['transfers'] as $s): ?>
						<li<?= $s['from'] == $me || $s['to'] == $me ? ' class="me"' : '' ?>><b><?= html_escape($s['from_name']) ?></b> → <b><?= html_escape($s['to_name']) ?></b><span><?= number_format($s['amount']) ?>원</span></li>
					<?php endforeach ?>
				</ul>
			<?php else: ?><p class="empty">보낼 돈이 없어요. 모두 낸 만큼 부담했어요 👍</p><?php endif ?>
			<h3>사람별</h3>
			<ul class="rows">
				<?php foreach ($settle['rows'] as $r): ?>
					<li><span class="what"><?= html_escape($r['name']) ?></span><small class="muted">낸 돈 <?= number_format($r['paid']) ?>원 · 부담 <?= number_format($r['share']) ?>원</small></li>
				<?php endforeach ?>
			</ul>
			<h3>쓴 내역</h3>
			<ul class="rows">
				<?php foreach ($expenses as $e): ?>
					<?php $sh = isset($shares[$e->id]) ? $shares[$e->id] : null; // who shares this cost (none stored: everyone) ?>
					<li>
						<span class="who"><?= avatar($e->payer, $e->payer_img) ?></span>
						<span class="what"><?= html_escape($e->title) ?><small class="where"><?= html_escape($e->payer) ?> 결제 · 함께 <?= !$sh || count($sh) >= $n ? '전원' : html_escape(implode(', ', $sh)) ?></small></span>
						<b class="money"><?= number_format($e->amount) ?>원</b>
						<?php if ($owner || !empty($is_admin) || $e->paid_by == $me): ?>
							<?= form_open("expense/$e->id/delete") ?><button class="x" aria-label="내역 삭제">✕</button></form>
						<?php endif ?>
					</li>
				<?php endforeach ?>
			</ul>
		<?php else: ?><p class="empty">아직 적은 내역이 없어요. 쓴 돈을 적으면 누가 누구에게 보낼지 계산해 줘요.</p><?php endif ?>
	</section>
</div>

<div class="danger">
	<?php if (!($owner && $n === 1)): // a lone owner can only delete ?>
		<?= form_open("trip/$trip->id/leave", ['onsubmit' => "return confirm('" . ($owner ? '방장 역할이 다른 친구에게 넘어가요. 모임에서 나갈까요?' : '이 모임에서 나갈까요? 투표한 내용은 사라지고, 초대 링크로 다시 들어올 수 있어요.') . "')"]) ?>
			<button class="btn btn-quiet">모임 나가기</button>
		</form>
	<?php endif ?>
	<?php if ($owner): ?>
		<?= form_open("trip/$trip->id/delete", ['onsubmit' => "return confirm('후보와 일정이 모두 사라져요. 이 모임을 삭제할까요?')"]) ?>
			<button class="btn btn-danger">이 모임 삭제</button>
		</form>
	<?php endif ?>
</div>
</div>

<?php if ($mapped && $js_key): ?><script src="https://dapi.kakao.com/v2/maps/sdk.js?appkey=<?= rawurlencode($js_key) ?>&autoload=false"></script><?php endif ?>
<script>
// every plan that has a place, on one Kakao map: pins numbered in time order, one coloured line per day.
// Only the plans of the selected destination are drawn.
const MAP_PLANS = <?= json_encode($mapped, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
const CHOSEN = <?= $chosen ? (int) $chosen->id : 0 ?>;   // the confirmed destination (0 = none yet)
const TRIP_KEY = 'dest-<?= (int) $trip->id ?>';           // remembers the selected destination across the reloads that saving causes
let kmap = null, kfit = () => {}, kinfo = null, klayers = [], curDest = 'all';
// does the view v ('all' or a destination id) show a plan of destination d? (both as strings)
const showsDest = (v, d) => v === 'all' || d === v;

function renderPins() {
	if (!kmap) return;
	klayers.forEach(l => l.setMap(null)); klayers = [];
	if (kinfo) kinfo.close();
	const list = MAP_PLANS.filter(p => showsDest(curDest, String(p.dest)));
	if (!list.length) return;
	const css = getComputedStyle(document.documentElement), byDay = {}, bounds = new kakao.maps.LatLngBounds();
	const spots = {}; // plans at the same spot share one pin, so a place used twice (airport, hotel) is not hidden
	list.forEach((p, i) => {
		const pos = new kakao.maps.LatLng(p.lat, p.lng), color = css.getPropertyValue('--l' + ((p.dn - 1) % 5)).trim();
		bounds.extend(pos);
		(byDay[p.day] = byDay[p.day] || { color, path: [] }).path.push(pos);
		(spots[p.lat + ',' + p.lng] = spots[p.lat + ',' + p.lng] || { pos, color, items: [] }).items.push({ p, n: i + 1 });
	});
	Object.values(spots).forEach(({ pos, color, items }) => {
		const pin = document.createElement('button');
		pin.type = 'button'; pin.className = 'pin' + (items.every(x => x.p.done) ? ' done' : ''); pin.style.background = color;
		pin.textContent = items.map(x => x.n).join('/');
		pin.setAttribute('aria-label', items.map(x => `${x.n}번, ${x.p.title}`).join(', '));
		pin.onclick = () => {
			const el = document.createElement('div');
			el.className = 'iw';
			items.forEach(({ p, n }) => {
				const t = document.createElement('b'), s = document.createElement('div');
				t.textContent = `${n}. ${p.title}`;
				s.textContent = [p.start && (p.end ? p.start + '~' + p.end : p.start), p.place].filter(Boolean).join('  ');
				el.append(t, s);
			});
			kinfo.close(); kinfo.setContent(el); kinfo.setPosition(pos); kinfo.open(kmap);
		};
		klayers.push(new kakao.maps.CustomOverlay({ map: kmap, position: pos, content: pin, yAnchor: 0.5, zIndex: 3 }));
	});
	Object.values(byDay).forEach(d => { if (d.path.length > 1) klayers.push(new kakao.maps.Polyline({ map: kmap, path: d.path, strokeWeight: 4, strokeColor: d.color, strokeOpacity: 0.85 })); });
	kfit = () => { if (list.length > 1) kmap.setBounds(bounds, 40, 40, 40, 40); else { kmap.setLevel(4); kmap.setCenter(new kakao.maps.LatLng(list[0].lat, list[0].lng)); } };
	kfit();
}

function drawMap() {
	const box = document.getElementById('map'), loading = document.getElementById('maploading');
	const loaded = () => { if (loading) loading.hidden = true; };
	if (!box || !MAP_PLANS.length) return;
	if (typeof kakao === 'undefined' || !kakao.maps) {
		loaded(); box.className = 'map-fail'; box.textContent = '지도를 불러오지 못했어요. JavaScript 키와 사이트 도메인 등록을 확인해 주세요.';
		return;
	}
	kakao.maps.load(() => {
		if (kmap) { kmap.relayout(); kfit(); return; } // the panel was hidden when first drawn (mobile tab)
		kmap = new kakao.maps.Map(box, { center: new kakao.maps.LatLng(MAP_PLANS[0].lat, MAP_PLANS[0].lng), level: 7 });
		kakao.maps.event.addListener(kmap, 'tilesloaded', loaded);
		setTimeout(loaded, 8000); // never leave the loader up if that event does not come
		kinfo = new kakao.maps.InfoWindow({ removable: true, zIndex: 5 });
		renderPins();
	});
}

// mobile: one panel at a time; the tab survives the redirect after saving (#plans)
const cols = document.querySelector('.cols');
function tab(name) {
	cols.dataset.tab = name;
	document.querySelectorAll('.seg button').forEach(b => b.setAttribute('aria-selected', b.dataset.tab === name));
	if (name === 'plans') drawMap();
}
document.querySelectorAll('.seg button').forEach(b => b.onclick = () => { tab(b.dataset.tab); history.replaceState(null, '', '#' + b.dataset.tab); });
const startTab = location.hash.slice(1); // #plans, #items, #expenses: stay on the panel that was just saved
if ([...document.querySelectorAll('.seg button')].some(b => b.dataset.tab === startTab)) { tab(startTab); document.getElementById(startTab).scrollIntoView(); }
if (getComputedStyle(document.getElementById('plans')).display !== 'none') drawMap(); // desktop: both panels are visible

// "지금 할 일": picked from the plan list with the viewer's own clock, refreshed every minute
const stops = [...document.querySelectorAll('.stop')], card = document.getElementById('nowcard');
const pad = n => String(n).padStart(2, '0');
function refreshNow() {
	if (!card) return;
	const now = new Date(), today = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
	const notDone = s => s.dataset.done !== '1', timed = s => s.dataset.timed === '1', day = s => s.dataset.start.slice(0, 10);
	const from = s => new Date(s.dataset.start);
	const to = s => s.dataset.end ? new Date(s.dataset.end) : new Date(from(s).getTime() + 36e5); // plans saved without an end time count as 1 hour
	const span = s => timed(s) ? s.dataset.start.slice(11) + (s.dataset.end ? '~' + s.dataset.end.slice(11) : '') : '';
	stops.forEach(s => s.classList.remove('now', 'late'));
	document.querySelectorAll('.route').forEach(r => r.classList.toggle('today', r.dataset.day === today));

	// once a destination is confirmed the card follows only its plans: plans of the other candidates
	// are alternatives, not commitments, so they must not show up as "late"
	const pool = stops.filter(s => !CHOSEN || s.dataset.dest === String(CHOSEN));
	// now: unchecked plan whose start..end window holds the current time (or an untimed plan of today)
	const live = pool.filter(s => notDone(s) && timed(s) && from(s) <= now && now < to(s)).pop()
		|| pool.find(s => notDone(s) && !timed(s) && day(s) === today);
	// late: unchecked and already over (its window ended, or it belongs to an earlier day)
	const late = pool.filter(s => notDone(s) && s !== live && (timed(s) ? to(s) <= now : day(s) < today));
	const next = pool.find(s => notDone(s) && (timed(s) ? from(s) > now : day(s) > today));
	if (live) live.classList.add('now');
	late.forEach(s => s.classList.add('late'));

	const when = s => {
		const t = from(s), days = Math.round((new Date(day(s)) - new Date(today)) / 864e5);
		if (days === 0 && timed(s)) { const m = Math.round((t - now) / 60000); return `오늘 ${span(s)}, ${m < 60 ? m + '분' : Math.round(m / 60) + '시간'} 뒤 시작`; }
		return `${t.getMonth() + 1}월 ${t.getDate()}일${timed(s) ? ' ' + span(s) : ''}, ${days === 1 ? '내일' : days + '일 뒤'}`;
	};
	let label, s, sub, btn = true;
	if (live) { s = live; label = timed(live) ? '지금 할 일' : '오늘 할 일'; sub = timed(live) ? span(live) : '오늘 안에 하면 돼요'; }
	else if (late.length) { s = late[0]; label = '밀린 일정'; sub = (timed(s) ? `${span(s)} 일정이었어요` : '지난 일정이에요') + (late.length > 1 ? `, 외 ${late.length - 1}개` : ''); }
	else if (next) { s = next; label = '다음 일정'; sub = when(next); btn = false; }
	else { label = '일정 완료'; sub = '계획한 일정을 모두 마쳤어요'; btn = false; }
	card.querySelector('.nc-label').textContent = label;
	card.querySelector('.nc-title').textContent = s ? s.querySelector('.what').textContent : '수고했어요!';
	card.querySelector('.nc-sub').textContent = sub;
	const b = document.getElementById('nc-done');
	b.hidden = !btn;
	b.onclick = () => { b.classList.add('busy'); s.querySelector('.dot').click(); };
	card.hidden = false;
}
refreshNow(); setInterval(refreshNow, 60000);

// the end time can never be earlier than (or equal to) the start time: the field's minimum is one minute after
// the start, and an end that ends up at or before it is cleared with a hint (the server checks this again)
const pf = document.querySelector('.plan-form').elements, hint = document.getElementById('timehint');
function syncTimes(ev) {
	const s = pf.at_time.value;
	if (s) {
		const t = Math.min(+s.slice(0, 2) * 60 + +s.slice(3) + 5, 23 * 60 + 55);
		pf.end_time.min = `${pad(Math.floor(t / 60))}:${pad(t % 60)}`;
	} else pf.end_time.removeAttribute('min');
	const bad = s && pf.end_time.value && pf.end_time.value <= s;
	if (bad) { pf.end_time.value = ''; showTime('end_time'); }
	if (bad) { hint.textContent = `종료 시간은 시작 시간(${s})보다 늦게 골라 주세요.`; hint.hidden = false; }
	// keep the hint while the cleared field is still empty; drop it once a valid end is chosen or the start changes
	else if (pf.end_time.value || !s || (ev && ev.target === pf.at_time)) hint.hidden = true;
}
['input', 'change'].forEach(ev => { pf.at_time.addEventListener(ev, syncTimes); pf.end_time.addEventListener(ev, syncTimes); });
// times are kept in 5-minute steps (KakaoTalk's calendar only takes those): a time picked in between snaps to the nearest one
const snap5 = v => {
	if (!/^\d\d:\d\d/.test(v)) return v;
	const m = Math.min(Math.round((+v.slice(0, 2) * 60 + +v.slice(3, 5)) / 5) * 5, 23 * 60 + 55);
	return `${pad(Math.floor(m / 60))}:${pad(m % 60)}`;
};
// the dropdowns fill the hidden field; code that sets a hidden field itself (editing a plan) calls showTime to update its dropdowns
const timeBox = n => document.querySelector(`[data-time="${n}"]`);
function showTime(n) { const [h, m] = timeBox(n).querySelectorAll('select'), v = pf[n].value; h.value = v.slice(0, 2); m.value = v.slice(3, 5); }
['at_time', 'end_time'].forEach(n => {
	const sel = timeBox(n).querySelectorAll('select');
	sel.forEach(x => x.addEventListener('change', () => {
		pf[n].value = sel[0].value && sel[1].value ? `${sel[0].value}:${sel[1].value}` : '';
		pf[n].dispatchEvent(new Event('change', { bubbles: true }));
	}));
});

// "일정에 넣기": carry a candidate (name + map position) into the plan form so voting leads straight into scheduling
document.querySelectorAll('[data-plan]').forEach(b => b.onclick = () => {
	if (planForm.classList.contains('editing')) cancelPlanEdit.onclick(); // leave edit mode first
	tab('plans');
	const f = document.querySelector('.plan-form'), e = f.elements;
	applyDest(b.dataset.dest); // the plan belongs to the candidate it was made from: show it and lock the form to it
	e.title.value = e.place.value = b.dataset.plan;
	e.lat.value = b.dataset.lat; e.lng.value = b.dataset.lng;
	f.scrollIntoView({ block: 'center', behavior: 'smooth' });
	(e.day.value ? timeBox('at_time').querySelector('select') : e.day).focus({ preventScroll: true });
});

document.querySelectorAll('[data-copy]').forEach(b => b.onclick = async () => {
	const old = b.textContent;
	try { await navigator.clipboard.writeText(b.dataset.url); b.textContent = '복사했어요'; }
	catch (_) { prompt('이 링크를 친구에게 보내세요', b.dataset.url); }
	setTimeout(() => b.textContent = old, 1600);
});

// Kakao Map keyword search, shared by the candidate search and the plan's place field (API text goes in via textContent)
async function kakaoSearch(query, ul, pick, btn) {
	const note = (t, spin) => {
		const x = document.createElement('li'); x.className = 'note muted';
		if (spin) { const s = document.createElement('span'); s.className = 'spin'; x.append(s); }
		x.append(t); ul.append(x);
	};
	ul.textContent = ''; note('카카오맵에서 찾는 중…', true);
	if (btn) btn.classList.add('busy');
	let r;
	try { r = await (await fetch('<?= site_url('trips/search') ?>?q=' + encodeURIComponent(query))).json(); }
	catch (_) { ul.textContent = ''; return note('검색에 실패했어요. 잠시 뒤 다시 시도해 주세요.'); }
	finally { if (btn) btn.classList.remove('busy'); }
	ul.textContent = '';
	if (r.error) return note(r.error);
	if (!r.items.length) return note('검색 결과가 없어요. 다른 이름으로 검색해 보세요.');
	r.items.forEach(p => {
		const b = document.createElement('button'), s = document.createElement('span'), x = document.createElement('li');
		b.type = 'button'; b.className = 'result'; b.textContent = p.name;
		s.className = 'muted'; s.textContent = p.addr; b.append(s);
		b.onclick = () => { ul.textContent = ''; pick(p); };
		x.append(b); ul.append(x);
	});
}

// candidate search: picking a result fills the hidden form and submits it
document.getElementById('search').onsubmit = e => {
	e.preventDefault();
	kakaoSearch(q.value, document.getElementById('results'), p => {
		const f = document.getElementById('placeForm'), x = f.elements;
		x.name.value = p.name; x.memo.value = p.addr; x.url.value = p.url; x.lat.value = p.lat; x.lng.value = p.lng;
		tmLoading.form(f); // form.submit() fires no submit event, so start the loading state by hand
		f.submit();
	}, e.submitter);
};

// plan place: a place picked from the search lands on the map; typing one by hand clears the position (none is known)
const placeBtn = document.getElementById('planSearch');
placeBtn.onclick = () => {
	const v = pf.place.value.trim();
	if (v) kakaoSearch(v, document.getElementById('planres'), p => {
		pf.place.value = p.name; pf.lat.value = p.lat; pf.lng.value = p.lng;
		if (!pf.title.value) pf.title.value = p.name;
	}, placeBtn);
};
pf.place.addEventListener('input', () => { pf.lat.value = pf.lng.value = ''; });
pf.place.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); placeBtn.click(); } });

// edit a plan: it goes into the form at the bottom, and saving updates it instead of adding a new one
const planForm = document.querySelector('.plan-form'), planSubmit = planForm.querySelector('.btn-primary'), addAction = planForm.getAttribute('action');
const cancelPlanEdit = document.createElement('button'), editNote = document.getElementById('editNote');
cancelPlanEdit.type = 'button'; cancelPlanEdit.className = 'btn btn-quiet'; cancelPlanEdit.textContent = '수정 취소'; cancelPlanEdit.hidden = true;
planSubmit.after(cancelPlanEdit);
cancelPlanEdit.onclick = () => {
	planForm.setAttribute('action', addAction); planForm.reset();
	planSubmit.textContent = '일정 추가'; cancelPlanEdit.hidden = true; editNote.hidden = true; planForm.classList.remove('editing');
	syncTimes(); syncDestSelect();
};
document.querySelectorAll('[data-edit-plan]').forEach(b => b.onclick = () => {
	const p = JSON.parse(b.dataset.editPlan);
	tab('plans');
	planForm.setAttribute('action', '<?= site_url('plan') ?>/' + p.id + '/edit');
	pf.day.value = p.day; pf.at_time.value = snap5(p.start); pf.end_time.value = snap5(p.end); showTime('at_time'); showTime('end_time'); pf.title.value = p.title;
	pf.place.value = p.place; pf.lat.value = p.lat === null ? '' : p.lat; pf.lng.value = p.lng === null ? '' : p.lng;
	setDest(p.dest ? String(p.dest) : '');
	planSubmit.textContent = '수정 저장'; cancelPlanEdit.hidden = false; editNote.hidden = false; planForm.classList.add('editing');
	syncTimes();
	planForm.scrollIntoView({ block: 'center', behavior: 'smooth' });
	pf.title.focus({ preventScroll: true });
});

// edit the group's name and trip dates (owner or admin)
const tripEdit = document.getElementById('tripEdit');
if (tripEdit) {
	const te = tripEdit.elements;
	document.getElementById('editTripBtn').onclick = () => { tripEdit.hidden = false; te.title.focus(); };
	document.getElementById('editTripCancel').onclick = () => { tripEdit.reset(); tripEdit.hidden = true; };
	te.start_date.onchange = e => { te.end_date.min = e.target.value; if (!te.end_date.value || te.end_date.value < e.target.value) te.end_date.value = e.target.value; };
	te.end_date.min = te.start_date.value;
}

// pick a destination: the plan list and the map show only its plans
const chips = [...document.querySelectorAll('.dest')], destEmpty = document.getElementById('destEmpty');
const ids = chips.map(c => c.dataset.dest).filter(d => d !== 'all');
const icsLink = document.getElementById('icsLink'), icsBase = icsLink && icsLink.getAttribute('href'), talkForm = document.getElementById('talkcalForm');
const destView = document.getElementById('destView'), destHint = document.getElementById('destHint');
// the plan's destination is never typed in: it follows the chip being viewed (or the candidate "일정에 넣기" came from).
// The visible box is disabled, so the hidden field carries the value; with no destination there is nothing to save yet.
function setDest(v) { pf.dest_id.value = destView.value = v; planSubmit.disabled = !v; destHint.hidden = !!v; }
function syncDestSelect() {
	if (planForm.classList.contains('editing') && pf.dest_id.value) return; // an edited plan keeps its destination
	setDest(curDest !== 'all' ? curDest : (CHOSEN ? String(CHOSEN) : (ids.length === 1 ? ids[0] : '')));
}
function applyDest(v) {
	curDest = v;
	try { sessionStorage.setItem(TRIP_KEY, v); } catch (_) {}
	chips.forEach(c => c.setAttribute('aria-pressed', String(c.dataset.dest === v)));
	stops.forEach(s => { s.hidden = !showsDest(v, s.dataset.dest); });
	document.querySelectorAll('.route').forEach(r => { r.hidden = !r.querySelector('.stop:not([hidden])'); });
	if (destEmpty) {
		destEmpty.hidden = v === 'all' || !!document.querySelector('.stop:not([hidden])');
	}
	syncDestSelect();
	showWeather();
	if (icsLink) icsLink.href = icsBase + '?dest=' + curDest; // the calendar file follows the destination in view ('all' = everything)
	if (talkForm) talkForm.dest.value = curDest;               // so does "카카오톡 캘린더에 넣기"
	renderPins();
}

// weather for the trip's days at the destination in view (Open-Meteo: free, no key, forecasts about 16 days ahead)
const WX = <?= json_encode($wx, $jf) ?>, TRIP_FROM = <?= json_encode((string) $trip->start_date) ?>, TRIP_TO = <?= json_encode((string) $trip->end_date) ?>;
const wxBox = document.getElementById('wx'), wxCache = {}, DOW = ['일', '월', '화', '수', '목', '금', '토'];
let wxToken = 0;
const wxIcon = c => c === 0 ? '☀️' : c <= 2 ? '🌤️' : c === 3 ? '☁️' : c <= 48 ? '🌫️' : c <= 57 ? '🌦️' : c <= 67 ? '🌧️' : c <= 77 ? '🌨️' : c <= 82 ? '🌧️' : c <= 86 ? '🌨️' : '⛈️';
async function showWeather() {
	const token = ++wxToken, id = curDest !== 'all' ? curDest : (CHOSEN ? String(CHOSEN) : ids.find(i => WX[i]));
	wxBox.hidden = true; wxBox.textContent = '';
	if (!TRIP_FROM || !WX[id]) return;                       // no dates, or the place was typed by hand (no position)
	const now = new Date(), today = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`, lim = new Date(now.getTime() + 15 * 864e5);
	const last = `${lim.getFullYear()}-${pad(lim.getMonth() + 1)}-${pad(lim.getDate())}`;
	const from = TRIP_FROM > today ? TRIP_FROM : today, to = TRIP_TO < last ? TRIP_TO : last;
	const title = document.createElement('p'), name = (chips.find(c => c.dataset.dest === id) || {}).dataset?.name || '';
	title.className = 'muted'; title.textContent = `${name} 날씨`;
	if (TRIP_TO < today) return;                              // the trip is over
	if (from > to) { title.textContent += ': 여행 16일 전부터 볼 수 있어요'; wxBox.append(title); wxBox.hidden = false; return; }
	const key = `${id}|${from}|${to}`;
	if (!wxCache[key]) {
		try {
			const u = `https://api.open-meteo.com/v1/forecast?latitude=${WX[id][0]}&longitude=${WX[id][1]}&daily=weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max&timezone=Asia%2FSeoul&start_date=${from}&end_date=${to}`;
			wxCache[key] = (await (await fetch(u)).json()).daily;
		} catch (_) { return; }
	}
	const d = wxCache[key];
	if (token !== wxToken || !d || !d.time) return;           // the person picked another destination meanwhile
	const row = document.createElement('div'); row.className = 'wx-row';
	d.time.forEach((day, i) => {
		const x = document.createElement('div'), dt = new Date(day + 'T00:00'), rain = d.precipitation_probability_max && d.precipitation_probability_max[i];
		x.className = 'wx-day';
		[`${dt.getMonth() + 1}/${dt.getDate()}(${DOW[dt.getDay()]})`, wxIcon(d.weather_code[i]), `${Math.round(d.temperature_2m_max[i])}° / ${Math.round(d.temperature_2m_min[i])}°`, rain >= 30 ? `☔ ${rain}%` : '']
			.forEach((t, k) => { const e = document.createElement(k === 1 ? 'b' : 'span'); e.textContent = t; x.append(e); });
		row.append(x);
	});
	wxBox.append(title, row); wxBox.hidden = false;
}

if (chips.length) {
	chips.forEach(c => c.onclick = () => applyDest(c.dataset.dest));
	let first = CHOSEN ? String(CHOSEN) : 'all';   // a confirmed destination opens on its own plans
	try { const saved = sessionStorage.getItem(TRIP_KEY); if (saved) first = saved; } catch (_) {}
	applyDest(chips.some(c => c.dataset.dest === first) ? first : 'all');
	// after saving a plan the page reloads: stay where the new plan is visible
	planForm.addEventListener('submit', () => {
		const d = pf.dest_id.value;
		if (d && !showsDest(curDest, d)) { try { sessionStorage.setItem(TRIP_KEY, d); } catch (_) {} }
	});
}

// "카카오톡으로 보내기": the Kakao JavaScript SDK opens KakaoTalk's friend picker with the invite link in the message
const KAKAO_KEY = <?= json_encode($js_key, $jf) ?>, ME = <?= json_encode((string) $this->session->userdata('nick'), $jf) ?>, TRIP_NAME = <?= json_encode($trip->title, $jf) ?>;
let kakaoSdk;
const loadKakao = () => kakaoSdk = kakaoSdk || new Promise((ok, fail) => {
	if (window.Kakao) return ok();
	const s = document.createElement('script');
	s.src = 'https://t1.kakaocdn.net/kakao_js_sdk/2.7.2/kakao.min.js'; s.onload = ok; s.onerror = fail;
	document.head.append(s);
});
document.querySelectorAll('[data-share]').forEach(b => b.onclick = async () => {
	b.classList.add('busy');
	try {
		await loadKakao();
		if (!Kakao.isInitialized()) Kakao.init(KAKAO_KEY);
		Kakao.Share.sendDefault({
			objectType: 'text',
			text: `${ME}님이 '${TRIP_NAME}' 여행 모임에 초대했어요! 같이 여행지를 투표로 정하고 일정을 짜요.`,
			link: { mobileWebUrl: b.dataset.url, webUrl: b.dataset.url },
			buttonTitle: '모임 참여하기',
		});
	} catch (_) { prompt('카카오톡 공유를 열지 못했어요. 이 링크를 친구에게 직접 보내 주세요', b.dataset.url); }
	finally { b.classList.remove('busy'); }
});

// "카카오내비": starts turn-by-turn guidance in the KakaoNavi app. Phones only (the web version was shut down), so the button
// stays hidden elsewhere; "길찾기" (Kakao Map in a new tab) works everywhere
const onPhone = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
document.querySelectorAll('[data-navi]').forEach(b => {
	b.hidden = !(onPhone && KAKAO_KEY);
	b.onclick = async () => {
		try {
			await loadKakao();
			if (!Kakao.isInitialized()) Kakao.init(KAKAO_KEY);
			Kakao.Navi.start({ name: b.dataset.name, x: +b.dataset.lng, y: +b.dataset.lat, coordType: 'wgs84' }); // x = longitude, y = latitude
		} catch (_) { alert('카카오내비를 열지 못했어요. "길찾기"를 눌러 카카오맵으로 확인해 보세요.'); }
	};
});

// the member list is a popup (native <dialog>): the button opens it, ✕, Esc or a click outside closes it
const memberDlg = document.getElementById('memberDlg');
document.getElementById('memberBtn').onclick = () => memberDlg.showModal();
memberDlg.querySelector('[data-close]').onclick = () => memberDlg.close();
memberDlg.addEventListener('click', e => { if (e.target === memberDlg) memberDlg.close(); });

// notes on a plan: leaving or deleting one reloads the page, so the plan's notes are reopened afterwards
document.querySelectorAll('.notes').forEach(d => d.addEventListener('submit', () => { try { sessionStorage.setItem('note', d.dataset.planId); } catch (_) {} }));
try {
	const n = sessionStorage.getItem('note');
	sessionStorage.removeItem('note');
	const d = n && [...document.querySelectorAll('.notes')].find(x => x.dataset.planId === n);
	if (d) { d.open = true; d.scrollIntoView({ block: 'center' }); }
} catch (_) {}
</script>

<?php $this->load->view('footer'); ?>
