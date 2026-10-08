<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/userguide3/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/
$route['default_controller'] = 'trips';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

$route['login']                 = 'auth/login';
$route['logout']                = 'auth/logout';
$route['trip/(:num)']           = 'trips/show/$1';
$route['trip/(:num)/place']     = 'trips/add_place/$1';
$route['trip/(:num)/plan']      = 'trips/add_plan/$1';
$route['place/(:num)/vote']     = 'trips/vote/$1';
$route['plan/(:num)/delete']    = 'trips/del_plan/$1';
$route['join/(:any)']           = 'trips/join/$1';
$route['place/(:num)/delete']   = 'trips/del_place/$1';
$route['place/(:num)/choose']   = 'trips/choose/$1';
$route['trip/(:num)/delete']    = 'trips/del_trip/$1';
$route['plan/(:num)/done']      = 'trips/done_plan/$1';
$route['trip/(:num)/leave']     = 'trips/leave/$1';
$route['plan/(:num)/edit']      = 'trips/edit_plan/$1';
$route['trip/(:num)/edit']      = 'trips/edit_trip/$1';
$route['trip/(:num)/kick/(:num)']     = 'trips/kick/$1/$2';
$route['trip/(:num)/owner/(:num)']    = 'trips/transfer/$1/$2';
$route['plan/(:num)/comment']         = 'trips/add_comment/$1';
$route['comment/(:num)/delete']       = 'trips/del_comment/$1';
$route['trip/(:num)/item']            = 'trips/add_item/$1';
$route['item/(:num)/take']            = 'trips/take_item/$1';
$route['item/(:num)/done']            = 'trips/done_item/$1';
$route['item/(:num)/delete']          = 'trips/del_item/$1';
$route['trip/(:num)/expense']         = 'trips/add_expense/$1';
$route['expense/(:num)/delete']       = 'trips/del_expense/$1';
$route['trip/(:num)/newlink']         = 'trips/new_invite/$1';
$route['trip/(:num)/calendar']        = 'trips/calendar/$1';
$route['trip/(:num)/talkcal']         = 'trips/talkcal/$1';
