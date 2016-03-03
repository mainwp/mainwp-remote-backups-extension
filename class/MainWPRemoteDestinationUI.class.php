<?php

class MainWPRemoteDestinationUI
{
    public static function mainwp_backups_remote_get_destinations($remote_destinations, $what)
    {
        $remote_destinations = null;
        if (isset($what['task']) && MainWPRemoteDestinationUtility::ctype_digit($what['task'])) $remote_destinations = MainWPRemoteBackupDB::Instance()->getRemoteDestinationsByTaskId($what['task']);
        else if (isset($what['website']) && MainWPRemoteDestinationUtility::ctype_digit($what['website'])) $remote_destinations = MainWPRemoteBackupDB::Instance()->getRemoteDestinationsByWebsiteId($what['website']);

        return ($remote_destinations == null ? array() : $remote_destinations);
    }

    public static function mainwp_backups_remote_settings($what)
    {
        $remote_destinations = null;
        if (isset($what['task'])) $remote_destinations = MainWPRemoteBackupDB::Instance()->getRemoteDestinationsByTaskId($what['task']);
        else if (isset($what['website'])) $remote_destinations = MainWPRemoteBackupDB::Instance()->getRemoteDestinationsByWebsiteId($what['website']);

        $allRemoteDestinations = MainWPRemoteBackupDB::Instance()->getRemoteDestinationsForUser();
        $hasRemoteDestinations = (count($remote_destinations) > 0);

        self::renderRemoteDestinationRows($allRemoteDestinations, $remote_destinations, ((!isset($what['hide']) || $what['hide'] != 'no') && !$hasRemoteDestinations ? 'display: none;' : null));
    }

    public static function renderRemoteDestinationRows($allRemoteDestinations, $remote_destinations, $extraStyle = null)
    {
        ?>
        <tr class="mainwp_backup_destinations" <?php echo ($extraStyle != null ? 'style="'.$extraStyle.'"' : '') ?>>
             <th scope="row"><?php _e('Remote Backup Destinations:','mainwp'); ?></th>
             <td>
                 <div id="remote_backup_destination_dialog" title="Add a destination" style="display: none;">
                     <?php
                     ?>
                     <h3><?php _e('Select existing destination:','mainwp'); ?></h3>
                     <?php
                     if (is_array($allRemoteDestinations) && count($allRemoteDestinations) > 0)
                     {//todo: RS: Backups!
                         foreach ($allRemoteDestinations as $remoteDest)
                         {
                             $remoteDestObj = MainWPRemoteDestination::buildRemoteDestination($remoteDest);

                             ?>
                             <div class="backup_destination_cont settings">
                                  <input type="hidden" name="remote_destinationstemplate[]" class="remote_destination_id" value="<?php echo $remoteDest->id; ?>" title="<?php echo htmlentities($remoteDest->title); ?>" destination_type="<?php echo MainWPRemoteBackupSystem::getRemoteDestinationName($remoteDest->type); ?>"/>
                                  <input type="hidden" name="remote_destination_type[]" class="remote_destination_type" value="<?php echo $remoteDest->type; ?>"/>
                                  <div class="backup_destination_type" style="background-image: url('<?php echo plugins_url('images/'.$remoteDest->type.'.png', dirname(__FILE__)) ?>')"><?php echo MainWPRemoteBackupSystem::getRemoteDestinationName($remoteDest->type); ?></div>
                                  <div class="backup_destination_title"><?php echo $remoteDest->title; ?></div>
                                  <div class="backup_destination_settings"><i class="fa fa-cog fa-2x backup_destination_settings_open"></i></div>
                                  <div class="backup_destination_settings_panel">
                                      <div class="clear"></div>
                                      <?php $remoteDestObj->buildUpdateForm(); ?>
                                      <br />
                                      <a href="#" class="button backup_destination_test"><span class="text"><?php _e('Test Settings','mainwp'); ?></span> <span class="loading"><?php do_action('mainwp_renderImage', 'images/loading.gif', 'Loading', ''); ?></span></a> <input type="button" class="button-primary backup_destination_save" value="<?php _e('Save Settings','mainwp'); ?>" /> <input type="button" class="button backup_destination_delete" value="<?php _e('Delete Destination','mainwp'); ?>" />
                                      <div class="clear"></div>
                                  </div>
                              </div>
                             <?php
                         }
                     }
                     ?>
                     <div id="backup_destination_new_add_here"></div>

                     <h3><?php _e('Add a new destination:','mainwp'); ?></h3>

                     <div class="backup_destination_cont settings new">
                         <div class="backup_destination_type">+ <?php _e('Add new','mainwp'); ?></div>
                         <div class="backup_destination_title">FTP, Dropbox, S3, Copy.com</div>
                         <div class="backup_destination_new_cont_panel">
                             <div class="clear"></div>
                             <?php
                             $newRemoteDestinations = array(new MainWPRemoteDestinationAmazon(), new MainWPRemoteDestinationDropbox2(), new MainWPRemoteDestinationFTP(), new MainWPRemoteDestinationCopy());
                             foreach ($newRemoteDestinations as $newRemoteDestination)
                             {
                             ?>
                                <div class="backup_destination_new_cont newdetail">
                                    <input type="hidden" name="remote_destination_type[]" class="remote_destination_type" value="<?php echo $newRemoteDestination->getType(); ?>"/>
                                    <div class="backup_destination_type" style="background-image: url('<?php echo plugins_url('images/'.$newRemoteDestination->getType().'.png', dirname(__FILE__)) ?>')"><?php echo MainWPRemoteBackupSystem::getRemoteDestinationName($newRemoteDestination->getType()); ?></div>
                                    <div class="backup_destination_title"></div>
                                    <div class="backup_destination_settings"><input type="button" class="button backup_destination_add_new" value="+ <?php _e('Add','mainwp'); ?>" /></div>
                                    <div class="backup_destination_settings_panel">
                                        <div class="clear"></div>
                                        <?php $newRemoteDestination->buildCreateForm(); ?>
                                        <br />
                                        <a href="#" class="button backup_destination_test" <?php if (!$newRemoteDestination->showTestButton()) { echo 'style="display: none;"'; } ?>><span class="text">Test Settings</span> <span class="loading"><?php do_action('mainwp_renderImage', 'images/loading.gif', 'Loading', ''); ?></span></a>
                                        <input type="button" class="button-primary backup_destination_new_save" value="Save Destination" <?php if (!$newRemoteDestination->showSaveButton()) { echo 'style="display: none;"'; } ?> />
                                        <div class="clear"></div>
                                    </div>
                                </div>
                             <?php
                             }
                             ?>
                             <div class="clear"></div>
                         </div>
                     </div>
                 </div>
                 <div id="backup_destination_list">
                     <div class="backup_destination_excludecont template" style="display: none"><?php do_action('mainwp_renderImage', 'images/exclude.png', '', 'backup_destination_exclude', 22); ?></div>
             <?php
             if (!is_array($remote_destinations)) $remote_destinations = array();
             foreach ($remote_destinations as $remoteDest)
             {
                 ?>
                 <div class="backup_destination_cont">
                      <input type="hidden" name="remote_destinations[]" class="remote_destination_id" value="<?php echo $remoteDest->id; ?>" title="<?php echo htmlentities($remoteDest->title); ?>" destination_type="<?php echo MainWPRemoteBackupSystem::getRemoteDestinationName($remoteDest->type); ?>"/>
                      <div class="backup_destination_type" style="background-image: url('<?php echo plugins_url('images/'.$remoteDest->type.'.png', dirname(__FILE__)) ?>')"><?php echo MainWPRemoteBackupSystem::getRemoteDestinationName($remoteDest->type); ?></div>
                      <div class="backup_destination_title"><?php echo $remoteDest->title; ?></div>
                      <div class="backup_destination_excludecont"><?php do_action('mainwp_renderImage', 'images/exclude.png', '', 'backup_destination_exclude', 22); ?></div>
                  </div>
                 <?php
             }
                 ?>
                 </div>
                 <input type="button" name="addremotebackupdestination" id="addremotebackupdestination" class="button" value="+ <?php _e('Add remote backup destination','mainwp'); ?>" />
             </td>
         </tr>
                 <?php
    }
}