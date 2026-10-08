<?php $this->load->view('header'); ?>

<?php if (!$this->session->userdata('uid')): ?>
	<section class="landing">
		<div>
			<h1>어디 갈지,<br>투표로 정하자</h1>
			<p class="lead">친구들과 여행지 후보를 모으고, 👍로 고르고, 일정까지 한곳에서 같이 짜요.</p>
			<a class="btn btn-kakao" href="<?= site_url('login') ?>">
				<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path fill="#191919" d="M12 3C6.5 3 2 6.6 2 11c0 2.8 1.8 5.3 4.6 6.7l-1 3.6c-.1.3.3.6.6.4l4.2-2.8c.5.1 1.1.1 1.6.1 5.5 0 10-3.6 10-8S17.500 3 12 3z"/></svg>
				카카오로 시작하기
			</a>
			<p class="fine">닉네임과 프로필 사진만 가져와요.</p>
		</div>
		<div aria-hidden="true">
			<p class="demo-cap">이렇게 정해요</p>
			<?php foreach ([['제주도', '푸른 바다와 흑돼지', 4, 80, true], ['부산', '해운대 야경', 2, 40, false], ['강릉', '커피거리', 1, 20, false]] as $d): ?>
				<div class="ticket<?= $d[4] ? ' lead' : '' ?>">
					<div class="ticket-body">
						<h3><?= $d[0] ?><?= $d[4] ? '<span class="badge">1위</span>' : '' ?></h3>
						<p class="addr"><?= $d[1] ?></p>
						<div class="bar"><i style="width:<?= $d[3] ?>%"></i></div>
					</div>
					<div class="ticket-stub"><span class="vote<?= $d[4] ? ' on' : '' ?>">👍<b><?= $d[2] ?></b></span></div>
				</div>
			<?php endforeach ?>
		</div>
	</section>
<?php else: ?>
	<div class="page-head">
		<h1>안녕하세요, <?= html_escape($this->session->userdata('nick')) ?>님</h1>
		<p class="muted">모임을 만들고 친구를 초대한 다음, 그 안에서 함께 계획해요.</p>
	</div>

	<?= form_open('trips/create', ['class' => 'create']) ?>
		<input type="text" name="title" placeholder="모임 이름 (예: 제주도 여행)" maxlength="100" aria-label="모임 이름" required>
		<label class="f">여행 시작<input type="date" name="start_date" required></label>
		<label class="f">여행 끝<input type="date" name="end_date" required></label>
		<label class="f wide">투표 마감일 (선택)<input type="date" name="vote_deadline"></label>
		<button class="btn btn-primary">모임 만들기</button>
	</form>

	<?php // a trip whose last day is over moves to "지난 여행"
	$today = date('Y-m-d'); $coming = $past = [];
	foreach ($trips as $t) { if ($t->end_date && $t->end_date < $today) $past[] = $t; else $coming[] = $t; } ?>
	<h2>내 모임</h2>
	<?php if ($coming): ?>
		<div class="trips">
			<?php foreach ($coming as $t) $this->load->view('trip_card', ['t' => $t]); ?>
		</div>
	<?php elseif (!$past): ?>
		<p class="empty">아직 참여한 모임이 없어요. 위에서 모임을 만들거나, 친구가 보낸 초대 링크를 눌러 보세요.</p>
	<?php else: ?>
		<p class="empty">다가오는 여행이 없어요. 위에서 새 모임을 만들어 보세요.</p>
	<?php endif ?>

	<?php if ($past): ?>
		<h2 style="margin-top:40px">지난 여행 <span class="muted"><?= count($past) ?></span></h2>
		<div class="trips past">
			<?php foreach ($past as $t) $this->load->view('trip_card', ['t' => $t]); ?>
		</div>
	<?php endif ?>

	<?php if (!empty($others)): ?>
		<h2 style="margin-top:40px">전체 모임 <span class="owner">관리자</span></h2>
		<p class="muted" style="margin:-4px 0 12px">내가 속하지 않은 모임이에요. 열어서 볼 수만 있어요.</p>
		<div class="trips">
			<?php foreach ($others as $t) $this->load->view('trip_card', ['t' => $t]); ?>
		</div>
	<?php endif ?>

	<div class="install">
		<p class="muted">📱 <b>앱처럼 쓰기</b> · 아이폰은 Safari 공유 버튼 → "홈 화면에 추가", 안드로이드는 Chrome 메뉴 → "앱 설치"</p>
		<button type="button" class="btn btn-soft" id="installBtn" hidden>앱으로 설치하기</button>
	</div>

	<script>
		// keep the end date from being earlier than the start date
		const f = document.querySelector('.create').elements;
		f.start_date.onchange = e => { f.end_date.min = e.target.value; if (!f.end_date.value || f.end_date.value < e.target.value) f.end_date.value = e.target.value; };
	</script>
<?php endif ?>

<?php $this->load->view('footer'); ?>
