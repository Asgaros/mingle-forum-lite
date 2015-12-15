<?php

/*
  Plugin Name: Asgaros Forum
  Plugin URI: https://github.com/Asgaros/asgaros-forum
  Description: A lightweight and simple forum plugin for WordPress.
  Version: 1.0.0-Dev
  Author: Thomas Belser
  Author URI: https://thomasbelser.net
  License: GPL2
  License URI: https://www.gnu.org/licenses/gpl-2.0.html
  Text Domain: asgarosforum
  Domain Path: /languages

  Asgaros Forum is free software: you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation, either version 2 of the License, or
  any later version.

  Asgaros Forum is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with Asgaros Forum. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
*/

//Textdomain Hook
$plugin_dir = plugin_basename(dirname(__FILE__));
load_plugin_textdomain('asgarosforum', false, $plugin_dir . '/languages/');

//Setup defines
define('WPAFURL', plugin_dir_url(__FILE__));

//Load class files
require('forum.php');

if (is_admin()) {
    require_once(dirname(__FILE__).'/admin/admin.php');
}

global $asgarosforum;
global $asgarisforum_admin;
$asgarosforum = new asgarosforum();

if (is_admin()) {
    $asgarosforum_admin = new asgarosforum_admin();
}

?>