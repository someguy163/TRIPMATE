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

// The plans against what the calendar already holds: which events to create, and which extra copies to delete.
// $wanted = events from talkcal_event(); $existing = the "events" list of Kakao's list API.
// Only an existing event with the same title and times as a plan counts, so nothing else in the calendar is touched.
// Two plans with the same title and times need two events; a third copy is an extra.
function talkcal_sync(array $wanted, array $existing)
{
	$need = $have = [];
	foreach ($wanted as $e) $need[talkcal_key($e['title'], $e['time']['start_at'], $e['time']['end_at'])][] = $e;
	foreach ($existing as $x)
	{
		if (isset($x['type']) && $x['type'] !== 'USER') continue; // public and subscribed calendars are not ours
		if (empty($x['id']) || !isset($x['title']) || empty($x['time']['start_at']) || empty($x['time']['end_at'])) continue;
		$k = talkcal_key($x['title'], $x['time']['start_at'], $x['time']['end_at']);
		if (isset($need[$k])) $have[$k][$x['id']] = $x['id']; // keyed by id: windows of the list can return an event twice
	}
	$create = $delete = []; $kept = 0;
	foreach ($need as $k => $list)
	{
		$h = isset($have[$k]) ? array_values($have[$k]) : [];
		$kept += min(count($h), count($list));
		foreach (array_slice($list, count($h)) as $e) $create[] = $e;
		foreach (array_slice($h, count($list)) as $id) $delete[] = $id;
	}
	return ['create' => $create, 'delete' => $delete, 'kept' => $kept];
}
