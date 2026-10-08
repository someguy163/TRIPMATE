<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// A plan as a KakaoTalk-calendar event. Kakao takes RFC3339 UTC times in whole 5-minute steps only, so the start moves
// earlier and the end later: the plan is still covered.
function talkcal_event($pl, $trip)
{
	$seoul = new DateTimeZone('Asia/Seoul');
	$ts    = function ($time) use ($pl, $seoul) { return (new DateTime($pl->day . ' ' . $time, $seoul))->getTimestamp(); };
	$from  = $ts($pl->at_time);
	$to    = $pl->end_time ? $ts($pl->end_time) : $from + 3600; // no end time: one hour
	$start = (int) (floor($from / 300) * 300);
	$end   = (int) (ceil($to / 300) * 300);
	if ($end <= $start) $end = $start + 300;
	$event = [
		'title'       => mb_substr($pl->title, 0, 50),
		'time'        => ['start_at' => gmdate('Y-m-d\TH:i:s\Z', $start), 'end_at' => gmdate('Y-m-d\TH:i:s\Z', $end), 'time_zone' => 'Asia/Seoul', 'all_day' => false, 'lunar' => false],
		'description' => ($pl->dest_name ? $pl->dest_name . ' · ' : '') . $trip->title . ' (작성 ' . $pl->author . ')',
		'reminders'   => [30],
	];
	if ($pl->place)
	{
		$event['location'] = ['name' => mb_substr($pl->place, 0, 100)];
		if ($pl->lat !== null && $pl->lng !== null) $event['location'] += ['latitude' => (float) $pl->lat, 'longitude' => (float) $pl->lng];
	}
	return $event;
}

// what identifies an event: its title and its start and end instants
function talkcal_key($title, $start, $end)
{
	return $title . '|' . strtotime($start) . '|' . strtotime($end);
}

// The ids of events already in the calendar that look exactly like one of these plans (same title, start and end).
// $wanted = events from talkcal_event(); $existing = the "events" list of Kakao's list API. Nothing else is matched.
function talkcal_old(array $wanted, array $existing)
{
	$keys = [];
	foreach ($wanted as $e) $keys[talkcal_key($e['title'], $e['time']['start_at'], $e['time']['end_at'])] = true;
	$ids = [];
	foreach ($existing as $x)
	{
		if (isset($x['type']) && $x['type'] !== 'USER') continue; // public and subscribed calendars are not ours
		if (empty($x['id']) || !isset($x['title']) || empty($x['time']['start_at']) || empty($x['time']['end_at'])) continue;
		if (isset($keys[talkcal_key($x['title'], $x['time']['start_at'], $x['time']['end_at'])])) $ids[$x['id']] = $x['id']; // by id: list windows can return one event twice
	}
	return array_values($ids);
}

// Kakao's complaint as text: " (code) message"
function talkcal_why($res)
{
	return isset($res['msg']) ? " ({$res['code']}) {$res['msg']}" : ' (카카오에서 응답이 없어요)';
}

// Replace what this app put into a KakaoTalk calendar earlier with the current plans: remove the old events, make the new ones.
// $api($method, $path, $params) calls Kakao and returns the decoded JSON. $remembered = event ids stored after earlier runs;
// $remember($id) / $forget($id) keep that list. Returns ['gone' => removed, 'made' => created, 'why' => Kakao's complaint if it stopped early].
// The old events go first and a failure to remove one stops everything (new ones on top of old ones would double the plans).
function talkcal_replace(callable $api, array $wanted, array $existing, array $remembered, callable $remember, callable $forget)
{
	$old  = array_values(array_unique(array_merge($remembered, talkcal_old($wanted, $existing))));
	$gone = $made = 0; $why = '';
	foreach ($old as $id)
	{
		$res = $api('DELETE', '/v2/api/calendar/delete/event', ['event_id' => $id]);
		if (isset($res['event_id'])) $gone++;
		elseif (isset($api('GET', '/v2/api/calendar/event', ['event_id' => $id])['id']))
		{
			$why = talkcal_why($res); // it is still there and could not be removed
			break;
		}
		$forget($id); // removed, or already gone (the person deleted it)
	}
	if (!$why) foreach ($wanted as $event)
	{
		$res = $api('POST', '/v2/api/calendar/create/event', ['event' => json_encode($event, JSON_UNESCAPED_UNICODE)]);
		if (isset($res['event_id'])) { $made++; $remember($res['event_id']); continue; }
		$why = talkcal_why($res);
		break; // the same reason would stop the rest too
	}
	return ['gone' => $gone, 'made' => $made, 'why' => $why];
}
