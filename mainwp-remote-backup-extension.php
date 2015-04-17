<?php
/*
Plugin Name: MainWP Remote Backup Extension
Plugin URI: http://extensions.mainwp.com
Description: MainWP Remote Backup Extension is an extension for the MainWP plugin that enables you store your backups on different off site locations.
Version: 0.0.8
Author: MainWP
Author URI: http://mainwp.com
Icon URI: http://extensions.mainwp.com/wp-content/uploads/2014/01/mainwp-remote-backups-ext-icon.png
Documentation URI: http://docs.mainwp.com/category/mainwp-extensions/mainwp-remote-backups-extension/
*/

if (!defined('MAINWP_REMOTE_BACKUP_PLUGIN_FILE')) {
    define('MAINWP_REMOTE_BACKUP_PLUGIN_FILE', __FILE__);
}

class MainWPRemoteBackupExtension
{
    public static $instance = null;
    public  $plugin_handle = "mainwp-remote-backup-extension";
    protected $plugin_url;
    protected $mainWPRemoteBackup;
    private $plugin_slug;

    static function Instance()
    {
        if (MainWPRemoteBackupExtension::$instance == null) MainWPRemoteBackupExtension::$instance = new MainWPRemoteBackupExtension();
        return MainWPRemoteBackupExtension::$instance;
    }

    static function isActivated()
    {
        return self::$instance != null;
    }

    public function __construct()
    {
		$this->plugin_url = plugin_dir_url(__FILE__);
        $this->plugin_slug = plugin_basename(__FILE__);

        add_action('init', array(&$this, 'init'));
        add_filter('plugin_row_meta', array(&$this, 'plugin_row_meta'), 10, 2);

        MainWPRemoteBackupDB::Instance()->install();
        MainWPRemoteDestination::init();
        $this->mainWPRemoteBackup = new MainWPRemoteBackupSystem();

        add_action('admin_init', array(&$this, 'admin_init'));
        add_action('mainwp_backups_remote_settings', array('MainWPRemoteDestinationUI', 'mainwp_backups_remote_settings'));
        add_filter('mainwp_backups_remote_get_destinations', array('MainWPRemoteDestinationUI', 'mainwp_backups_remote_get_destinations'), 10, 2);

        add_action('mainwp_remote_backup_extension_cronremotedestinationcheck_action', array('MainWPRemoteBackupSystem', 'mainwp_remote_backup_extension_cronremotedestinationcheck_action'));

        $useWPCron = (get_option('mainwp_wp_cron') === false) || (get_option('mainwp_wp_cron') == 1);
        if (($sched = wp_next_scheduled('mainwp_remote_backup_extension_cronremotedestinationcheck_action')) == false)
        {
            if ($useWPCron) wp_schedule_event(time(), 'daily', 'mainwp_remote_backup_extension_cronremotedestinationcheck_action');
        }
        else
        {
            if (!$useWPCron) wp_unschedule_event($sched, 'mainwp_remote_backup_extension_cronremotedestinationcheck_action');
        }
	}

   
    public function init()
    {
        $this->mainWPRemoteBackup->init();
    }

    public function plugin_row_meta($plugin_meta, $plugin_file)
    {
        if ($this->plugin_slug != $plugin_file) return $plugin_meta;

        $plugin_meta[] = '<a href="?do=checkUpgrade" title="Check for updates.">Check for updates now</a>';
        return $plugin_meta;
    }

    public function admin_init()
    {
        wp_enqueue_style('mainwp-remote-backup-extension-css', $this->plugin_url . 'css/mainwp-remote-backup.css');
        wp_enqueue_script('mainwp-remote-backup-extension-js', $this->plugin_url . 'js/mainwp-remote-backup.js');

        wp_localize_script('mainwp-remote-backup-extension-js', 'mainwp_remote_backup_security_nonces', $this->mainWPRemoteBackup->security_nonces);
    }
}


function mainwp_remote_backup_extension_autoload($class_name)
{
    if (stristr($class_name, '\\'))
    {
        $class_name = str_replace('\\', DIRECTORY_SEPARATOR, $class_name);
        $file = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . str_replace(basename(__FILE__), '', plugin_basename(__FILE__)) . 'libs' . DIRECTORY_SEPARATOR . $class_name . '.php';
        if (file_exists($file))
        {
            require_once($file);
        }
        return;
    }

    $allowedLoadingTypes = array('class');

    foreach ($allowedLoadingTypes as $allowedLoadingType)
    {
        $class_file = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . str_replace(basename(__FILE__), '', plugin_basename(__FILE__)) . $allowedLoadingType . DIRECTORY_SEPARATOR . $class_name . '.' . $allowedLoadingType . '.php';
        if (file_exists($class_file))
        {
            require_once($class_file);
        }
    }
}

if (function_exists('spl_autoload_register'))
{
    spl_autoload_register('mainwp_remote_backup_extension_autoload');
}
else
{
    function __autoload($class_name)
    {
        mainwp_remote_backup_extension_autoload($class_name);
    }
}

register_activation_hook(__FILE__, 'remote_backup_extension_activate');
register_deactivation_hook(__FILE__, 'remote_backup_extension_deactivate');
function remote_backup_extension_activate()
{   
    update_option('mainwp_remote_backup_extension_activated', 'yes');
    $extensionActivator = new MainWPRemoteBackupExtensionActivator();
    $extensionActivator->activate();
}
function remote_backup_extension_deactivate()
{   
    $extensionActivator = new MainWPRemoteBackupExtensionActivator();
    $extensionActivator->deactivate();
}

class MainWPRemoteBackupExtensionActivator
{
    protected $mainwpMainActivated = false;
    protected $childEnabled = false;
    protected $childKey = false;
    protected $childFile;
    protected $plugin_handle = "mainwp-remote-backup-extension";
    protected $product_id = "MainWP Remote Backup Extension"; 
    protected $software_version = "0.0.8";   
   
    public function __construct()
    {
        $this->childFile = __FILE__;
        add_filter('mainwp-getextensions', array(&$this, 'get_this_extension'));
        $this->mainwpMainActivated = apply_filters('mainwp-activated-check', false);

        if ($this->mainwpMainActivated !== false)
        {
            $this->activate_this_plugin();
        }
        else
        {
            add_action('mainwp-activated', array(&$this, 'activate_this_plugin'));
        }
        add_action('admin_init', array(&$this, 'admin_init'));
        add_action('admin_notices', array(&$this, 'mainwp_error_notice'));
    }

    function admin_init() {
        if (get_option('mainwp_remote_backup_extension_activated') == 'yes')
        {
            delete_option('mainwp_remote_backup_extension_activated');
            wp_redirect(admin_url('admin.php?page=Extensions'));
            return;
        }        
    }
    
    function get_this_extension($pArray)
    {
        $pArray[] = array('plugin' => __FILE__, 'api' => $this->plugin_handle, 'mainwp' => true, 'callback' => array(&$this, 'settings'), 'apiManager' => true);
        return $pArray;
    }

    function settings()
    {
        do_action('mainwp-pageheader-extensions', __FILE__);
        if ($this->childEnabled)
        {
            ?>
            <?php self::QSGRemoteBackups(); ?>
            <div class="mainwp_info-box">
                    <?php _e('This extension does not have the settings page. It just adds specific options to the backup feature. Check this <a href="http://docs.mainwp.com/backup-remote-destinations/" target="_blank">document</a> if you need help with this extension.','mainwp'); ?>
            </div>
            <?php
        }
        else
        {
                ?><div class="mainwp_info-box-yellow"><strong><?php _e("The Extension has to be enabled to change the settings."); ?></strong></div><?php
        }
        do_action('mainwp-pagefooter-extensions', __FILE__);
    }

    function QSGRemoteBackups() {
        $plugin_data =  get_plugin_data( MAINWP_REMOTE_BACKUP_PLUGIN_FILE, false );         
        $description = $plugin_data['Description'];
        $extraHeaders = array('DocumentationURI' => 'Documentation URI');
        $file_data = get_file_data(MAINWP_REMOTE_BACKUP_PLUGIN_FILE, $extraHeaders);
        $documentation_url  = $file_data['DocumentationURI'];
        ?>
        <div  class="mainwp_ext_info_box" id="rb-pth-notice-box">
            <div class="mainwp-ext-description"><?php echo $description; ?></div><br/>
            <b><?php echo __("Need Help?"); ?></b> <?php echo __("Review the Extension"); ?> <a href="<?php echo $documentation_url; ?>" target="_blank"><i class="fa fa-book"></i> <?php echo __('Documentation'); ?></a>. 
                    <a href="#" id="mainwp-rb-quick-start-guide"><i class="fa fa-info-circle"></i> <?php _e('Show Quick Start Guide','mainwp'); ?></a></div>
                    <div  class="mainwp_ext_info_box" id="mainwp-rb-tips" style="color: #333!important; text-shadow: none!important;">
                      <span><a href="#" class="mainwp-show-tut" number="1"><i class="fa fa-book"></i> <?php _e('Dropbox','mainwp') ?></a>&nbsp;&nbsp;&nbsp;&nbsp;<a href="#" class="mainwp-show-tut"  number="2"><i class="fa fa-book"></i> <?php _e('Copy.com','mainwp') ?></a>&nbsp;&nbsp;&nbsp;&nbsp;<a href="#" class="mainwp-show-tut"  number="3"><i class="fa fa-book"></i> <?php _e('Amazon S3','mainwp') ?></a>&nbsp;&nbsp;&nbsp;&nbsp;<a href="#" class="mainwp-show-tut"  number="4"><i class="fa fa-book"></i> <?php _e('FTP','mainwp') ?></a></span><span><a href="#" id="mainwp-rb-tips-dismiss" style="float: right;"><i class="fa fa-times-circle"></i> <?php _e('Dismiss','mainwp'); ?></a></span>
                      <div class="clear"></div>
                      <div id="mainwp-rb-tuts">
                        <div class="mainwp-rb-tut" number="1">
                            <h3>Dropbox</h3> 
                            <p>When creating a backup or backup task, to use the Dropbox as a remote backup destination:</p>
                            <ul>
                                <li>Click the Remote Destination button in the "Store Backup in" option</li>
                                <li>After popup appears, click the Add New. External source options will show. To select the Dropbox, click the Add button.</li>
                                <li>Two new options will appear. Here you need to enter destination Title and Directory. Destination Title assigned here is used only for easier management in cases you have set multiple sources. If you want you can leave it as it is (New Dropbox Destination). In the Directory field enter a name for the default backups directory.</li>
                                <li>When ready, click the Connect to Dropbox button. Click on this button will open the Dropbox login screen, enter your login details and click the Sign In button. <strong>In case this window doesnâ€™t open, be sure to turn off your popup blocker.</strong></li>
                                <li>Once you Sign In, Dropbox will ask you if you want to allow MainWP to access your Dropbox. Click the Allow button.</li>
                                <li>After you get the success message, return to dashboard and click the Yes, Iâ€™ve authorized MainWP to Dropbox button.</li>
                                <li>Click the Test Settings button, if it returns success message click the Save Destination button.</li>
                            </ul>
                        </div>
                        <div class="mainwp-rb-tut"  number="2">
                            <h3>Copy.com</h3>       
                            <p>When creating a backup or backup task, to use the Copy.com as a remote backup destination:</p>
                            <ul>
                                <li>Click the Remote Destination button in the "Store Backup in" option</li>
                                <li>After popup appears, click the Add New. External source options will show. To select the Copy.com, click the Add button.</li>
                                <li>Two new options will appear. Here you need to enter destination Title and Directory. Destination Title assigned here is used only for easier management in cases you have set multiple sources. If you want you can leave it as it is (New Copy.com Destination). In the Directory field enter a name for the default backups directory.</li>
                                <li>When ready, click the Connect to Copy.com button. Click on this button will open the Copy.com login screen, enter your login details and click the Sign In button. <strong>In case this window doesnâ€™t open, be sure to turn off your popup blocker.</strong></li>
                                <li>Once you Sign In, Copy.com will ask you if you want to allow MainWP to access your Copy.com. Click the Allow button.</li>
                                <li>After you get the success message, return to dashboard and click the Yes, Iâ€™ve authorized MainWP to Copy.com button.</li>
                                <li>Click the Test Settings button, if it returns success message click the Save Destination button.</li>
                            </ul>
                        </div>
                        <div class="mainwp-rb-tut"  number="3">
                            <h3>Amazon S3</h3>  
                            <p>When creating a backup or backup task, to use the Amazon S3 as a remote backup destination:</p>
                            <ul>
                                <li>Click the Remote Destination button in the "Store Backup in" option</li>
                                <li>After choosing Remote Destination for keeping your backups, to select the Amazon S3, click the Add button next to the Amazon icon.</li>
                                <li>Settings fields will appear, here you need to provide a few details for proper use of the external source.</li>
                                <li>
                                    <ul>
                                        <li>Destination title, something that will help you to manage your locations easier in future;</li>
                                        <li>Access Key ID and Secret Key, provided by Amazon in your account;</li>
                                        <li>Bucket, default backups bucket;</li>
                                        <li>Sub-directory.</li>
                                    </ul>
                                </li>
                                <li>Once you have added all necessary info, Click the Test Settings button. If it returns the success message, click the Save Settings button and you are ready to use your Amazon S3 bucket.</li>
                            </ul>
                        </div>
                        <div class="mainwp-rb-tut"  number="4">
                            <h3>FTP</h3>
                            <p>When creating a backup or backup task, to use the FTP as a remote backup destination:</p>
                            <ul>
                                <li>Click the Remote Destination button in the "Store Backup in" option</li>
                                <li>After choosing Remote Destination for keeping your backups, to use the remote FTP location, click the Add button next to the FTP icon.</li>
                                <li>Settings fields will appear, you need to enter following:</li>
                                <li>
                                    <ul>
                                        <li>Title</li>
                                        <li>Server address</li>
                                        <li>Server port</li>
                                        <li>Username</li>
                                        <li>Password</li>
                                        <li>Remote path</li>
                                    </ul>
                                </li>
                                <li>Also you have options to use SSL and Active Mode. When done with settings, use the Test Settings button to check if you have entered correct info. If the success message is returned, click the Save Settings button and you are ready to go.</li>
                            </ul>
                        </div>                        
                      </div>
                    </div>
        <?php
    }

    function activate_this_plugin()
    {
        $this->mainwpMainActivated = apply_filters('mainwp-activated-check', $this->mainwpMainActivated);

        $this->childEnabled = apply_filters('mainwp-extension-enabled-check', __FILE__);
        if (!$this->childEnabled) return;

        $this->childEnabled = apply_filters('mainwp-extension-enabled-check', __FILE__);
        if (!$this->childEnabled) return;

        $this->childKey = $this->childEnabled['key'];

        if (function_exists("mainwp_current_user_can")&& !mainwp_current_user_can("extension", "mainwp-remote-backup-extension"))
            return;

        new MainWPRemoteBackupExtension();
    }

    public function getChildKey()
    {
        return $this->childKey;
    }

    function mainwp_error_notice()
    {
        global $current_screen;
        if ($current_screen->parent_base == 'plugins' && $this->mainwpMainActivated == false)
        {
            echo '<div class="error"><p>MainWP Remote Backup Extension ' . __('requires <a href="http://mainwp.com/" target="_blank">MainWP</a> Plugin to be activated in order to work. Please install and activate <a href="http://mainwp.com/" target="_blank">MainWP</a> first.') . '</p></div>';
        }
    }
    
    public function update_option($option_name, $option_value)
    {
        $success = add_option($option_name, $option_value, '', 'no');

         if (!$success)
         {
             $success = update_option($option_name, $option_value);
         }

         return $success;
    }  
    
    public function activate() {                          
        $options = array (  'product_id' => $this->product_id,
                            'activated_key' => 'Deactivated',  
                            'instance_id' => apply_filters('mainwp-extensions-apigeneratepassword', 12, false),                            
                            'software_version' => $this->software_version
                        );               
        $this->update_option($this->plugin_handle . "_APIManAdder", $options);
    } 
    
    public function deactivate() {                                 
        $this->update_option($this->plugin_handle . "_APIManAdder", '');
    } 	    
}

function mainwp_remote_backup_extension_dir()
{
    return WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . dirname(plugin_basename(__FILE__)) . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR;
}

new MainWPRemoteBackupExtensionActivator();
