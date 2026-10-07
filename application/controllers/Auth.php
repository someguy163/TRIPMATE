<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Auth extends CI_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->config->load('kakao');
		$this->load->helper('http');
	}

	public function login()
	{
		$state = bin2hex(random_bytes(16));
		$this->session->set_userdata('oauth_state', $state);
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

	public function logout()
	{
		if ($this->input->method() !== 'post') show_404();
		$this->session->sess_destroy();
		redirect('/');
	}
}
