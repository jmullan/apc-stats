<?php
class apc_lib {
  /**
   * This selects the default view.
   */
  public static $VIEW_HOST_STATS = 1;
  public static $VIEW_SYSTEM_CACHE_ENTRIES = 2;
  public static $VIEW_USER_CACHE = 3;
  public static $VIEW_SYSTEM_CACHE_ENTRIES_DIR = 4;
  public static $VIEW_VERSION_CHECK = 9;

  /**
   * The username for internal handling of http-auth.
   */
  private $ADMIN_USERNAME = 'apc';

  /**
   * The password for internal handling of http-auth.  This library will not
   * authenticate to the default password, so you must change it in your
   * configuration to access areas that require authentication.
   *
   * (beckerr) I'm using a clear text password here, because I've no good
   * idea how to let users generate a md5 or crypt password in a easy way to
   * fill it in
   */
  private $ADMIN_PASSWORD = '';
  private $USE_INTERNAL_AUTHENTICATION;
  /**
   * The default date format for dates output by the application.
   * You can override it in your configuration or by applying it to your
   * instance of this class:
   * $apc->DATE_FORMAT = 'd.m.Y H:i:s';
   *
   * The default is sort of US format.
   *
   * TODO: Consider replacing this with locale-based functionality.
   */
  public $DATE_FORMAT = 'Y/m/d H:i:s';

  private $time;

  private $MYREQUEST;
  private $PHP_SELF;
  private $AUTHENTICATED;

  public static $allowed_values = array(
    'SCOPE' => array(
      'A' => 'Active',
      'D' => 'Deleted'
    ),
    'SORT2' => array('D' => 'Descending',
		     'A' => 'Ascending'
    ),
    'COUNT' => array(10 => 'Top 10',
		     20 => 'Top 20',
		     50 => 'Top 50',
		     100 => 'Top 100',
		     150 => 'Top 150',
                                                         200 => 'Top 200',
                                                         500 => 'Top 500',
                                                         0 => 'All'),
                                        'AGGR' => array(0 => 'None'),
                                        'C_D' => array('A' => 'cache_list',
                                                       'D' => 'deleted_list'),
                                        'USER_SORT' => array('S' => 'User Entry Label',
                                                             'H' => 'Hits',
                                                             'Z' => 'Size',
                                                             'A' => 'Last Accessed',
                                                             'M' => 'Last Modified',
                                                             'T' => 'Timeout',
                                                             'C' => 'Created At',
                                                             'D' => 'Deleted At'),
                                        'FILE_SORT' => array('S' => 'Script Filename',
                                                             'H' => 'Hits',
                                                             'Z' => 'Size',
                                                             'A' => 'Last Accessed',
                                                             'M' => 'Last Modified',
                                                             'C' => 'Created At',
                                                             'D' => 'Deleted At'),
                                        'DIR_SORT' => array('S' => 'Directory Name',
                                                            'T' => 'Number of Files',
                                                            'H' => 'Hits',
                                                            'Z' => 'Size',
                                                            'C' => 'Avg. Hits',
                                                            'A' => 'Avg. Size'),
                                        'VIEW' => array(1 => 'Host Stats',
                                                        2 => 'System Cache Entries',
                                                        3 => 'User Cache',
                                                        4 => 'System Cache Entries by Directory',
                                                        9 => 'Version Check'),
                                        'CC' => array('opcode' => 'Opcode Cache',
                                                      'user' => 'User Cache'),
                                        'IMG' => array(1 => 'Memory Usage',
                                                       2 => 'Hits and Misses',
                                                       3 => 'Memory Usage and Fragmentation'),
                                        'LO' => array(1 => 'Login'));
  /**
   * These are regex-based input validators.
   */
  private static $input_validators = array(/* Delete User Key
                                            *
                                            * TODO: Should this really accept
                                            * everything?
                                            */
    'DU' => '/^.*$/',
    /*
     * Shared object description: an
     * md5 sum of an inode, filename,
     * or user entry label.
     */
    'SH' => '/^[a-fA-F0-9]{32}$/',
    /*
     * Search within the results by a string.
     */
    'SEARCH' => '~^[a-zA-Z0-1/_.-]*$~',
  );

  /**
   * Default cache mode
   */
  private $cache_mode = 'opcode';
  private $host;
  public $apc_all;
  public $cache_user;
  public $sma_information;
  private $errors;
  private $require_login;
  public $is_valid;
  function __construct() {
    $this->errors = array();
    if (!function_exists('apc_cache_info')) {
      $this->errors[] = "No cache info available.  APC does not appear to be running. ";
      $this->is_valid = false;
    } else {
      $this->time = time();
      foreach (range(1, 10) as $i) {
        self::$allowed_values['AGGR'][$i] = $i;
      }
      $this->host = getenv('HOSTNAME');
      if ($this->host) {
        $this->host = '(' . $this->host . ')';
      }
      $this->apc_all = ini_get_all('apc');
      $this->cache_opcode = apc_cache_info('opcode', 1);
      $this->cache_user = apc_cache_info('user', 1);
      $this->sma_information = apc_sma_info();
      $this->MYREQUEST = array();
      $this->AUTHENTICATED = false;
      $this->require_login = false;
      $this->USE_INTERNAL_AUTHENTICATION = true;
      $this->is_valid = true;
    }
  }
  public function handle_old_config() {
    if (defined('USE_AUTHENTICATION') && !USE_AUTHENTICATION) {
      $this->USE_INTERNAL_AUTHENTICATION = false;
    }
    if (defined('ADMIN_USERNAME')) {
      $this->ADMIN_USERNAME = ADMIN_USERNAME;
    }
    if (defined('ADMIN_PASSWORD')) {
      $this->ADMIN_PASSWORD = ADMIN_PASSWORD;
    }
    if (defined('DATE_FORMAT')) {
      $this->DATE_FORMAT = DATE_FORMAT;
    }
    if (defined('GRAPH_SIZE')) {
      $this->apc_img->set_graph_size(intval(GRAPH_SIZE));
    }

  }
  public function get_ini($key) {
    if (isset($this->apc_all[$key]['local_value'])) {
      return $this->apc_all[$key]['local_value'];
    } elseif (isset($this->apc_all[$key]['global_value'])) {
      return $this->apc_all[$key]['global_value'];
    } else {
      return null;
    }
  }
  public function get_host() {
    return $this->host;
  }
  public function get_time() {
    return $this->time;
  }
  public function use_internal_authentication() {
    return ($this->USE_INTERNAL_AUTHENTICATION);
  }
  public function has_bad_password() {
    return (empty($this->ADMIN_PASSWORD));
  }
  public function is_authenticated() {
    if ($this->AUTHENTICATED
        || !$this->use_internal_authentication()
        || (!$this->has_bad_password()
            && isset($_SERVER['PHP_AUTH_USER'])
            && isset($_SERVER['PHP_AUTH_PW'])
            && ($_SERVER['PHP_AUTH_USER'] == $this->ADMIN_USERNAME)
            && ($_SERVER['PHP_AUTH_PW'] == $this->ADMIN_PASSWORD))) {
      $this->AUTHENTICATED = true;
    }
    return $this->AUTHENTICATED;
  }
  public function get_user_name() {
    return $_SERVER['PHP_AUTH_USER'];
  }
  function parse_request() {
    foreach ($_GET as $key => $value) {
      if (isset(self::$allowed_values[$key][$value])) {
        $this->MYREQUEST[$key] = $value;
      } elseif (isset(self::$input_validators[$key])) {
        if (preg_match(self::$input_validators[$key] . 'D', $value)) {
          $this->MYREQUEST[$key] = $value;
        }
      }
    }
    $this->PHP_SELF = (isset($_SERVER['PHP_SELF'])
                       ? htmlentities(strip_tags($_SERVER['PHP_SELF'], ''), ENT_QUOTES)
                       : '');
    $this->do_actions();
  }
  function get_total_memory() {
    $memory_available = ini_get('memory_limit');
    if (preg_match('/([0-9]+)([KM]?)/', $memory_available, $matches)) {
      $multipliers = array('K' => 1000, 'M' => 1000000);
      if ($matches[2]) {
        $memory_available = intval($matches[1]) * $multipliers[$matches[2]];
      } else {
        $memory_available = intval($matches[1]);
      }
    }
    return intval($memory_available);
  }
  function get_available_memory() {
    $memory_used = memory_get_usage();
    $memory_total = $this->get_total_memory();
    return $memory_total - $memory_used;
  }
  function request_memory($amount) {
    $amount += $this->get_total_memory();
    ini_set('memory_limit', intval($amount));
  }
  function do_actions() {
    if (!$this->is_valid) {
      return;
    }
    if (!empty($this->MYREQUEST['LO'])) {
      /*
       * Check authentication and bail
       */
      $this->is_authenticated();
      $this->require_login = true;
      return;
    }
    /*
      if ($this->get_view() == apc_lib::$VIEW_USER_CACHE
      && !$this->is_authenticated()) {
      $this->require_login = true;
      $this->errors[] = 'You need to login to see the user values here!';
      return;
      }
    */
    $memory_used = memory_get_usage();
    if ($this->get_view() == apc_lib::$VIEW_SYSTEM_CACHE_ENTRIES) {
      $this->cache_mode = 'opcode';
      $this->request_memory($this->cache_opcode['mem_size'] * 2);
      /* We need the detail for this view. */
      $this->cache_opcode = apc_cache_info('opcode', false);
    }
    if ($this->get_view() == apc_lib::$VIEW_SYSTEM_CACHE_ENTRIES_DIR) {
      $this->cache_mode = 'opcode';
      $this->request_memory($this->cache_opcode['mem_size'] * 2);
      /* We need the detail for this view. */
      $this->cache_opcode = apc_cache_info('opcode', false);
      $this->require_login = true;
    }
    if ($this->get_view() == apc_lib::$VIEW_USER_CACHE) {
      $this->cache_mode = 'user';
      /*
       * It might be possible to be more responsible with data copying and
       * memory buffering, but we're not there yet.  It would help to have a
       * mode where metadata about all of the entries is delivered, but
       * currently I am running out of allocated memory when trying to
       * report on my 282M of user variables unless the following magic
       * number is 4.
       */
      $this->request_memory($this->cache_user['mem_size'] * 4);
      /* We need the detail for this view. */
      $this->cache_user = apc_cache_info('user', false);
    }
    if ($this->get_request('CC')) {
      if ($this->is_authenticated()) {
        apc_clear_cache($this->cache_mode);
      } else {
        $this->require_login = true;
      }
    }
    if ($this->get_request('DU')) {
      if ($this->is_authenticated()) {
        apc_delete($this->get_request('DU'));
      } else {
        $this->require_login = true;
      }
    }
  }
  function get_cache_mode() {
    return $this->cache_mode;
  }
  function get_request($key) {
    if (isset($this->MYREQUEST[$key])) {
      return $this->MYREQUEST[$key];
    } else {
      return null;
    }
  }
  function get_scope() {
    if ($this->get_request('SCOPE')) {
      return $this->get_request('SCOPE');
    } else {
      return 'A';
    }
  }
  function get_count() {
    if (!is_null($this->get_request('COUNT'))) {
      return intval($this->get_request('COUNT'));
    } else {
      return 20;
    }
  }
  function get_view() {
    if ($this->get_request('VIEW')) {
      return $this->get_request('VIEW');
    } else {
      return self::$VIEW_HOST_STATS;
    }
  }
  function get_selected($key) {
    switch ($key) {
      case 'COUNT' : {
        return $this->get_count();
        break;
      }
      case 'VIEW' : {
        return $this->get_view();
        break;
      }
      case 'SCOPE' : {
        return $this->get_scope();
        break;
      }
      case 'SORT' : {
        return $this->get_sort();
        break;
      }
      default : {
        return $this->get_request($key);
        break;
      }
    }
  }
  function get_sort_context($view = null) {
    if (is_null($view)) {
      $view = $this->get_view();
    }
    if ($view == apc_lib::$VIEW_SYSTEM_CACHE_ENTRIES) {
      return 'FILE_SORT';
    } elseif ($view == apc_lib::$VIEW_USER_CACHE) {
      return 'USER_SORT';
    } elseif ($view == apc_lib::$VIEW_SYSTEM_CACHE_ENTRIES_DIR) {
      return 'DIR_SORT';
    } else {
      return null;
    }
  }
  function get_sort() {
    $default_sorts = array('FILE_SORT' => 'H',
                           'USER_SORT' => 'H',                         'DIR_SORT' => 'S');
    $sort = $this->get_request($this->get_sort_context());
    if (!$sort) {
      return $default_sorts[$this->get_sort_context()];
    }
    return $sort;
  }
  function get_sort_direction() {
    $direction = $this->get_request('SORT2');
    if (!$direction) {
      $direction = 'D';
    }
    return $direction;
  }
  public function get_request_vars() {
    return $this->MYREQUEST;
  }
  public function get_php_self() {
    return $this->PHP_SELF;
  }
  public function magic() {
    $this->handle_old_config();
    $this->parse_request();
    $apc_img = new apc_img($this);
    $apc_views = new apc_views($this);
    $apc_views->set_apc_img($apc_img);
    $apc_img->set_apc_views($apc_views);
    if (!$this->require_login && $this->get_request('IMG')) {
      $apc_img->handlerequest($this->get_request('IMG'));
      exit;
    }

    $page = array('title' => "APC INFO :: " . $this->get_host(),
                  'head' => '<link rel="stylesheet" type="text/css" href="apc.css" />',
                  'body' => '');
    ob_start();
    echo $apc_views->display_errors($this->errors);
    if (($this->require_login || $this->get_request('LO')) && !$this->is_authenticated()) {
      echo $apc_views->get_rejected_login_message();
    } else {
      echo $apc_views->handlerequest($this->get_view());
    }
    $page['body'] .= ob_get_clean();
    $apc_views->render($page);
  }
}
