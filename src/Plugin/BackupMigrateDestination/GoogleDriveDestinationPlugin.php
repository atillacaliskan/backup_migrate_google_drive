<?php

namespace Drupal\backup_migrate_google_drive\Plugin\BackupMigrateDestination;

use Drupal\backup_migrate\Drupal\EntityPlugins\DestinationPluginBase;

/**
 * Defines a Google Drive destination plugin.
 *
 * @BackupMigrateDestinationPlugin(
 *   id = "GoogleDrive",
 *   title = @Translation("Google Drive"),
 *   description = @Translation("Back up to Google Drive cloud storage. Configure at Settings → Google Drive tab."),
 *   wrapped_class = "\Drupal\backup_migrate_google_drive\Destination\GoogleDriveDestination"
 * )
 */
class GoogleDriveDestinationPlugin extends DestinationPluginBase {}
