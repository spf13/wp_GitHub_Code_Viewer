<?php
/*
Plugin Name: GitHub Code Viewer
Version: 2.0
Plugin URI: http://spf13.com/post/github-code-viewer/
Description: Pull file from github and place into post using shortcode [github_cv url='$url']
	Caches locally (in db), so there isn't a performance hit.
	Heavily Modified from original plugin by Matt Curry (http://www.pseudocoder.com)
Author: Steve Francia
Author URI: http://spf13.com
*/

class GitHub_Code_Viewer {
	static $db;
	static $ttl = '1 day';
	static $table = "github_code_cache";
	static $cache = array();

	function init() {
		global $wpdb;

		self::$db = $wpdb;
		self::$table = self::$db->prefix . "github";

		register_activation_hook(__FILE__, array(__CLASS__, 'install'));
		register_deactivation_hook(__FILE__, array(__CLASS__, 'uninstall'));
		add_shortcode('github_cv', array(__CLASS__, 'handle_shortcode'));
	}

	public function handle_shortcode($atts, $content = null) {
		if (array_key_exists('url', $atts)) {
			$url = $atts['url'];
		} else {
			return 'invalid github url';
		}

		if (array_key_exists('ttl', $atts) && preg_match('/^[\w\d\-]+$/', $atts['ttl'])) {
			$ttl = $atts['ttl'];
		} else {
			$ttl = self::$ttl;
		}

		return self::getGitHubFile($url, $ttl);
	}


	function install() {
		$result = self::$db->query("CREATE TABLE IF NOT EXISTS `{self::$table}` (
			`id` int(10) unsigned NOT NULL auto_increment,
			`url` text NOT NULL,
			`code` text NOT NULL,
			`updated` datetime NOT NULL default '0000-00-00 00:00:00',
			PRIMARY KEY  (`id`)
		)");
	}

	function uninstall() {
		$result = self::$db->query("DROP TABLE IF EXISTS `{self::$table}`");
	}


	function getGitHubFile($url, $ttl = null){
		self::__loadCache($url, $ttl);

		if (isset(self::$cache[$url])) {
			$code = self::$cache[$url];
		} else {
			$code = wp_remote_fopen($url . '?raw=true');
			if ($code == '') {
				return 'You need cURL installed to use GitHub_Code_Viewer';
			}
			$code = str_replace('<', '&lt;', $code);
			self::__setCache($url, $code);
		}

		return $code;
	}


	function __loadCache($urls, $ttl = null) {
		if ($ttl === null) {
			$ttl = self::$ttl;
		}

		$sql = sprintf('SELECT * FROM `%s`
			WHERE url IN ("%s")',
				self::$table,
				implode('", "', $urls));

		$results = self::$db->get_results($sql, ARRAY_A);
		if ($results) {
			$old = array();
			foreach($results as $row) {
				if($row['updated'] < date('Y-m-d H:i:s', strtotime('-' . $ttl))) {
					$old[] = $row['id'];
				} else {
					self::$cache[$row['url']] = $row['code'];
				}
			}

			if($old) {
				$sql = sprintf('DELETE FROM `%s` WHERE id IN (%s)',
					self::$table,
					implode(',', $old));
				self::$db->query($sql);
			}
		}

		return true;
	}

	function __setCache($url, $code) {
		$sql = sprintf('INSERT INTO `%s` (`url`, `code`, `updated`) VALUES ("%s", "%s", "%s")',
			self::$table,
			$url,
			mysql_real_escape_string($code),
			date('Y-m-d H:i:s'));
		$result = self::$db->query($sql);
	}
}

GitHub_Code_Viewer::init();
