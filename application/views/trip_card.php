<?php // one group in a list; $t comes with members/places/chosen counts, and `owner` for the admin list ?>
<a class="trip c<?= $t->id % 5 ?>" href="<?= site_url("trip/$t->id") ?>">
	<span class="ico"><?= ['🏝️', '🏔️', '🌆', '🚗', '🍜'][$t->id % 5] ?></span>
	<span>
		<b><?= html_escape($t->title) ?></b>
		<?php if ($t->start_date): ?><span class="muted"><?= date('n월 j일', strtotime($t->start_date)) ?>부터 <?= date('n월 j일', strtotime($t->end_date)) ?>까지</span><?php endif ?>
		<span class="muted">친구 <?= (int) $t->members ?>명, 후보 <?= (int) $t->places ?>곳<?= isset($t->owner) ? ', 방장 ' . html_escape($t->owner) : '' ?></span>
		<?php if ($t->chosen): ?><span class="muted">확정 <?= html_escape($t->chosen) ?></span><?php endif ?>
	</span>
</a>
