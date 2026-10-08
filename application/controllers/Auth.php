<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Auth extends CI_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->config->load('kakao');
		$this->load->helper('http');
		$this->load->helper('talkcal');
	}

	public function login()
	{
		$state = bin2hex(random_bytes(16));
		$this->session->set_userdata('oauth_state', $state);
		$this->session->unset_userdata('talkcal'); // a calendar job that was never finished must not turn this login into one
		redirect('https://kauth.kakao.com/oauth/authorize?' . http_build_query([
			'client_id'     => config_item('kakao_rest_key'),
			'redirect_uri'  => site_url('auth/callback'),
			'response_type' => 'code',
			'state'         => $state,
		]));
	}

	public function callback()
	{
		$state = (string) $this->session->userdata('oauth_state');
		$this->session->unset_userdata('oauth_state');
		$code = $this->input->get('code');
		if ($state === '' || !$code || !hash_equals($state, (string) $this->input->get('state')))
		{
			show_error('로그인에 실패했어요. 다시 시도해주세요.', 400);
		}

		$token = http_json('https://kauth.kakao.com/oauth/token', array_filter([
			'grant_type'    => 'authorization_code',
			'client_id'     => config_item('kakao_rest_key'),
			'client_secret' => config_item('kakao_client_secret'),
			'redirect_uri'  => site_url('auth/callback'),
			'code'          => $code,
		]));
		// not a login: the person came back from the extra consent for "카카오톡 캘린더에 넣기"
		$job = $this->session->userdata('talkcal');
		if ($job)
		{
			$this->session->unset_userdata('talkcal');
			$this->_talkcal($token, $job);
		}
		$me = isset($token['access_token'])
			? http_json('https://kapi.kakao.com/v2/user/me', null, ['Authorization: Bearer ' . $token['access_token']])
			: null;
		if (empty($me['id']))
		{
			$why = '';
			if (isset($token['error_code'])) $why = " ({$token['error_code']}) {$token['error_description']}";
			elseif (isset($me['msg'])) $why = " ({$me['code']}) {$me['msg']}";
			show_error('카카오 인증에 실패했어요. REST API 키, Client Secret, Redirect URI를 확인해주세요.' . $why, 400);
		}

		$profile =isset($me['kakao_account']['profile']) ? $me['kakao_account']['profile'] : [];
		// profile.nickname needs the consent item; properties.nickname is the older field some apps still get
		$nick = isset($profile['nickname']) ? $profile['nickname']
			: (isset($me['properties']['nickname']) ? $me['properties']['nickname'] : '친구');
		$img  = isset($profile['profile_image_url']) ? $profile['profile_image_url'] : null;

		// Email only arrives for biz apps with the email consent item; trust it only when Kakao marks it verified.
		$acct  = isset($me['kakao_account']) ? $me['kakao_account'] : [];
		$email = (!empty($acct['is_email_valid']) && !empty($acct['is_email_verified']) && isset($acct['email'])) ? strtolower($acct['email']) : '';
		// Admin = Kakao member id listed in config/kakao.php (works on any server, no DB access needed).
		// The id is stable per Kakao app, so the same app on a new server gives the same id.
		$admin = (in_array((string) $me['id'], array_map('strval', config_item('admin_kakao_ids')), true)
			|| in_array($email, array_map('strtolower', config_item('admin_emails')), true)) ? 1 : 0;

		// LAST_INSERT_ID(id) makes insert_id() return the existing row's id on duplicate.
		// is_admin only ever goes up here (GREATEST); demoting is a manual UPDATE.
		$this->db->query(
			'INSERT INTO users (kakao_id, nickname, profile_img, is_admin) VALUES (?, ?, ?, ?)
			 ON DUPLICATE KEY UPDATE nickname = IF(VALUES(nickname) = \'친구\', nickname, VALUES(nickname)),
			   profile_img = COALESCE(VALUES(profile_img), profile_img),
			   is_admin = GREATEST(is_admin, VALUES(is_admin)), id = LAST_INSERT_ID(id)',
			[$me['id'], mb_substr($nick, 0, 100), $img, $admin]
		);
		$uid = $this->db->insert_id();

		$next = $this->session->userdata('next');
		$this->session->sess_regenerate(TRUE);
		$this->session->set_userdata(['uid' => $uid, 'nick' => $nick, 'img' => $img]);
		$this->session->unset_userdata('next');
		redirect($next ?: '/');
	}

	// "카카오톡 캘린더에 넣기", step 2: put the plans chosen in Trips::talkcal into the person's own KakaoTalk calendar
	// (each with a reminder 30 minutes before). The token is used once here and never stored.
	private function _talkcal($token, $job)
	{
		$uid  = $this->session->userdata('uid');
		$back = 'trip/' . (int) $job['trip'] . '#plans';
		$fail = function ($msg) use ($back) { $this->session->set_flashdata('err', $msg); redirect($back); };
		if (!$uid) redirect('login');
		if (empty($token['access_token']))
		{
			$fail('카카오 인증에 실패했어요. 다시 시도해 주세요.' . (isset($token['error_description']) ? " ({$token['error_description']})" : ''));
		}
		$auth = ['Authorization: Bearer ' . $token['access_token']];

		// the Kakao account that agreed must be the one logged in here
		$me = http_json('https://kapi.kakao.com/v2/user/me', null, $auth);
		$mine = $this->db->get_where('users', ['id' => $uid])->row();
		if (empty($me['id']) || !$mine || (string) $me['id'] !== (string) $mine->kakao_id)
		{
			$fail('로그인한 카카오 계정과 동의한 계정이 달라요. 같은 계정으로 다시 시도해 주세요.');
		}
		if (!$this->db->get_where('trip_members', ['trip_id' => $job['trip'], 'user_id' => $uid])->num_rows()) show_404();

		$trip  = $this->db->get_where('trips', ['id' => $job['trip']])->row();
		$plans = $this->db->select('p.*, u.nickname AS author, d.name AS dest_name')->from('plans p')->join('users u', 'u.id = p.added_by')
			->join('places d', 'd.id = p.dest_id', 'left')->where('p.trip_id', $job['trip'])->where_in('p.id', $job['plans'])
			->order_by('p.day')->order_by('p.at_time')->get()->result();
		$wanted = array_map(function ($pl) use ($trip) { return talkcal_event($pl, $trip); }, $plans);
		if (!$wanted) $fail('카카오톡 캘린더에 넣을 일정이 없어요.');
		$more = function ($res) { return isset($res['msg']) ? " ({$res['code']}) {$res['msg']}" : ' (카카오에서 응답이 없어요)'; };
		// the console hint only helps when Kakao is complaining about consent / permission, not about the data
		$hint = function ($why) { return preg_match('/scope|permission|consent|authoriz|동의|권한/i', $why) ? ' 카카오 개발자 콘솔의 톡캘린더 동의항목과 사용 권한(앱 멤버만 가능)을 확인해 주세요.' : ''; };

		// what the calendar already holds around these plans (the list API gives at most 31 days at a time).
		// ponytail: one page of 1000 events per window; has_next is not followed
		$first = min(array_map(function ($e) { return strtotime($e['time']['start_at']); }, $wanted)) - 3600;
		$last  = max(array_map(function ($e) { return strtotime($e['time']['end_at']); }, $wanted)) + 3600;
		$existing = [];
		for ($from = $first; $from < $last; $from += 30 * 86400)
		{
			$res = http_json('https://kapi.kakao.com/v2/api/calendar/events?' . http_build_query([
				'calendar_id' => 'primary', 'from' => gmdate('Y-m-d\TH:i:s\Z', $from), 'to' => gmdate('Y-m-d\TH:i:s\Z', min($from + 30 * 86400, $last)), 'limit' => 1000,
			]), null, $auth);
			if (!isset($res['events']))
			{
				$why = $more($res);
				$fail('카카오톡 캘린더의 일정을 불러오지 못했어요.' . $why . $hint($why));
			}
			$existing = array_merge($existing, $res['events']);
		}
		$todo = talkcal_sync($wanted, $existing);

		$made = $gone = 0; $why = '';
		foreach ($todo['create'] as $event)
		{
			$res = http_json('https://kapi.kakao.com/v2/api/calendar/create/event', ['event' => json_encode($event, JSON_UNESCAPED_UNICODE)], $auth);
			if (isset($res['event_id'])) { $made++; continue; }
			$why = $more($res);
			break; // the same reason would stop the rest too
		}
		if (!$why) foreach ($todo['delete'] as $id) // extra copies of a plan that is already in the calendar
		{
			$res = http_json('https://kapi.kakao.com/v2/api/calendar/delete/event?' . http_build_query(['event_id' => $id]), null, $auth, 'DELETE');
			if (isset($res['event_id'])) { $gone++; continue; }
			$why = $more($res);
			break;
		}
		$summary = "새로 {$made}개 넣고, 이미 있는 {$todo['kept']}개는 그대로 두고, 중복 {$gone}개는 지웠어요.";
		if ($why && !$made && !$gone) $fail('카카오톡 캘린더에 넣지 못했어요.' . $why . $hint($why));
		$this->session->set_flashdata($why ? 'err' : 'ok', '카카오톡 캘린더: ' . $summary . ($why ? ' 일부는 처리하지 못했어요.' . $why : ' 톡캘린더나 위젯에서 확인해 보세요.'));
		redirect($back);
	}

	public function logout()
	{
		if ($this->input->method() !== 'post') show_404();
		$this->session->sess_destroy();
		redirect('/');
	}
}
