<?php
/**
 * WP-CI The CodeIgniter plugin for WordPress.
 * Copyright (C)2009-2010 Collegeman.net, LLC.
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

/**
 * The WPCI class provides a namespace for functions and state that
 * bridge the gap between WordPress and CodeIgniter. 
 */
class WPCI {
	
	private static $apps = array();
	
	static function get_apps() {
		return array_merge(array('__core__' => APPPATH), self::$apps);
	}
	
	private static $app_index = array();
	
	static function find_app($token) {
		return (isset(self::$app_index[$token])) ? self::$app_index[$token] : null;
	}
	
	private static $menus = array();
	
	private static $active_app;
	
	static function get_active_app() {
		return self::$active_app;
	}
	
	private static $title;
	
	static function set_title($title) {
		self::$title = $title;
	}
	
	static function get_title($sep = null, $seplocation = null) {
		if (!self::$title)
			return null;
		
		if ($sep && $seplocation == 'right') {
			return self::$title.' '.$sep;
		}
		else if ($sep && $seplocation == 'left') {
			return $sep.' '.self::$title;
		}
		else {
			return self::$title;
		}
	}
	
	private static $vars = array();
	
	static function set($name, $value = null) {
		self::$vars[$name] = $value;
		return $value;
	}
	
	static function get($name, $default = null) {
		return (isset(self::$vars[$name]) ? self::$vars[$name] : $default);
	}
	
	static function safe($name, $default = null) {
		return htmlentities(self::get($name, $default));
	}
	
	static function add_actions() {
		add_action('activate_wp-ci/wp-ci.php', 		array('WPCI', 'activate_plugin'));
		add_action('deactivate_wp-ci/wp-ci.php', 	array('WPCI', 'deactivate_plugin'));
		add_filter('wp_list_pages_excludes', 		array('WPCI', 'list_pages_excludes'));
		add_filter('init', 							array('WPCI', 'flush_rules'));
		add_filter('generate_rewrite_rules', 		array('WPCI', 'generate_rewrite_rules'));
		add_filter('query_vars', 					array('WPCI', 'rewrite_query_vars'));
		add_filter('the_title', 					array('WPCI', 'the_title'), 10, 2);
		add_filter('wp_title', 						array('WPCI', 'wp_title'), 10, 3);
		add_filter('the_content',					array('WPCI', 'the_content'));
		add_action('in_admin_footer', 				array('WPCI', 'in_admin_footer'));
		add_filter('page_template', 				array('WPCI', 'template'));
		add_action('admin_menu', 					array('WPCI', 'admin_menu'));
		add_action('plugins_loaded', 				array('WPCI', 'execute'));
	}
	
	private static function template_exists($file) {
		$path = get_template_directory().'/'.$file;
		return (file_exists($path) ? $path : FALSE);
	}

	static function wpci_template($default) {
		log_message('debug', "Default template is $default");
		$template = $default;
		if (is_codeigniter()) {
			global $RTR;
			// application/class/action template
			if ($path = self::template_exists($RTR->fetch_app().'/'.$RTR->fetch_class().'/'.$RTR->fetch_method().EXT))
				$template = $path;
			// application/class template
			else if ($path = self::template_exists($RTR->fetch_app().'/'.$RTR->fetch_class().EXT))
				$template = $path;
			// class/action
			else if ($path = self::template_exists($RTR->fetch_class().'/'.$RTR->fetch_method().EXT))
				$template = $path;
			// class-named template
			else if ($path = self::template_exists($RTR->fetch_class().EXT))
				$template = $path;
			// application-named template
			else if ($path = self::template_exists($RTR->fetch_app().EXT))
				$template = $path;
			// template file named "codeigniter.php"
			else if ($path = self::template_exists('codeigniter'.EXT))
				$template = $path;
		}

		log_message('debug', "Loading template $template");
		return $template;
	}
	
	// the_title filter is executed for the_title() function, typically
	// used by WordPress templates to print the page title into the source
	static function the_title($title, $post = null) {
		return (is_codeigniter()) ? WPCI::get_title() : $title;
	}

	// wp_title filter is executed to retrieve the page/post title as it
	// is printed for the <title></title> tag, as well as by the Widgets
	// that print lists of pages or posts
	static function wp_title($title, $sep, $seplocation) {
		return (is_codeigniter()) ? WPCI::get_title($sep, $seplocation) : $title;
	}

	static function the_content($content) {
		global $wp_query, $RTR, $OUT;
		$gateway = wpci_get_gateway();
		if ($wp_query->query_vars['pagename'] == $gateway->post_name) {
			ob_start();
			$OUT->_display();
			echo do_shortcode(ob_get_clean());
		}
		else {
			return $content;
		}
	}

	static function in_admin_footer() {
		?> 
			<span style="float: right; margin-left: 20px;">
				You are running <a href="http://codeigniter.com" target="_blank">CodeIgniter&reg; <?php echo CI_VERSION ?></a>
				| <a href="http://wiki.github.com/collegeman/wp-ci/" target="_blank">Learn WP-CI</a>
			</span> 
		<?php
	}

	static function flush_rules() {
		global $wp_rewrite;
		$wp_rewrite->flush_rules();
	}

	static function generate_rewrite_rules($wp_rewrite) {
		$gateway = wpci_get_gateway();
		$wp_rewrite->rules = array(
			'^'.wpci_get_slug().'/.*' => 'index.php?pagename='.$gateway->post_name
		) + $wp_rewrite->rules;
	}

	static function rewrite_query_vars($vars) {
		return $vars;
	}

	static function activate_plugin() {
		if (!($slug = get_option('wpci_gateway_slug', false))) {

			do {
				$slug = md5(uniqid());
				if (!get_page_by_path($slug)) {
					$page = wpci_create_gateway($slug);
				}
			} while (!$page);

			update_option('wpci_gateway_slug', $slug);
		}

		// no page for the slug? maybe deleted, create again...
		else if (!get_page_by_path($slug)) {
			wpci_create_gateway($slug);
		}

	}
	
	// on deactivation, remove our gateway page
	function deactivate_plugin() {
		// remove our gateway page, otherwise it'll show up in page lists
		if ($page = wpci_get_gateway()) {
			wp_delete_post($page->ID);
		}	
	}

	// make sure that our gateway page doesn't show up in standard page lists
	static function list_pages_excludes($exclude) {
		$gateway = wpci_get_gateway();
		$exclude[] = $gateway->ID;
		return $exclude;
	}

	static function get_url($resource, $app = null) {
		if (!$app)
			$app = self::$active_app;
		
		if ($app)
			return WP_PLUGIN_URL."/$app/application/".$resource;
		else
			return WP_PLUGIN_URL."/wp-ci/".$resource;
	}
	
	static function url($resource, $app = null) {
		echo self::get_url($resource, $app);
	}
	
	static function activate($spec) {
		if (is_array($spec) && count($spec) < 2)
			return FALSE;
		
		// if $spec is an array, use the second segment for app name, otherwise just use spec
		$app_name = (is_array($spec) && count($spec) > 1 ? $spec[1] : $spec);

		if (self::registered($app_name)) {
			global $CFG;
			self::$active_app = $app_name;
			$CFG->load('config');

			log_message('debug', "Pluggable application [$app_name] activated");
		}
	}
	
	static function active_app_path($fallback_to_core = TRUE) {
		return (self::$active_app ? self::$apps[self::$active_app] : ( $fallback_to_core ? APPPATH : null ));
	}
	
	static function registered($spec) {
		if (is_array($spec) && count($spec) < 2)
			return FALSE;
		
		$app_name = (is_array($spec) && count($spec) > 1 ? $spec[1] : $spec);
		
		return isset(self::$apps[$app_name]);
	}
	
	static function routes() {
		return  (self::$active_app ? self::$apps[self::$active_app].'config/routes'.EXT : APPPATH.'config/routes'.EXT);
	}
	
	/**
	 * Locate and load the PHP file to handle this CI request.
	 * @return If found, the path to the controller file.
	 */
	static function include_controller(&$RTR) {
		$the_path = null;
		
		$this_path = self::active_app_path().'/controllers/'.$RTR->fetch_directory().$RTR->fetch_class().EXT;
		if (file_exists($this_path)) {
			require_once($this_path);
			log_message('debug', "Loaded Controller $this_path");
			$the_path = $this_path;
		}
		
		if (!$the_path) {
			$this_path = self::active_app_path().'/controllers/'.$RTR->default_controller.EXT;
			require_once($this_path);
			log_message('debug', "Loaded Controller $this_path");
			$the_path = $this_path;
		}
		
		if (!$the_path)		
			wp_die('Unable to load default controller. Please make sure the controller specified in routes.php file is valid.');
			
		return $the_path;
	}
	
	/**
	 * Register a WordPress plugin as a CI application.
	 * @param $plugin_file String The path to the plugin trigger file; easily identified by __FILE__ constant.
	 */
	static function register_app($plugin_file) {
		$app_name = substr(basename($plugin_file), 0, strrpos(basename($plugin_file), '.'));
		log_message('debug', "Registering pluggable application: [$app_name]");
		$app_path = realpath(dirname($plugin_file)).'/application';
		if (file_exists($app_path) && is_dir($app_path)) {
			self::$apps[$app_name] = $app_path;
		}
		else {
			wp_die("Plugin <b>$plugin_file</b> is not a valid pluggable CI application: missing folder <b>$app_path</b>.");
		}
	}
	
	
	function process_menu_annotations($app = null, $app_path = null) {
		if ($app == null && $app_path == null) {
			foreach(self::$apps as $app => $app_path) {
				self::process_menu_annotations($app, "$app_path/controllers");
			}
			return;
		}

		$dir = opendir($app_path);

		while(($entry = readdir($dir)) !== false) {

			if ($entry != '.' && $entry != '..') {

				if (is_dir("$app_path/$entry")) {
					self::process_menu_annotations($app, "$app_path/$entry");
				}

				else if (stripos($entry, '.php') == strlen($entry)-4) {
					$class = split("\.", $entry);
					$class = $class[0];

					Annotations::addClass($class, "$app_path/$entry");

					$ann = Annotations::get($class);
					if (isset($ann['methods'])) {
						foreach($ann['methods'] as $method_name => $method) {

							// administrative menus
							if (isset($method['menu'])) {
								self::add_menu($app, "$app_path/$entry", $class, $method_name, $method);
							}

							// submenus
							if (isset($method['submenu'])) {
								self::add_submenu($app, "$app_path/$entry", $class, $method_name, $method);
							}
							else if (isset($method['item'])) {
								$method['submenu'] = $method['item'];
								self::add_submenu($app, "$app_path/$entry", $class, $method_name, $method);
							}

							// submenus on specific pages
							foreach(array('posts', 'media', 'links', 'pages', 'comments', 'appearance', 'plugins', 'users', 'tools', 'settings') as $p) {
								if (isset($method[$p.'_item'])) {
									self::add_submenu($app, "$app_path/$entry", $class, $method_name, $method, $p);
								}
							}

						}
					}

				}
			}
		}

		closedir($dir);
	}
	
	
	static function admin_menu() {
		// add menu item for WP-CI
		add_options_page('WP-CI', 'WP-CI', 'administrator', 'wp-ci', array('WPCI', 'execute_admin_fx'));

		// then add all the other menus
		foreach(self::$menus as $menu) {
			if ($menu['type'] == 'menu') {
				self::_menu($menu);
			}
			else {
				self::_submenu($menu);
			}
		}
	}
	
	

	static function add_menu($app, $path, $class, $method_name, $annotations) {
		$args = wp_parse_args($annotations['menu'], array(
			'page_title' => 'My Menu',
			'menu_title' => '',
			'position' => null,
			'capability' => ''
		));
		
		if (!$args['menu_title'])
			$args['menu_title'] = $args['page_title'];
			
		if (!$args['capability'] && isset($annotations['user_can']))
			$args['capability'] = $annotations['user_can'];
		else
			$args['capability'] = 'administrator'; // maybe overkill... ?
			
		$token = "wp-ci/$app/$class/$method_name";
		
		if (!isset(self::$app_index[$app]))
			$app_index[$app] = array();
		
		if (!isset(self::$app_index[$class]))
			$app_index[$class] = array();
		
		self::$app_index[$app][$class][$method_name] = $token;
		self::$app_index[$token] = array('app' => $app, 'app_path' => $path, 'class' => $class, 'method_name' => $method_name);
		
		$icon_url = '';
		if ($icon = isset($annotations['icon']) ? $annotations['icon'] : null) {
			if (strncmp($icon, '/', 1) != 0 && strncmp($icon, 'http://', 7) != 0)
				$icon_url = self::get_url($icon, $app); 
			else
				$icon_url = $icon;
		}
		
		$action_label = isset($_REQUEST['id']) ? 'Edit' : 'Add New';
		$page_title = preg_replace('/%a/', $action_label, $args['page_title']);
		$menu_title = preg_replace('/%a/', $action_label, $args['menu_title']);
		
		self::$menus[] = array(
			'type' => 'menu',
			'page_title' => $page_title, 
			'menu_title' => $menu_title,
			'capability' => $args['capability'], 
			'token' => $token,
			'icon_url' => $icon_url,
			'position' => $args['position']
		);
	}
	
	static function add_submenu($app, $path, $class, $method_name, $annotations, $page = null) {
		$args = wp_parse_args($annotations['submenu'], array(
			'page_title' => 'My Menu Item',
			'menu_title' => '',
			'capability' => '',
			'parent' => ''
		));
		
		if (!$args['menu_title'])
			$args['menu_title'] = $args['page_title'];
			
		if (!$args['capability'] && isset($annotations['user_can']))
			$args['capability'] = $annotations['user_can'];
		else
			$args['capability'] = 'administrator'; // maybe overkill... ?
			
		$token = "wp-ci/$app/$class/$method_name";
	
		if (!isset(self::$app_index[$app]))
			$app_index[$app] = array();
		
		if (!isset(self::$app_index[$class]))
			$app_index[$class] = array();
		
		self::$app_index[$app][$class][$method_name] = $token;
		self::$app_index[$token] = array('app' => $app, 'app_path' => $path, 'class' => $class, 'method_name' => $method_name);
		
		if ($args['parent']) {
			if (stripos($args['parent'], '.php') == strlen($args['parent'])-4) // specific WordPress menu...
				$parent_token = $args['parent'];
			else
				$parent_token = self::$app_index[$app][$class][$args['parent']];
		
			$action_label = isset($_REQUEST['id']) ? 'Edit' : 'Add New';
			$page_title = preg_replace('/%a/', $action_label, $args['page_title']);
			$menu_title = preg_replace('/%a/', $action_label, $args['menu_title']);
			
			self::$menus[] = array(
				'type' => 'submenu',
				'parent_token' => $parent_token,
				'page_title' => $page_title,
				'menu_title' => $menu_title,
				'capability' => $args['capability'],
				'token' => $token
			);
		}
	}
	
	function _menu($menu) {
		extract($menu);
		add_menu_page($page_title, $page_title, $capability, $token, array('WPCI', 'execute_admin_fx'), $icon_url, $position);
		if ($page_title != $menu_title)
			add_submenu_page($token, $page_title, $menu_title, $capability, $token, array('WPCI', 'execute_admin_fx'));	
		
	}
	
	static function _submenu($menu) {
		extract($menu);
		add_submenu_page($parent_token, $page_title, $menu_title, $capability, $token, array('WPCI', 'execute_admin_fx'));	
	}
	
	static function execute_admin_fx() {
		global $OUT;
		echo $OUT->_display();
	}
	
	static function execute() {
		log_message('debug', 'Firing-up the engines: processing'.(is_admin() ? ' admin ' : ' ').'request.');
		
		if (is_admin()) {
			self::execute_admin();
		}

		else { // front-end request
			self::execute_frontend();
		}
	}
	
	private static function execute_frontend() {
		global $RTR, $CI, $EXT, $BM, $URI;

		// set routing, triggering detection of active application (if any)
		$RTR->_set_routing();
		$RTR->set_app(WPCI::get_active_app());

		// complete CI logging
		log_message('debug', "Router Class Set");

		// if a class was identified, try to execute it
		if ($RTR->fetch_class()) {

			$app_path = WPCI::include_controller($RTR);

			$BM->mark('loading_time_base_classes_end');

			/*
			 * ------------------------------------------------------
			 *  Security check
			 * ------------------------------------------------------
			 *
			 *  None of the functions in the app controller or the
			 *  loader class can be called via the URI, nor can
			 *  controller functions that begin with an underscore
			 */
			$class  = $RTR->fetch_class();
			$method = $RTR->fetch_method();

			if (!class_exists($class)) {
				wp_die("I can't find <b>{$class}/{$method}</b>.");
			}

			if ($method == 'controller'
				OR strncmp($method, '_', 1) == 0
				OR in_array(strtolower($method), array_map('strtolower', get_class_methods('Controller')))
				)
			{
				wp_die("You're not allowed to do <b>{$class}/{$method}</b>.");
			}

			/*
			 * ------------------------------------------------------
			 *  Is there a "pre_controller" hook?
			 * ------------------------------------------------------
			 */
			$EXT->_call_hook('pre_controller');

			/*
			 * ------------------------------------------------------
			 *  Instantiate the controller and call requested method
			 * ------------------------------------------------------
			 */

			// Mark a start point so we can benchmark the controller
			$BM->mark('controller_execution_time_( '.$class.' / '.$method.' )_start');

			$CI = new $class();

			// Is this a scaffolding request?
			if ($RTR->scaffolding_request === TRUE)
			{
				if ($EXT->_call_hook('scaffolding_override') === FALSE)
				{
					$CI->_ci_scaffolding();
				}
			}
			else
			{
				/*
				 * ------------------------------------------------------
				 *  Is there a "post_controller_constructor" hook?
				 * ------------------------------------------------------
				 */
				$EXT->_call_hook('post_controller_constructor');

				// make sure app class is at the top of the annotations stack
				Annotations::addClass($RTR->fetch_class(), $app_path);

				// grab the title annotation, if defined
				if ($title = Annotations::get($RTR->fetch_class(), $RTR->fetch_method(), 'title'))
					WPCI::set_title($title);

				// Is there a "remap" function?
				if (method_exists($CI, '_remap'))
				{
					$CI->_remap($method);
				}
				else
				{
					// is_callable() returns TRUE on some versions of PHP 5 for private and protected
					// methods, so we'll use this workaround for consistent behavior
					if ( ! in_array(strtolower($method), array_map('strtolower', get_class_methods($CI))))
					{
						wp_die("I'm not allowed to do {$class}/{$method}.");
					}

					if (count(array_slice($URI->rsegments, 2)) > 0) {
						$param_list = trim(substr(
							preg_replace('/\t/',  '', 
								preg_replace('/[\n\r]/', '', 
									print_r(array_slice($URI->rsegments, 2), true)
						)), 8));
					}
					else {
						$param_list = ')';
					}

					log_message('debug', "Executing {$class}/{$method}($param_list");

					// Call the requested method.
					// Any URI segments present (besides the class/function) will be passed to the method for convenience
					call_user_func_array(array(&$CI, $method), array_slice($URI->rsegments, 2));
				}
			}

			// Mark a benchmark end point
			$BM->mark('controller_execution_time_( '.$class.' / '.$method.' )_end');

			/*
			 * ------------------------------------------------------
			 *  Is there a "post_controller" hook?
			 * ------------------------------------------------------
			 */
			$EXT->_call_hook('post_controller');

			/*
			 * ------------------------------------------------------
			 *  Is there a "post_system" hook?
			 * ------------------------------------------------------
			 */
			$EXT->_call_hook('post_system');

			/*
			 * ------------------------------------------------------
			 *  Close the DB connection if one exists
			 * ------------------------------------------------------
			 */
			if (class_exists('CI_DB') AND isset($CI->db))
			{
				$CI->db->close();
			}
		}
	}
	
	private static function execute_admin() {
		global $RTR, $CI, $EXT, $BM, $URI;

		// process annotations so that tokens are defined
		WPCI::process_menu_annotations();

		if ($token = isset($_REQUEST['page']) ? $_REQUEST['page'] : null) {

			$class = null;	
			$method = null;
			$app = null;
			$directory = null;
			$app_path = null;

			// exact match for token?
			if (isset(WPCI::$app_index[$token])) {
				
				// load the menu settings
				$menu = WPCI::$app_index[$token];

				// tell WPCI which app is active
				$app = $menu['app'];
				WPCI::activate($app);

				// load the application controller
				$app_path = $menu['app_path'];
				require_once($app_path);

				$BM->mark('loading_time_base_classes_end');

				// create an instance of the controller
				$class = $menu['class'];
				$method = $menu['method_name'];
			}

			// otherwise, read controller and action from request
			else if ($token == 'wp-ci') {
				
				$app = isset($_REQUEST['a']) ? $_REQUEST['a'] : null;
				$class = isset($_REQUEST['c']) ? strtolower($_REQUEST['c']) : 'settings';
				$method = isset($_REQUEST['m']) ? $_REQUEST['m'] : 'index';
				$directory = isset($_REQUEST['d']) ? $_REQUEST['d'] : null;

				// if app is specified, activate it... (otherwise the core application will be used)
				if ($app)
					WPCI::activate($app);

				if ($directory)	
					$app_path = WPCI::active_app_path()."/controllers/$directory/$class".EXT;
				else
					$app_path = WPCI::active_app_path()."/controllers/$class".EXT;

				if (!file_exists($app_path)) {
					wp_die("I don't know how to do <b>{$class}/{$method}</b>.");
				}	

				// load the contorller
				require_once($app_path);
			}

			if ($class && $method) {

				// fake the router into thinking he did his job...
				$RTR->set_app($app);
				$RTR->set_class($class);
				$RTR->set_method($method);
				$RTR->set_directory($directory);

				$BM->mark('loading_time_base_classes_end');

				if (!class_exists($class)) {
					wp_die("I can't find <b>{$class}/{$method}</b>.");
				}

				if ($method == 'controller'
					OR strncmp($method, '_', 1) == 0
					OR in_array(strtolower($method), array_map('strtolower', get_class_methods('Controller')))
					)
				{
					wp_die("You're not allowed to do <b>{$class}/{$method}</b>.");
				}

				$EXT->_call_hook('pre_controller');

				$BM->mark('controller_execution_time_( '.$class.' / '.$method.' )_start');

				$CI = new $class();

				$EXT->_call_hook('post_controller_constructor');

				// make sure app class is at the top of the annotations stack
				Annotations::addClass($class, $app_path);

				// Is there a "remap" function?
				if (method_exists($CI, '_remap')) {
					$CI->_remap($method);
				}
				else {
					// is_callable() returns TRUE on some versions of PHP 5 for private and protected
					// methods, so we'll use this workaround for consistent behavior
					if ( ! in_array(strtolower($method), array_map('strtolower', get_class_methods($CI)))) {
						wp_die("I'm not allowed to do <b>{$class}/{$method}</b>.");
					}
					
					log_message('debug', "Executing {$class}/{$method}()");

					// Call the requested method.
					// Any URI segments present (besides the class/function) will be passed to the method for convenience
					call_user_func_array(array(&$CI, $method), array());
				}

				$BM->mark('controller_execution_time_( '.$class.' / '.$method.' )_end');

				$EXT->_call_hook('post_controller');

				$EXT->_call_hook('post_system');

				if (class_exists('CI_DB') AND isset($CI->db)) {
					$CI->db->close();
				}	
			}
		}
	}
	
	
}



