<?php
abstract class MainWPRemoteDestination
{
    public static $ENCRYPT = 'RDEncrypt';
    protected $object;

    public function __construct($pObject)
    {
        if (is_array($pObject)) $pObject = (object) $pObject;
        $this->object = $pObject;
    }

    public function getType()
    {
        return $this->object->type;
    }

    public function getTitle()
    {
        return $this->object->title;
    }

    public function getUserID()
    {
        return $this->object->userid;
    }
    /**
     * @param $pLocalbackupfile
     * @param $pType: full or not
     * @return mixed
     */
    public abstract function upload($pLocalbackupfile, $pType, $pSubfolder, $pRegexFile, $pSiteId = null, $pTaskId = null, $pUnique = null, $pTryResume = false);
    public abstract function getIdentifier();
    public abstract function limitFiles($ftp, $pLocalbackupfile, $pRegexFile, &$backupFiles, $dir = null);
    public abstract function test($fields = array());
    public abstract function save($fields = array());
    public abstract function buildUpdateForm();
    public abstract function buildCreateForm();

    /**
     * @param $pObject
     * @return MainWPRemoteDestination
     * @throws Exception
     */
    public static function buildRemoteDestination($pObject)
    {
        if (is_array($pObject)) $pObject = (object) $pObject;
        if ($pObject->type == 'ftp')
        {
            return new MainWPRemoteDestinationFTP($pObject);
        }
        else if ($pObject->type == 'amazon')
        {
            return new MainWPRemoteDestinationAmazon($pObject);
        }
        else if ($pObject->type == 'dropbox')
        {
            return new MainWPRemoteDestinationDropbox($pObject);
        }
        else if ($pObject->type == 'dropbox2')
        {
            return new MainWPRemoteDestinationDropbox2($pObject);
        }
        else if ($pObject->type == 'copy')
        {
            return new MainWPRemoteDestinationCopy($pObject);
        }
        else
        {
            throw new Exception('Unknown remote destination type');
        }
    }

    public function showTestButton()
    {
        return true;
    }

    public function showSaveButton()
    {
        return true;
    }

    public static function init()
    {
        MainWPRemoteDestinationDropbox2::init();
        MainWPRemoteDestinationCopy::init();

        add_action('mainwp_admin_menu', array('MainWPRemoteDestination', 'initMenu'));
    }

    public static function initMenu()
    {
        add_submenu_page('mainwp_tab', 'Remote Destination', '<div class="mainwp-hidden">Remote Destination</div>', 'read', 'MainWPRemoteDestination', array('MainWPRemoteDestination', 'render'));
    }

    public static function render()
    {
        if (isset($_REQUEST['oauth_token']) || isset($_REQUEST['?oauth_token']))
        {
            if (session_id() == '') session_start();
            if (isset($_REQUEST['oauth_token'])) $oauth_token = $_REQUEST['oauth_token'];
            else if (isset($_REQUEST['?oauth_token'])) $oauth_token = $_REQUEST['?oauth_token'];

            $_SESSION[$oauth_token] = $_REQUEST['oauth_verifier'];
            ?>
            <div style="text-align: center;"><a href="http://mainwp.com"><img src="http://mainwp.com/wp-content/uploads/2013/07/MainWP-Logo-1000-300x62.png"></a></div>
            <div style="width: 80%; margin-left: auto; margin-right: auto;">
            <div style="border: 4px Solid #fff; background: #7fb100; padding: .5em 1em; margin: 4em 0 0 0; text-align: center; -webkit-box-shadow: 0px 0px 10px 0px rgba(0, 0, 0, 0.25); -moz-box-shadow:    0px 0px 10px 0px rgba(0, 0, 0, 0.25); box-shadow:         0px 0px 10px 0px rgba(0, 0, 0, 0.25);">
                <p style="color: #fff; font-size: 32px;">Connection successful!</p>
                <p style="color: #fff; font-size: 16px;">Your MainWP dashboard is succeffully connected to the remote backup destination!</p>
            </div>
            <div style="text-align: center; margin-top: 5em;"><a href="https://mainwp.com"><img src="<?php plugins_url( 'images/remote-backups.png', dirname( __FILE__ ) );?>" height="100"></a></div>
            </div>
            <?php
            return;
        }
        ?>
            <div style="text-align: center;"><a href="http://mainwp.com"><img src="http://mainwp.com/wp-content/uploads/2013/07/MainWP-Logo-1000-300x62.png"></a></div>
            <div style="width: 80%; margin-left: auto; margin-right: auto;">
            <div style="border: 4px Solid #fff; background: #bb7239; padding: .5em 1em; margin: 4em 0 0 0; text-align: center; -webkit-box-shadow: 0px 0px 10px 0px rgba(0, 0, 0, 0.25); -moz-box-shadow:    0px 0px 10px 0px rgba(0, 0, 0, 0.25); box-shadow:         0px 0px 10px 0px rgba(0, 0, 0, 0.25);">
                <p style="color: #fff; font-size: 32px;">Connection Failed!</p>
                <p style="color: #fff; font-size: 16px;">Something went wrong with connecting your MainWP dashboard to the remote backup destination!</p>
            </div>
            <div style="text-align: center; margin-top: 5em;"><a href="https://mainwp.com"><img src="<?php plugins_url( 'images/remote-backups.png', dirname( __FILE__ ) );?>" height="100"></a></div>
            </div>
        <?php
    }
}