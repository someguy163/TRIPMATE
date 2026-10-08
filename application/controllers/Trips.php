<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Trips extends CI_Controller {

	private $uid;
	private $admin = false;

	public function __construct()
	{
		parent::__construct();
		$this->uid = $this->session->userdata('uid');
		// checked against the DB on every request so removing is_admin takes effect immediately
		$this->admin = $this->uid && $this->db->get_where('users', ['id' => $this->uid, 'is_admin' => 1])->num_rows() > 0;
		$this->load->vars('is_admin', $this->admin);
		// everything except these pages changes data, so POST only (CSRF token is checked by CI)
		if (!in_array($this->router->fetch_method(), ['index', 'show', 'join', 'search', 'calendar']) && $this->input->method() !== 'post')
		{
			show_404();
		}
	}

	public function index()
	{
		$trips = [];
		if ($this->uid)
		{
			$trips = $this->db->select('t.*,
					(SELECT COUNT(*) FROM trip_members WHERE trip_id = t.id) AS members,
					(SELECT COUNT(*) FROM places WHERE trip_id = t.id) AS places,
					(SELECT name FROM places WHERE id = t.chosen_place_id) AS chosen', FALSE)->from('trips t')
				->join('trip_members m', 'm.trip_id = t.id')
				->where('m.user_id', $this->uid)->order_by('t.id', 'desc')->get()->result();
		}
		// admins also see the groups they are not part of (read only)
		$others = [];
		if ($this->admin)
		{
			$others = $this->db->query(
				'SELECT t.*, u.nickname AS owner,
					(SELECT COUNT(*) FROM trip_members WHERE trip_id = t.id) AS members,
					(SELECT COUNT(*) FROM places WHERE trip_id = t.id) AS places,
					(SELECT name FROM places WHERE id = t.chosen_place_id) AS chosen
				 FROM trips t JOIN users u ON u.id = t.owner_id
				 WHERE t.id NOT IN (SELECT trip_id FROM trip_members WHERE user_id = ?)
				 ORDER BY t.id DESC',
				[$this->uid]
			)->result();
		}
		$this->load->view('home', ['trips' => $trips, 'others' => $others]);
	}

	public function create()
	{
		$this->_login();
		$title = trim((string) $this->input->post('title'));
		$start = $this->_date($this->input->post('start_date'));
		$end   = $this->_date($this->input->post('end_date'));
		if ($title === '' || !$start || !$end) $this->_fail('모임 이름과 여행 날짜를 모두 입력해 주세요.', '/');
		if ($end < $start) $this->_fail('여행이 끝나는 날은 시작하는 날보다 빠를 수 없어요.', '/');

		$this->db->insert('trips', [
			'title'       => mb_substr($title, 0, 100),
			'start_date'  => $start,
			'end_date'    => $end,
			'vote_deadline' => $this->_date($this->input->post('vote_deadline')), // optional
			'invite_code' => bin2hex(random_bytes(6)),
			'owner_id'    => $this->uid,
		]);
		$id = $this->db->insert_id();
		$this->db->insert('trip_members', ['trip_id' => $id, 'user_id' => $this->uid]);
		redirect("trip/$id");
	}

	public function show($id)
	{
		$this->_member($id, TRUE); // admins may look at any group
		$trip = $this->db->get_where('trips', ['id' => $id])->row();
		// owner first, then in the order people joined (ids grow with sign-up, close enough)
		$members = $this->db->select('u.id, u.nickname, u.profile_img')->from('trip_members m')
			->join('users u', 'u.id = m.user_id')->where('m.trip_id', $id)
			->order_by('u.id = ' . (int) $trip->owner_id, 'DESC', FALSE)->order_by('u.id')->get()->result();
		$places = $this->db->query(
			'SELECT p.*, u.nickname, u.profile_img, COUNT(v.user_id) AS votes, MAX(v.user_id = ?) AS mine
			 FROM places p JOIN users u ON u.id = p.added_by
			 LEFT JOIN votes v ON v.place_id = p.id
			 WHERE p.trip_id = ? GROUP BY p.id ORDER BY votes DESC, p.id',
			[$this->uid, $id]
		)->result();
		// untimed plans go last within their day
		$plans = $this->db->select('p.*, u.nickname AS author, u.profile_img AS author_img')->from('plans p')
			->join('users u', 'u.id = p.added_by')->where('p.trip_id', $id)
			->order_by('p.day')->order_by('p.at_time IS NULL', '', FALSE)->order_by('p.at_time')->order_by('p.id')->get()->result();
		$comments = []; // plan id => its notes, oldest first
		foreach ($this->db->select('c.*, u.nickname, u.profile_img')->from('comments c')->join('plans p', 'p.id = c.plan_id')
			->join('users u', 'u.id = c.user_id')->where('p.trip_id', $id)->order_by('c.id')->get()->result() as $c) $comments[$c->plan_id][] = $c;
		$items = $this->db->select('i.*, u.nickname AS taker')->from('items i')->join('users u', 'u.id = i.taker_id', 'left')
			->where('i.trip_id', $id)->order_by('i.done')->order_by('i.id')->get()->result();
		$expenses = $this->db->select('e.*, u.nickname AS payer, u.profile_img AS payer_img')->from('expenses e')->join('users u', 'u.id = e.paid_by')
			->where('e.trip_id', $id)->order_by('e.id', 'desc')->get()->result();
		$shares = []; // expense id => [user id => nickname] of the people who share its cost
		foreach ($this->db->select('s.expense_id, s.user_id, u.nickname')->from('expense_shares s')->join('expenses e', 'e.id = s.expense_id')
			->join('users u', 'u.id = s.user_id')->where('e.trip_id', $id)->get()->result() as $r) $shares[$r->expense_id][$r->user_id] = $r->nickname;
		$settle = $this->_settle($members, $expenses, $shares);
		$me = $this->uid;
		$view_only = !in_array($this->uid, array_column($members, 'id')); // only possible for an admin
		$this->config->load('kakao');
		$js_key = (string) config_item('kakao_js_key');
		$this->load->view('trip', compact('trip', 'members', 'places', 'plans', 'comments', 'items', 'expenses', 'shares', 'settle', 'me', 'view_only', 'js_key'));
	}

	public function join($code)
	{
		$trip = $this->db->get_where('trips', ['invite_code' => $code])->row();
		if (!$trip) show_404();
		$this->_login();
		$this->db->query('INSERT IGNORE INTO trip_members (trip_id, user_id) VALUES (?, ?)', [$trip->id, $this->uid]);
		redirect("trip/$trip->id");
	}

	// Kakao Map keyword search, proxied so the REST key stays server-side
	public function search()
	{
		$this->_login();
		$q = trim((string) $this->input->get('q'));
		$out = ['items' => []];
		if ($q !== '')
		{
			$this->config->load('kakao');
			$this->load->helper('http');
			$res = http_json(
				'https://dapi.kakao.com/v2/local/search/keyword.json?' . http_build_query(['query' => $q, 'size' => 8]),
				null, ['Authorization: KakaoAK ' . config_item('kakao_rest_key')]
			);
			if (!isset($res['documents']))
			{
				$out['error'] = isset($res['message']) ? $res['message'] : '카카오맵 검색에 실패했어요.';
			}
			else foreach ($res['documents'] as $d)
			{
				$out['items'][] = [
					'name' => $d['place_name'],
					'addr' => $d['road_address_name'] ?: $d['address_name'],
					'url'  => $d['place_url'],
					'lat'  => $d['y'], // Kakao: y = latitude, x = longitude
					'lng'  => $d['x'],
				];
			}
		}
		$this->output->set_content_type('application/json')->set_output(json_encode($out));
	}

	public function add_place($trip_id)
	{
		$this->_member($trip_id);
		$name = trim((string) $this->input->post('name'));
		$url  = (string) $this->input->post('url');
		if ($name !== '')
		{
			list($lat, $lng) = $this->_coords($this->input->post('lat'), $this->input->post('lng'));
			$this->db->insert('places', [
				'trip_id'  => $trip_id,
				'name'     => mb_substr($name, 0, 100),
				'memo'     => mb_substr(trim((string) $this->input->post('memo')), 0, 255),
				'lat'      => $lat,
				'lng'      => $lng,
				// only accept Kakao Map links (blocks javascript: etc.)
				'url'      => preg_match('#^https?://place\.map\.kakao\.com/\d+$#', $url) ? $url : null,
				'added_by' => $this->uid,
			]);
		}
		redirect("trip/$trip_id");
	}

	// whoever added a candidate, or the trip owner, can remove it
	public function del_place($place_id)
	{
		$place = $this->db->get_where('places', ['id' => $place_id])->row();
		if (!$place) show_404();
		$this->_member($place->trip_id);
		$trip = $this->db->get_where('trips', ['id' => $place->trip_id])->row();
		if ($place->added_by != $this->uid && $trip->owner_id != $this->uid)
		{
			show_error('추가한 친구나 방장만 지울 수 있어요.', 403);
		}
		$this->db->update('trips', ['chosen_place_id' => null], ['chosen_place_id' => $place_id]);
		$this->db->delete('plans', ['dest_id' => $place_id]); // a plan only exists for its destination
		$this->db->delete('places', ['id' => $place_id]);
		redirect("trip/$place->trip_id");
	}

	// owner picks the final destination; choosing it again clears the choice
	public function choose($place_id)
	{
		$place = $this->db->get_where('places', ['id' => $place_id])->row();
		if (!$place) show_404();
		$trip = $this->_owned($place->trip_id);
		$this->db->update('trips', ['chosen_place_id' => $trip->chosen_place_id == $place_id ? null : $place_id], ['id' => $trip->id]);
		redirect("trip/$trip->id");
	}

	// leave a group. The owner hands it to the member who has been in it longest (lowest id); a lone owner can't leave
	public function leave($trip_id)
	{
		$this->_member($trip_id);
		$trip = $this->db->get_where('trips', ['id' => $trip_id])->row();
		if ($trip->owner_id == $this->uid)
		{
			$next = $this->db->select('user_id')->where('trip_id', $trip_id)->where('user_id !=', $this->uid)
				->order_by('user_id')->limit(1)->get('trip_members')->row();
			if (!$next) $this->_fail('혼자 남은 방장은 나갈 수 없어요. 모임을 삭제해 주세요.', "trip/$trip_id");
			$this->db->update('trips', ['owner_id' => $next->user_id], ['id' => $trip_id]);
		}
		$this->_drop_member($trip_id, $this->uid);
		$this->session->set_flashdata('ok', '모임에서 나갔어요.');
		redirect('/');
	}

	// the owner (or an admin) sends a member away; they can come back with the invite link
	public function kick($trip_id, $user_id)
	{
		$trip = $this->_manage($trip_id);
		if ($user_id == $trip->owner_id) show_error('방장은 내보낼 수 없어요.', 403);
		$m = $this->_member_row($trip_id, $user_id);
		$this->_drop_member($trip_id, $user_id);
		$this->session->set_flashdata('ok', "{$m->nickname}님을 내보냈어요.");
		redirect("trip/$trip_id");
	}

	// hand the group over to another member
	public function transfer($trip_id, $user_id)
	{
		$trip = $this->_manage($trip_id);
		if ($user_id == $trip->owner_id) $this->_fail('이미 방장이에요.', "trip/$trip_id");
		$m = $this->_member_row($trip_id, $user_id);
		$this->db->update('trips', ['owner_id' => $user_id], ['id' => $trip_id]);
		$this->session->set_flashdata('ok', "{$m->nickname}님이 새 방장이에요.");
		redirect("trip/$trip_id");
	}

	// the old invite link stops working: the owner (or an admin) gets a fresh one
	public function new_invite($trip_id)
	{
		$this->_manage($trip_id);
		$this->db->update('trips', ['invite_code' => bin2hex(random_bytes(6))], ['id' => $trip_id]);
		$this->session->set_flashdata('ok', '초대 링크를 새로 만들었어요. 이전에 보낸 링크는 더 이상 쓸 수 없어요.');
		redirect("trip/$trip_id");
	}

	// the plans as a .ics file for the phone's / PC's calendar (each with a reminder 30 minutes before).
	// Which plans: ?dest=ID that destination, ?dest=all everything, nothing given: the confirmed destination (else everything).
	public function calendar($trip_id)
	{
		$this->_member($trip_id, TRUE);
		$trip = $this->db->get_where('trips', ['id' => $trip_id])->row();
		$plans = $this->_calendar_plans($trip, $this->input->get('dest'));

		$seoul = new DateTimeZone('Asia/Seoul'); $utc = new DateTimeZone('UTC'); // times are saved as Korean time; calendars get absolute (UTC) times
		$esc  = function ($t) { return str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'], (string) $t); };
		$when = function ($day, $time) use ($seoul, $utc) { return (new DateTime("$day $time", $seoul))->setTimezone($utc)->format('Ymd\\THis\\Z'); };
		$host = (string) parse_url(base_url(), PHP_URL_HOST);
		$out  = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//TripMate//KO', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'X-WR-CALNAME:' . $esc($trip->title)];
		foreach ($plans as $pl)
		{
			$out = array_merge($out, ['BEGIN:VEVENT', "UID:plan-$pl->id@$host", 'DTSTAMP:' . gmdate('Ymd\\THis\\Z')]);
			if ($pl->at_time)
			{
				$end = $pl->end_time ?: date('H:i:s', strtotime($pl->at_time) + 3600); // no end time: one hour
				array_push($out, 'DTSTART:' . $when($pl->day, $pl->at_time), 'DTEND:' . $when($pl->day, $end));
			}
			else // older plans without a time: an all-day event
			{
				array_push($out, 'DTSTART;VALUE=DATE:' . date('Ymd', strtotime($pl->day)), 'DTEND;VALUE=DATE:' . date('Ymd', strtotime($pl->day . ' +1 day')));
			}
			$out[] = 'SUMMARY:' . $esc($pl->title);
			if ($pl->place) $out[] = 'LOCATION:' . $esc($pl->place);
			if ($pl->lat !== null && $pl->lng !== null) $out[] = 'GEO:' . (float) $pl->lat . ';' . (float) $pl->lng;
			$out[] = 'DESCRIPTION:' . $esc(($pl->dest_name ? $pl->dest_name . ' · ' : '') . $trip->title . ' (작성 ' . $pl->author . ')');
			array_push($out, 'BEGIN:VALARM', 'TRIGGER:-PT30M', 'ACTION:DISPLAY', 'DESCRIPTION:' . $esc($pl->title), 'END:VALARM', 'END:VEVENT');
		}
		$out[] = 'END:VCALENDAR';

		$fold = function ($line) { // lines are at most 75 bytes; a longer one continues on the next line after a space
			$s = '';
			while (strlen($line) > 74)
			{
				$cut = 74;
				while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) $cut--; // never cut inside a Korean character
				$s .= substr($line, 0, $cut) . "\r\n ";
				$line = substr($line, $cut);
			}
			return $s . $line;
		};
		$this->output->set_content_type('text/calendar')
			->set_header('Content-Disposition: attachment; filename="tripmate-' . (int) $trip_id . '.ics"')
			->set_output(implode("\r\n", array_map($fold, $out)) . "\r\n");
	}

	// the plans a calendar export covers: 'all' everything, an id that destination, nothing: the confirmed one (else everything)
	private function _calendar_plans($trip, $want)
	{
		$dest = $want === 'all' ? 0 : ((int) $want ?: (int) $trip->chosen_place_id);
		$this->db->select('p.*, u.nickname AS author, d.name AS dest_name')->from('plans p')->join('users u', 'u.id = p.added_by')
			->join('places d', 'd.id = p.dest_id', 'left')->where('p.trip_id', $trip->id);
		if ($dest) $this->db->where('p.dest_id', $dest);
		return $this->db->order_by('p.day')->order_by('p.at_time')->order_by('p.id')->get()->result();
	}

	// "카카오톡 캘린더에 넣기", step 1: remember which plans to send, then ask Kakao for the extra consent (talk_calendar).
	// It comes back through Auth::callback (the same redirect URI as the login), which creates the events.
	public function talkcal($trip_id)
	{
		$this->_member($trip_id);
		$trip = $this->db->get_where('trips', ['id' => $trip_id])->row();
		$plans = array_filter($this->_calendar_plans($trip, $this->input->post('dest')), function ($p) { return $p->at_time; }); // plans without a time are not sent
		if (!$plans) $this->_fail('카카오톡 캘린더에 넣을 일정이 없어요.', "trip/$trip_id#plans");
		if (count($plans) > 50) $this->_fail('한 번에 50개까지만 넣을 수 있어요. 여행지 버튼으로 나눠서 넣어 주세요.', "trip/$trip_id#plans");
		$this->config->load('kakao');
		$state = bin2hex(random_bytes(16));
		$this->session->set_userdata(['oauth_state' => $state, 'talkcal' => ['trip' => (int) $trip_id, 'plans' => array_map('intval', array_column($plans, 'id'))]]);
		redirect('https://kauth.kakao.com/oauth/authorize?' . http_build_query([
			'client_id'     => config_item('kakao_rest_key'),
			'redirect_uri'  => site_url('auth/callback'),
			'response_type' => 'code',
			'state'         => $state,
			'scope'         => 'talk_calendar',
		]));
	}

	// notes on plans: any member can write one; the writer, the owner or an admin can remove it
	public function add_comment($plan_id)
	{
		$plan = $this->db->get_where('plans', ['id' => $plan_id])->row();
		if (!$plan) show_404();
		$this->_member($plan->trip_id);
		$body = mb_substr(trim((string) $this->input->post('body')), 0, 300);
		if ($body !== '')
		{
			$this->db->insert('comments', ['plan_id' => $plan_id, 'user_id' => $this->uid, 'body' => $body, 'created_at' => date('Y-m-d H:i:s')]);
		}
		redirect("trip/$plan->trip_id#plans");
	}

	public function del_comment($comment_id)
	{
		$c = $this->db->select('c.*, p.trip_id')->from('comments c')->join('plans p', 'p.id = c.plan_id')->where('c.id', $comment_id)->get()->row();
		if (!$c) show_404();
		$this->_may_remove($c->trip_id, $c->user_id);
		$this->db->delete('comments', ['id' => $comment_id]);
		redirect("trip/$c->trip_id#plans");
	}

	// packing list: anyone adds, checks off, or takes an item ("I'll bring it"); taking it again gives it back
	public function add_item($trip_id)
	{
		$this->_member($trip_id);
		$name = mb_substr(trim((string) $this->input->post('name')), 0, 100);
		if ($name !== '') $this->db->insert('items', ['trip_id' => $trip_id, 'name' => $name, 'added_by' => $this->uid]);
		redirect("trip/$trip_id#items");
	}

	public function take_item($item_id)
	{
		$item = $this->db->get_where('items', ['id' => $item_id])->row();
		if (!$item) show_404();
		$this->_member($item->trip_id);
		$back = "trip/$item->trip_id#items";
		if ($item->taker_id && $item->taker_id != $this->uid) $this->_fail('이미 다른 친구가 맡았어요.', $back);
		$this->db->update('items', ['taker_id' => $item->taker_id ? null : $this->uid], ['id' => $item_id]);
		redirect($back);
	}

	public function done_item($item_id)
	{
		$item = $this->db->get_where('items', ['id' => $item_id])->row();
		if (!$item) show_404();
		$this->_member($item->trip_id);
		$this->db->set('done', '1 - done', FALSE)->where('id', $item_id)->update('items');
		redirect("trip/$item->trip_id#items");
	}

	public function del_item($item_id)
	{
		$item = $this->db->get_where('items', ['id' => $item_id])->row();
		if (!$item) show_404();
		$this->_may_remove($item->trip_id, $item->added_by);
		$this->db->delete('items', ['id' => $item_id]);
		redirect("trip/$item->trip_id#items");
	}

	// expenses: whoever paid writes it down; the writer, the owner or an admin can remove it
	public function add_expense($trip_id)
	{
		$this->_member($trip_id);
		$title  = mb_substr(trim((string) $this->input->post('title')), 0, 100);
		$amount = preg_replace('/\D/', '', (string) $this->input->post('amount')); // "12,000" works too
		$back   = "trip/$trip_id#expenses";
		if ($title === '' || $amount === '' || (int) $amount < 1 || strlen($amount) > 9) $this->_fail('쓴 내용과 금액(1원 이상)을 입력해 주세요.', $back);
		// the people who share this cost: only members of this trip count
		$who = array_map('intval', (array) $this->input->post('who'));
		$members = $who ? $this->db->select('user_id')->where('trip_id', $trip_id)->where_in('user_id', $who)->get('trip_members')->result() : [];
		if (!$members) $this->_fail('함께한 사람을 한 명 이상 골라 주세요.', $back);
		$this->db->insert('expenses', ['trip_id' => $trip_id, 'paid_by' => $this->uid, 'title' => $title, 'amount' => (int) $amount]);
		$eid = $this->db->insert_id();
		$this->db->insert_batch('expense_shares', array_map(function ($m) use ($eid) { return ['expense_id' => $eid, 'user_id' => $m->user_id]; }, $members));
		redirect($back);
	}

	public function del_expense($expense_id)
	{
		$e = $this->db->get_where('expenses', ['id' => $expense_id])->row();
		if (!$e) show_404();
		$this->_may_remove($e->trip_id, $e->paid_by);
		$this->db->delete('expenses', ['id' => $expense_id]);
		redirect("trip/$e->trip_id#expenses");
	}

	public function del_trip($trip_id)
	{
		$this->_owned($trip_id);
		$this->db->delete('trips', ['id' => $trip_id]); // members, places, votes, plans follow via FK cascade
		redirect('/');
	}

	// toggle: second click removes the vote
	public function vote($place_id)
	{
		$place = $this->db->get_where('places', ['id' => $place_id])->row();
		if (!$place) show_404();
		$this->_member($place->trip_id);
		$deadline = $this->db->get_where('trips', ['id' => $place->trip_id])->row()->vote_deadline;
		if ($deadline && $deadline < date('Y-m-d')) $this->_fail('투표가 마감됐어요.', "trip/$place->trip_id");

		$key = ['place_id' => $place_id, 'user_id' => $this->uid];
		$this->db->where($key)->delete('votes');
		if (!$this->db->affected_rows()) $this->db->insert('votes', $key);
		redirect("trip/$place->trip_id");
	}

	public function add_plan($trip_id)
	{
		$this->_member($trip_id);
		$trip = $this->db->get_where('trips', ['id' => $trip_id])->row();
		$back = "trip/$trip_id#plans";
		$this->db->insert('plans', $this->_plan_input($trip, $back) + ['trip_id' => $trip_id, 'added_by' => $this->uid]);
		redirect($back);
	}

	// the person who wrote a plan can change it; an admin can change any plan (even in a group they are not in)
	public function edit_plan($plan_id)
	{
		$plan = $this->db->get_where('plans', ['id' => $plan_id])->row();
		if (!$plan) show_404();
		$this->_login();
		if (!$this->admin)
		{
			$this->_member($plan->trip_id);
			if ($plan->added_by != $this->uid) show_error('내가 만든 일정만 고칠 수 있어요.', 403);
		}
		$trip = $this->db->get_where('trips', ['id' => $plan->trip_id])->row();
		$back = "trip/$plan->trip_id#plans";
		$this->db->update('plans', $this->_plan_input($trip, $back), ['id' => $plan_id]);
		$this->session->set_flashdata('ok', '일정을 수정했어요.');
		redirect($back);
	}

	// the group's name and trip dates: the owner or an admin. Plans already made must still fit inside the new dates
	public function edit_trip($trip_id)
	{
		$this->_manage($trip_id);
		$title = trim((string) $this->input->post('title'));
		$start = $this->_date($this->input->post('start_date'));
		$end   = $this->_date($this->input->post('end_date'));
		$back  = "trip/$trip_id";
		if ($title === '' || !$start || !$end) $this->_fail('모임 이름과 여행 날짜를 모두 입력해 주세요.', $back);
		if ($end < $start) $this->_fail('여행이 끝나는 날은 시작하는 날보다 빠를 수 없어요.', $back);
		$outside = $this->db->where('trip_id', $trip_id)->group_start()->where('day <', $start)->or_where('day >', $end)->group_end()->count_all_results('plans');
		if ($outside) $this->_fail("이미 만든 일정 {$outside}개가 새 여행 기간 밖에 있어요. 일정을 먼저 고치거나 기간을 더 넓게 잡아 주세요.", $back);

		$this->db->update('trips', [
			'title' => mb_substr($title, 0, 100), 'start_date' => $start, 'end_date' => $end,
			'vote_deadline' => $this->_date($this->input->post('vote_deadline')),
		], ['id' => $trip_id]);
		$this->session->set_flashdata('ok', '모임 정보를 수정했어요.');
		redirect($back);
	}

	// the validated plan fields from the form (date inside the trip dates, end after start).
	// On bad input it shows the reason and goes back, so callers only ever see good data.
	private function _plan_input($trip, $back)
	{
		$day   = $this->_date($this->input->post('day'));
		$from  = $this->_time($this->input->post('at_time'));
		$to    = $this->_time($this->input->post('end_time'));
		$title = trim((string) $this->input->post('title'));

		if (!$day || !$from || !$to || $title === '') $this->_fail('날짜, 시작·종료 시간, 내용을 모두 입력해 주세요.', $back);
		// whole 5-minute steps only (KakaoTalk's calendar takes nothing finer; the form snaps to them, this guards the rest)
		if ((int) substr($from, 3, 2) % 5 || (int) substr($to, 3, 2) % 5) $this->_fail('시간은 5분 단위(예: 09:05, 10:30)로 입력해 주세요.', $back);
		// the trip dates are the limit (older trips without dates stay unrestricted)
		if ($trip->start_date && ($day < $trip->start_date || $day > $trip->end_date))
		{
			$this->_fail('여행 기간(' . date('n월 j일', strtotime($trip->start_date)) . ' ~ ' . date('n월 j일', strtotime($trip->end_date)) . ') 안의 날짜만 고를 수 있어요.', $back);
		}
		if ($to <= $from) $this->_fail('종료 시간은 시작 시간보다 늦어야 해요.', $back);

		$place = mb_substr(trim((string) $this->input->post('place')), 0, 100);
		list($lat, $lng) = $place === '' ? [null, null] : $this->_coords($this->input->post('lat'), $this->input->post('lng'));

		// every plan belongs to one candidate destination, and it must be a candidate of THIS trip
		$dest = (int) $this->input->post('dest_id');
		if (!$dest || !$this->db->get_where('places', ['id' => $dest, 'trip_id' => $trip->id])->num_rows())
		{
			$this->_fail('어느 여행지의 일정인지 골라 주세요. 후보가 없다면 먼저 여행지 후보를 추가해야 해요.', $back);
		}
		return [
			'dest_id'  => $dest,
			'day'      => $day,
			'at_time'  => $from,
			'end_time' => $to,
			'title'    => mb_substr($title, 0, 150),
			'place'    => $place === '' ? null : $place,
			'lat'      => $lat,
			'lng'      => $lng,
		];
	}

	// check / uncheck a plan as done (shared by the whole group)
	public function done_plan($plan_id)
	{
		$plan = $this->db->get_where('plans', ['id' => $plan_id])->row();
		if (!$plan) show_404();
		$this->_member($plan->trip_id);
		$this->db->set('done', '1 - done', FALSE)->where('id', $plan_id)->update('plans');
		redirect("trip/$plan->trip_id#plans");
	}

	public function del_plan($plan_id)
	{
		$plan = $this->db->get_where('plans', ['id' => $plan_id])->row();
		if (!$plan) show_404();
		$this->_may_remove($plan->trip_id, $plan->added_by);
		$this->db->delete('plans', ['id' => $plan_id]);
		redirect("trip/$plan->trip_id#plans");
	}

	private function _login()
	{
		if (!$this->uid)
		{
			if ($this->input->method() === 'get') $this->session->set_userdata('next', uri_string());
			redirect('login');
		}
	}

	// 'YYYY-MM-DD' that is a real calendar date, else null
	private function _date($s)
	{
		$s = (string) $s;
		$d = DateTime::createFromFormat('!Y-m-d', $s);
		return ($d && $d->format('Y-m-d') === $s) ? $s : null;
	}

	// [lat, lng] when both are real coordinates, else [null, null] (a place typed by hand has none)
	private function _coords($lat, $lng)
	{
		if (is_numeric($lat) && is_numeric($lng) && abs($lat) <= 90 && abs($lng) <= 180)
		{
			return [round((float) $lat, 6), round((float) $lng, 6)];
		}
		return [null, null];
	}

	// 'HH:MM' (24h), else null; same-length strings compare correctly as text
	private function _time($s)
	{
		return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $s) ? $s : null;
	}

	// show a message on the next page and go there
	private function _fail($msg, $to)
	{
		$this->session->set_flashdata('err', $msg);
		redirect($to);
	}

	// member check + owner only; returns the trip row
	private function _owned($trip_id)
	{
		$this->_member($trip_id);
		$trip = $this->db->get_where('trips', ['id' => $trip_id])->row();
		if ($trip->owner_id != $this->uid) show_error('방장만 할 수 있어요.', 403);
		return $trip;
	}

	// the owner or an admin (an admin may be outside the group); returns the trip row
	private function _manage($trip_id)
	{
		$trip = $this->db->get_where('trips', ['id' => $trip_id])->row();
		if (!$trip) show_404();
		$this->_login();
		if (!$this->admin)
		{
			$this->_member($trip_id);
			if ($trip->owner_id != $this->uid) show_error('방장만 할 수 있어요.', 403);
		}
		return $trip;
	}

	// whoever wrote something, the trip owner, or an admin may remove it; other members get 403
	private function _may_remove($trip_id, $author_id)
	{
		$this->_login();
		if ($this->admin) return;
		$this->_member($trip_id);
		if ($author_id != $this->uid && $this->db->get_where('trips', ['id' => $trip_id])->row()->owner_id != $this->uid)
		{
			show_error('쓴 친구나 방장만 지울 수 있어요.', 403);
		}
	}

	// a member of this trip (404 when that user is not in it)
	private function _member_row($trip_id, $user_id)
	{
		$m = $this->db->select('u.id, u.nickname')->from('trip_members m')->join('users u', 'u.id = m.user_id')
			->where('m.trip_id', $trip_id)->where('m.user_id', $user_id)->get()->row();
		if (!$m) show_404();
		return $m;
	}

	// take someone out of a group: their votes go with them so the vote bars keep matching the member count,
	// and the packing items they had taken become free again
	private function _drop_member($trip_id, $user_id)
	{
		$this->db->query('DELETE v FROM votes v JOIN places p ON p.id = v.place_id WHERE p.trip_id = ? AND v.user_id = ?', [$trip_id, $user_id]);
		$this->db->update('items', ['taker_id' => null], ['trip_id' => $trip_id, 'taker_id' => $user_id]);
		$this->db->query('DELETE s FROM expense_shares s JOIN expenses e ON e.id = s.expense_id WHERE e.trip_id = ? AND s.user_id = ?', [$trip_id, $user_id]);
		$this->db->delete('trip_members', ['trip_id' => $trip_id, 'user_id' => $user_id]);
	}

	// what each person paid and has to bear, and who sends how much to whom to even it out.
	// Each expense is split equally between the people ticked for it (an older one without a list: everyone in the group now).
	// Someone who left and paid something is simply paid back.
	// ponytail: whole won, rounded per person; a few won of rounding can be left over
	private function _settle($members, $expenses, $shares)
	{
		$names = $paid = $share = $all = [];
		foreach ($members as $m) { $names[$m->id] = $m->nickname; $all[] = $m->id; }
		foreach ($expenses as $e)
		{
			$names[$e->paid_by] = isset($names[$e->paid_by]) ? $names[$e->paid_by] : $e->payer;
			$who = isset($shares[$e->id]) ? array_keys($shares[$e->id]) : $all;
			foreach ($who as $u) $share[$u] = (isset($share[$u]) ? $share[$u] : 0) + $e->amount / count($who);
			$paid[$e->paid_by] = (isset($paid[$e->paid_by]) ? $paid[$e->paid_by] : 0) + $e->amount;
		}
		$rows = $pay = $get = [];
		foreach ($names as $id => $name)
		{
			$p = isset($paid[$id]) ? (int) $paid[$id] : 0;
			$s = isset($share[$id]) ? (int) round($share[$id]) : 0;
			$rows[] = ['id' => $id, 'name' => $name, 'paid' => $p, 'share' => $s];
			if ($p < $s) $pay[$id] = $s - $p; elseif ($p > $s) $get[$id] = $p - $s;
		}
		arsort($pay); arsort($get);
		$transfers = [];
		foreach ($pay as $from => $owe)
		{
			foreach ($get as $to => $due) // biggest debtor pays the biggest creditor first
			{
				if (!$owe) break;
				if (!$due) continue;
				$x = min($owe, $due);
				$transfers[] = ['from' => $from, 'to' => $to, 'from_name' => $names[$from], 'to_name' => $names[$to], 'amount' => $x];
				$owe -= $x; $get[$to] -= $x;
			}
		}
		return ['rows' => $rows, 'transfers' => $transfers];
	}

	// members only; $admin_ok lets an admin through for read-only pages
	private function _member($trip_id, $admin_ok = FALSE)
	{
		$this->_login();
		if ($admin_ok && $this->admin && $this->db->get_where('trips', ['id' => $trip_id])->num_rows()) return;
		if (!$this->db->get_where('trip_members', ['trip_id' => $trip_id, 'user_id' => $this->uid])->num_rows())
		{
			show_404();
		}
	}
}
