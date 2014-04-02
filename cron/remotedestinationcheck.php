<?php
include_once('bootstrap.php');

if (MainWPRemoteBackupExtension::isActivated()) MainWPRemoteBackupSystem::mainwp_remote_backup_extension_cronremotedestinationcheck_action();