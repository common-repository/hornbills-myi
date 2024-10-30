<?php
/**
 * Plugin Name:	Hornbills Myi
 * Description:	- 1: 在 WooCommerce 管理后台插入一个菜单 , 订单菜单之前插入,可以跳转到 blog 子站, 并自动进入部署手册文章页  2: 在 WooCommerce 子店页面底部自动插入一个订阅入口,收集用户邮箱,返回订阅成功说明页面,有合理数据库表收集**有效**订阅邮箱,能导出 .csv 数据文件,(可选)能合理批量发送促销邮件
 * Version: 1.0.0
 * Author: Alex Mo
 * Text Domain: hornbills
 * Domain Path: /languages/
 * WooCommerce requires at least: 5.0.0
 * Requires Wordpress at least: 5.4
 * Requires PHP: 7.0
 */
// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Check if WooCommerce is active
 **/
if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	exit;
}

/**
 * Return Hornbills_Myi Single Instance
 *
 * @since  1.0.0
 * @return object Hornbills_Myi
 */
function Hornbills_Myi() {
	return Hornbills_Myi::instance();
}

/**
 * Call Hornbills_Myi
 */
Hornbills_Myi();


/**
 *
 * Class Hornbills_Myi
 */
final class Hornbills_Myi{

	/**
	 * Hornbills_Myi The single instance of Hornbills_Myi.
	 * @var 	object
	 * @access  private
	 * @since 	1.0.0
	 */
	private static $_instance = null;

	/**
	 * The token.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $token;

	/**
	 * The version number.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $version;


	/**
	 * The plugin path.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $plugin_path;


	/**
	 * Constructor function.
	 * @access  private
	 * @since   1.0.0
	 * @return  void
	 */
	private function __construct() {
		$this->token = 'hornbills-myi';
		$this->version = '1.0.0';
		$this->plugin_path = plugin_dir_path(__FILE__);

		register_activation_hook(__FILE__, array($this, 'install'));
		register_deactivation_hook(__FILE__,array($this,'uninstall'));

		add_action('init', array($this, 'load_plugin_textdomain'));
		add_action('wp_enqueue_scripts', array($this, 'scripts'));
		add_action('wp_enqueue_style', array($this, 'styles'));

		add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
		add_action('admin_head', array($this, 'styles'));


		add_action('init', array($this, 'subscribe_entrance'));
		add_action( 'admin_menu',array( $this,'deployment_sub_menu') ,99);
		add_action( 'admin_menu',array( $this,'promotion_sub_menu') ,99);
		add_action( 'admin_menu',array( $this,'export_csv_menu') ,99);

		add_action( 'phpmailer_init',array( $this,'mailer_config'), 10, 1);

		// ajax request
		add_action('wp_enqueue_scripts', array($this,'email_ajax_enqueue'));
		add_action('admin_enqueue_scripts', array($this,'csv_ajax_enqueue'));
		add_action('wp_ajax_nopriv_email_save', array($this,'ajax_email_handler'));
		add_action('wp_ajax_email_save', array($this,'ajax_email_handler'));
		// login or nologin handling

		add_action('wp_ajax_export_csv', array($this,'export_csv_action'));
	}


	/**
	 * ajax email request
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function email_ajax_enqueue($hook) {
		wp_enqueue_script( $this->token.'-ajax-script',
			plugins_url( 'assets/js/common.js', __FILE__ ),
			array('jquery')
		);
		$email_nonce = wp_create_nonce($this->token.'-email-nonce');
		wp_localize_script($this->token.'-ajax-script', 'my_ajax_obj', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => $email_nonce,
		));
	}


	/**
	 * ajax csv request
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function csv_ajax_enqueue($hook) {
		wp_enqueue_script( $this->token.'-csv-ajax-script',
			plugins_url( 'assets/js/common.js', __FILE__ )
			//array('jquery')
		);
		$csv_nonce = wp_create_nonce($this->token.'-csv-nonce');
		wp_localize_script($this->token.'-csv-ajax-script', 'my_ajax_obj', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => $csv_nonce,
		));
	}

	/**
	 * ajax email handler
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	function ajax_email_handler() {
		check_ajax_referer($this->token.'-email-nonce');
		$email = sanitize_email($_POST['email']);
		if (is_email($email)){
			/**
			 * checkdnsrr （在win主机下无效) 只检测域名部分是否正常通信，
			 * 要实现检测是否是真正的邮箱必须检测SMTP是否正常通信（不可靠），
			 * 或者强制用户验证邮箱
			 */
			$arr = explode("@",$email);
			if(checkdnsrr(array_pop($arr),"MX")){
				// Store email
				global $wpdb;
				$table_name = $wpdb->prefix.'wcmd_user_subscribe_info';
				$exists = $wpdb->query('SELECT * FROM '.$table_name.' WHERE `email`="'.$email.'" LIMIT 1');
				if($exists === 0){  // not exists
					$result = $wpdb->query( 'INSERT INTO '.$table_name.' (email) VALUES ("'.$email.'")');
					if ($result){
						exit(wp_send_json_success(array('code'=>201,'msg'=>__('Subscribe success!','hornbills-myi'),'data'=>null)));
					}else{
						exit(wp_send_json_error(array('code'=>501,'msg'=>__('Store error!','hornbills-myi'),'data'=>null)));
					}
				}else{
					wp_send_json_success(array('code'=>201,'msg'=>__('You Already Subscribed!','hornbills-myi'),'data'=>null));
				}
			}
			exit(wp_send_json_error(array('code'=>501,'msg'=>__('Not a valid email address!','hornbills-myi'),'data'=>null)));
		}
		exit(wp_send_json_error(array('code'=>501,'msg'=>__('Not a email error','hornbills-myi'),'data'=>null)));

		wp_die(); // all ajax handlers should die when finished
	}


	/**
	 * Subscribe Entrance
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function subscribe_entrance(){
		global  $test;
		$test  = add_action( 'woocommerce_after_shop_loop', array($this,'subscribe_btn'));
	}




    /**
     * Subscribe btn and email form
     * @access  public
     * @since   1.0.0
     * @return  void
     */
	public function subscribe_btn() {
		printf('<button data-method="confirmTrans" title="%s" data-btn="%s" class="%s-subscribe-btn">%s</button>',__("Subscribe email",$this->token),__('Subscribe',$this->token),$this->token,__('Subscribe',$this->token));
		$form = <<<FORM
<form class="layui-form" action="" id="{$this->token}-subscribe-info-form" style="display:none">
  <div class="layui-form-item">
    <label class="layui-form-label">%s</label>
    <div class="layui-input-block">
      <input type="email" name="email" required  lay-verify="email" placeholder="%s" autocomplete="on" class="layui-input" id="{$this->token}-subscribe-email" size="30">
    </div>
  </div>
</form>
FORM;

		printf($form,__('Email',$this->token),__('Please enter your email address',$this->token));

	}


	/**
	 * Add Deployment Menu
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function deployment_sub_menu() {
		add_submenu_page('woocommerce',__(  'deployment instructions', $this->token ),__( 'deployment instructions', $this->token ),'manage_woocommerce' ,'deployment_instructions', array($this,'deployment_output'), 1 );
	}


	/**
	 * Add export csv Menu
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function export_csv_menu() {
		add_submenu_page('woocommerce',__(  'Export .csv', $this->token ),__( 'Export .csv', $this->token ),'manage_woocommerce' ,'export_csv', array($this,'export_csv_output'), 3 );
	}

	/**
	 * Add Promotion Menu
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function promotion_sub_menu(){
		add_submenu_page('woocommerce',__(  'Promotion', $this->token ),__( 'Promotion', $this->token ),'manage_woocommerce' ,'promotion', array($this,'promotion_output'), 2 );
	}


	/**
	 *
	 * @param PHPMailer $mailer
	 */
	public function mailer_config(PHPMailer\PHPMailer\PHPMailer $mailer){
		$mailer->IsSMTP();
		$mailer->Host = "smtp.qq.com"; // your SMTP server
		$mailer->SMTPAuth = true;
		$mailer->SMTPSecure = 'ssl';
		$mailer->Port = 465;
		$mailer->FromName = 'Preferential promotion';
		$mailer->Username = '465245514';
		$mailer->Password =  'butehqlizzzicbci';
		$mailer->SMTPDebug = 2; // write 0 if you don't want to see client/server communication in page
		$mailer->From = 'momo1a@qq.com';
		$mailer->CharSet = "utf-8";
		$mailer->isHTML(true);
	}

	/**
	 * promotion Menu Output
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function promotion_output(){
		global $wpdb;
		$table_name = $wpdb->prefix.'wcmd_user_subscribe_info';
		$limit = 10;
		$offset = 0;
		
		while (true){
			$emails = $wpdb->get_results('SELECT `email` FROM '.$table_name.' ORDER BY id DESC LIMIT '.$offset.','.$limit);
			if(!empty($emails)){
				foreach ($emails as $email){
					echo 'email :::'.$email->email.'<br/>';
					$mails[] = $email->email;
				}
				wp_mail($mails,__('Preferential promotion',$this->token),'<h1>Hello world!</h1>');
			}else{
				_e('Processing is complete!<br/>', $this->token);
				break;
			}
			$offset += 10;
		}
	}


	/**
	 * Export CSV View
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */

	public function export_csv_output(){
		_e('<div><button class="layui-btn layui-btn-normal" id="'.$this->token.'-csv-btn" style="margin-top: 5%">'.__("Export Emails",$this->token).'</button></div>');
	}

	/**
	 * Export CSV
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */

	public function export_csv_action() {
		check_ajax_referer($this->token.'-csv-nonce');
		global $wpdb;
		$table_name = $wpdb->prefix . 'wcmd_user_subscribe_info';
		$csv_source_array = $wpdb->get_results( " SELECT id, email FROM {$table_name} ", ARRAY_A );

		$data =  'Id,Email'.PHP_EOL;
		if ( !empty( $csv_source_array ) ) {
			foreach ( $csv_source_array as $line ) {
				$data .= $line['id'].','.$line['email'].PHP_EOL;
			}
			wp_send_json_success($data);
		}

		wp_send_json_error(__('No data!',$this->token));

	}

	/**
	 * Deployment Menu Output
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function deployment_output(){
		wp_redirect(get_blog_permalink(1,1));
	}

	/**
	 * Scripts
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public static function scripts() {
		wp_register_script("hornbills-common-script",plugins_url('assets/js/common.js', __FILE__));
		wp_register_script("hornbills-layui-script",plugins_url('assets/layui/layui.js', __FILE__));
		wp_enqueue_script('hornbills-layui-script');
		wp_enqueue_script('hornbills-common-script');
	}


	/**
	 * Scripts
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public static function admin_scripts() {
		wp_register_script("hornbills-layui-script",plugins_url('assets/layui/layui.js', __FILE__));
		wp_enqueue_script('hornbills-layui-script');
	}

	/**
	 * Styles
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public static function styles() {
		wp_register_style("hornbills-layui-style",plugins_url('assets/layui/css/layui.css', __FILE__));
		wp_enqueue_style('hornbills-layui-style');
	}

	/**
	 * Load the localisation file.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain($this->token, false, dirname(plugin_basename(__FILE__)) . '/languages/');
	}

	/**
	 * Main Hornbills_Myi Instance
	 *
	 * Ensures only one instance of Hornbills_Myi is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @see Hornbills_Myi()
	 * @return Main Hornbills_Myi instance
	 */
	public static function instance() {
		if (is_null(self::$_instance))
			self::$_instance = new self();
		return self::$_instance;
	}


	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone() {
		_doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?'), '1.0.0');
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?'), '1.0.0');
	}

	/**
	 * Installation.
	 * Runs on activation. Logs the version number and assigns a notice message to a WordPress option.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function install() {
	    //Create table for save user subscribe information;
        global $wpdb;
        $table_name = $wpdb->prefix.'wcmd_user_subscribe_info';
        if($wpdb->get_var("SHOW TABLE LIKE '$table_name'") != $table_name) {
            $sql = 'CREATE TABLE IF NOT EXISTS `' . $table_name . '` (
			`id` int(11) NOT NULL auto_increment,
			`email` varchar(60) default NULL, 
			UNIQUE KEY `id` (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;';
            $wpdb->query($sql);
        }
        // Save version number
		$this->_log_version_number();
	}

	/**
	 * Installation.
	 * Runs on deactivation. Delete the version number and assigns a notice message to a WordPress option.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function uninstall() {
		$this->_del_version_number();
	}

	/**
	 * Log the plugin version number.
	 * @access  private
	 * @since   1.0.0
	 * @return  void
	 */
	private function _log_version_number() {
		// Log the version number.
		update_option($this->token . '-version', $this->version);
	}


	/**
	 * Delete the plugin version number.
	 * @access  private
	 * @since   1.0.0
	 * @return  void
	 */
	private function _del_version_number() {
		// Delete the version number.
		delete_option($this->token . '-version');
	}
}