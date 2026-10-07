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
		if (!in_array($this->router->fetch_method(), ['index', 'show', 'join', 'search']) && $this->input->method() !== 'post')
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
			'SELECT p.*, u.nickname, COUNT(v.user_id) AS votes, MAX(v.user_id = ?) AS mine
			 FROM places p JOIN users u ON u.id = p.added_by
			 LEFT JOIN votes v ON v.place_id = p.id
			 WHERE p.trip_id = ? GROUP BY p.id ORDER BY votes DESC, p.id',
			[$this->uid, $id]
		)->result();
		// untimed plans go last within their day
		$plans = $this->db->order_by('day')->order_by('at_time IS NULL', '', FALSE)->order_by('at_time')->order_by('id')
			->get_where('plans', ['trip_id' => $id])->result();
		$me = $this->uid;
		$view_only = !in_array($this->uid, array_column($members, 'id')); // only possible for an admin
		$this->config->load('kakao');
		$js_key = (string) config_item('kakao_js_key');
		$this->load->view('trip', compact('trip', 'members', 'places', 'plans', 'me', 'view_only', 'js_key'));
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
		// their votes go with them so the vote bars keep matching the member count
		$this->db->query('DELETE v FROM votes v JOIN places p ON p.id = v.place_id WHERE p.trip_id = ? AND v.user_id = ?', [$trip_id, $this->uid]);
		$this->db->delete('trip_members', ['trip_id' => $trip_id, 'user_id' => $this->uid]);
		$this->session->set_flashdata('ok', '모임에서 나갔어요.');
		redirect('/');
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
		$trip = $this->db->get_where('trips', ['id' => $trip_id])->row();
		if (!$trip) show_404();
		$this->_login();
		if (!$this->admin)
		{
			$this->_member($trip_id);
			if ($trip->owner_id != $this->uid) show_error('방장만 모임 정보를 고칠 수 있어요.', 403);
		}
		$title = trim((string) $this->input->post('title'));
		$start = $this->_date($this->input->post('start_date'));
		$end   = $this->_date($this->input->post('end_date'));
		$back  = "trip/$trip_id";
		if ($title === '' || !$start || !$end) $this->_fail('모임 이름과 여행 날짜를 모두 입력해 주세요.', $back);
		if ($end < $start) $this->_fail('여행이 끝나는 날은 시작하는 날보다 빠를 수 없어요.', $back);
		$outside = $this->db->where('trip_id', $trip_id)->group_start()->where('day <', $start)->or_where('day >', $end)->group_end()->count_all_results('plans');
		if ($outside) $this->_fail("이미 만든 일정 {$outside}개가 새 여행 기간 밖에 있어요. 일정을 먼저 고치거나 기간을 더 넓게 잡아 주세요.", $back);

		$this->db->update('trips', ['title' => mb_substr($title, 0, 100), 'start_date' => $start, 'end_date' => $end], ['id' => $trip_id]);
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
		// the trip dates are the limit (older trips without dates stay unrestricted)
		if ($trip->start_date && ($day < $trip->start_date || $day > $trip->end_date))
		{
			$this->_fail('여행 기간(' . date('n월 j일', strtotime($trip->start_date)) . ' ~ ' . date('n월 j일', strtotime($trip->end_date)) . ') 안의 날짜만 고를 수 있어요.', $back);
		}
		if ($to <= $from) $this->_fail('종료 시간은 시작 시간보다 늦어야 해요.', $back);

		$place = mb_substr(trim((string) $this->input->post('place')), 0, 100);
		list($lat, $lng) = $place === '' ? [null, null] : $this->_coords($this->input->post('lat'), $this->input->post('lng'));
		return [
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
		$this->_member($plan->trip_id);
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
