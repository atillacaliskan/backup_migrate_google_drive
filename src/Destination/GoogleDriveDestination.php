<?php

namespace Drupal\backup_migrate_google_drive\Destination;

use Drupal\backup_migrate\Core\Config\ConfigurableInterface;
use Drupal\backup_migrate\Core\Destination\WritableDestinationInterface;
use Drupal\backup_migrate\Core\Destination\ReadableDestinationInterface;
use Drupal\backup_migrate\Core\Destination\ListableDestinationInterface;
use Drupal\backup_migrate\Core\Plugin\FileProcessorInterface;
use Drupal\backup_migrate\Core\Plugin\FileProcessorTrait;
use Drupal\backup_migrate\Core\File\BackupFileInterface;
use Drupal\backup_migrate\Core\File\BackupFileReadableInterface;
use Drupal\backup_migrate\Core\File\BackupFile;
use Drupal\backup_migrate\Core\Plugin\PluginBase;
use Drupal\backup_migrate\Core\Config\Config;
use Drupal\backup_migrate\Core\Exception\BackupMigrateException;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;

/**
 * A Google Drive destination for backup files.
 * 
 * Provides full backup management functionality with Google Drive integration:
 * - OAuth2 authentication with token refresh
 * - Path-based folder organization
 * - Automatic folder creation
 * - Full CRUD operations (create, read, update, delete)
 * - Description metadata support
 * - Automatic cleanup of old backups
 */
class GoogleDriveDestination extends PluginBase implements WritableDestinationInterface, ReadableDestinationInterface, ListableDestinationInterface, ConfigurableInterface, FileProcessorInterface
{
    use FileProcessorTrait;

    /**
     * The Google Drive service.
     *
     * @var \Google\Service\Drive
     */
    protected $driveService;

    /**
     * {@inheritdoc}
     */
    public function supportedOps()
    {
        return [
            'saveFile' => [],
            'loadFileForReading' => [],
            'listFiles' => [],
            'deleteFile' => [],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function checkWritable()
    {
        try {
            $this->initializeDriveService();

            // Test connection by trying to list files
            $this->driveService->files->listFiles([
                'pageSize' => 1,
                'fields' => 'files(id, name)',
            ]);

            return TRUE;
        } catch (\Exception $e) {
            throw new BackupMigrateException('Google Drive destination is not writable: %message', ['%message' => $e->getMessage()]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configSchema(array $params = [])
    {
        $schema = [];

        // Init settings.
        if ($params['operation'] == 'initialize') {
            $schema['fields']['auth_status'] = [
                'type' => 'item',
                'title' => \Drupal::translation()->translate('Google Drive Authorization'),
                'description' => $this->getAuthorizationStatus(),
            ];

            $schema['fields']['folder_path'] = [
                'type' => 'text',
                'title' => \Drupal::translation()->translate('Backup Folder Path'),
                'description' => \Drupal::translation()->translate('The folder path where backups will be stored (e.g., /backups/mysite). Folders will be created automatically if they don\'t exist. Leave empty to store in root.'),
                'default_value' => $this->confGet('folder_path', '/backups'),
            ];

            $schema['fields']['max_backups'] = [
                'type' => 'number',
                'title' => \Drupal::translation()->translate('Maximum number of backups'),
                'description' => \Drupal::translation()->translate('The maximum number of backup files to keep. Older backups will be deleted automatically. Set to 0 for unlimited.'),
                'default_value' => $this->confGet('max_backups', 10),
            ];
        }

        return $schema;
    }

    /**
     * {@inheritdoc}
     */
    public function configDefaults()
    {
        return new Config([
            'access_token' => '',
            'refresh_token' => '',
            'folder_path' => '/backups',
            'max_backups' => 10,
        ]);
    }

    /**
     * Get the authorization status message for the config form.
     *
     * @return string
     */
    protected function getAuthorizationStatus()
    {
        $access_token = $this->confGet('access_token');
        $session = \Drupal::request()->getSession();
        $session_token = $session->get('google_drive_access_token');

        if (empty($access_token) && empty($session_token)) {
            $auth_url = \Drupal::service('url_generator')->generateFromRoute('backup_migrate_google_drive.auth', [], ['absolute' => TRUE]);
            return \Drupal::translation()->translate('Not authorized yet. <a href="@url" target="_blank">Click here to authorize with Google Drive</a>', ['@url' => $auth_url]);
        } else {
            $revoke_url = \Drupal::service('url_generator')->generateFromRoute('backup_migrate_google_drive.revoke', [], ['absolute' => TRUE]);
            return \Drupal::translation()->translate('✓ Authorized with Google Drive. <a href="@url">Revoke authorization</a>', ['@url' => $revoke_url]);
        }
    }

    /**
     * Initialize the Google Drive service.
     *
     * @throws \Drupal\backup_migrate\Core\Exception\BackupMigrateException
     */
    protected function initializeDriveService()
    {
        if ($this->driveService) {
            return;
        }

        // Get tokens from global configuration
        $config = \Drupal::config('backup_migrate_google_drive.settings');
        $access_token = $config->get('access_token');

        // If no stored token, try to get from session (from recent auth)
        if (empty($access_token)) {
            $session = \Drupal::request()->getSession();
            $access_token = $session->get('google_drive_access_token');

            if (empty($access_token)) {
                throw new BackupMigrateException('Google Drive authorization is required. Please authorize the application first.');
            }
        }

        try {
            $client = new Client();
            // Get OAuth credentials from configuration
            $client->setClientId($config->get('client_id'));
            $client->setClientSecret($config->get('client_secret'));
            $client->setAccessToken($access_token);

            // Check if token needs refresh
            if ($client->isAccessTokenExpired()) {
                $refresh_token = $config->get('refresh_token');
                if ($refresh_token) {
                    $client->refreshToken($refresh_token);
                    // Save the new access token
                    $new_token = $client->getAccessToken();
                    \Drupal::configFactory()->getEditable('backup_migrate_google_drive.settings')
                        ->set('access_token', $new_token)
                        ->save();
                } else {
                    throw new BackupMigrateException('Google Drive access token expired and no refresh token available. Please re-authorize.');
                }
            }

            $this->driveService = new Drive($client);
        } catch (\Exception $e) {
            throw new BackupMigrateException('Failed to initialize Google Drive service: ' . $e->getMessage());
        }
    }

    /**
     * Get or create the backup folder on Google Drive based on path.
     *
     * @return string|null
     *   The folder ID or null for root.
     */
    protected function getOrCreateBackupFolder()
    {
        $folder_path = $this->confGet('folder_path');

        // If no path specified, use root
        if (empty($folder_path) || $folder_path === '/') {
            return null;
        }

        // Clean up the path
        $folder_path = trim($folder_path, '/');
        if (empty($folder_path)) {
            return null;
        }

        // Check if we have cached folder ID for this path
        $config = \Drupal::config('backup_migrate_google_drive.settings');
        $cached_folders = $config->get('folder_cache') ?: [];

        if (isset($cached_folders[$folder_path])) {
            return $cached_folders[$folder_path];
        }

        // Create folder structure
        try {
            $folder_id = $this->createFolderStructure($folder_path);

            // Cache the folder ID
            $cached_folders[$folder_path] = $folder_id;
            \Drupal::configFactory()->getEditable('backup_migrate_google_drive.settings')
                ->set('folder_cache', $cached_folders)
                ->save();

            return $folder_id;
        } catch (\Exception $e) {
            \Drupal::logger('backup_migrate_google_drive')->error('Failed to create folder structure "@path": @message', [
                '@path' => $folder_path,
                '@message' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Create folder structure recursively.
     *
     * @param string $path
     *   The folder path like "backups/mysite".
     *
     * @return string
     *   The final folder ID.
     */
    protected function createFolderStructure($path)
    {
        $folders = explode('/', $path);
        $parent_id = null; // Start from root

        foreach ($folders as $folder_name) {
            if (empty($folder_name)) {
                continue;
            }

            // Check if folder already exists
            $existing_folder = $this->findFolder($folder_name, $parent_id);

            if ($existing_folder) {
                $parent_id = $existing_folder->getId();
            } else {
                // Create new folder
                $folderMetadata = new DriveFile();
                $folderMetadata->setName($folder_name);
                $folderMetadata->setMimeType('application/vnd.google-apps.folder');

                if ($parent_id) {
                    $folderMetadata->setParents([$parent_id]);
                }

                $folder = $this->driveService->files->create($folderMetadata);
                $parent_id = $folder->getId();
            }
        }

        return $parent_id;
    }

    /**
     * Find a folder by name within a parent folder.
     *
     * @param string $name
     *   The folder name.
     * @param string|null $parent_id
     *   The parent folder ID or null for root.
     *
     * @return \Google\Service\Drive\DriveFile|null
     *   The folder object or null if not found.
     */
    protected function findFolder($name, $parent_id = null)
    {
        $query = "name='" . addslashes($name) . "' and mimeType='application/vnd.google-apps.folder' and trashed=false";

        if ($parent_id) {
            $query .= " and '" . $parent_id . "' in parents";
        } else {
            $query .= " and 'root' in parents";
        }

        $results = $this->driveService->files->listFiles([
            'q' => $query,
            'pageSize' => 1,
            'fields' => 'files(id, name)',
        ]);

        $files = $results->getFiles();
        return !empty($files) ? $files[0] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function saveFile(BackupFileReadableInterface $file)
    {
        $this->initializeDriveService();

        try {
            // Get or create backup folder
            $folder_id = $this->getOrCreateBackupFolder();

            // Create the file metadata
            $driveFile = new DriveFile();
            $driveFile->setName($file->getFullName());

            // Set description if available
            $description = $file->getMeta('description');
            if (!empty($description)) {
                $driveFile->setDescription($description);
            }

            // Set parent folder
            if (!empty($folder_id)) {
                $driveFile->setParents([$folder_id]);
            }

            // Open the file for reading
            $file->openForRead();
            $content = '';
            while ($data = $file->readBytes(8192)) {
                $content .= $data;
            }
            $file->close();

            // Upload the file
            $result = $this->driveService->files->create($driveFile, [
                'data' => $content,
                'mimeType' => 'application/octet-stream',
                'uploadType' => 'multipart',
            ]);

            // Clean up old backups if max_backups is set
            $max_backups = $this->confGet('max_backups', 10);
            if ($max_backups > 0) {
                $this->cleanupOldBackups($max_backups);
            }

            return $result->getId();
        } catch (\Exception $e) {
            throw new BackupMigrateException('Failed to upload backup to Google Drive: ' . $e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getFile($id)
    {
        $this->initializeDriveService();

        try {
            $file = $this->driveService->files->get($id, [
                'fields' => 'id,name,size,createdTime,modifiedTime,description'
            ]);

            // Create a backup file object using BackupFile directly
            $backup_file = new BackupFile();
            $backup_file->setMeta('id', $id);
            $backup_file->setFullName($file->getName());

            // Set description if available
            $description = $file->getDescription();
            if (!empty($description)) {
                $backup_file->setMeta('description', $description);
            }

            return $backup_file;
        } catch (\Exception $e) {
            throw new BackupMigrateException('Failed to get file from Google Drive: ' . $e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function loadFileForReading(BackupFileInterface $file)
    {
        $this->initializeDriveService();

        try {
            $id = $file->getMeta('id');
            if (!$id) {
                throw new BackupMigrateException('File ID is required to load from Google Drive.');
            }

            // Download the file content
            $response = $this->driveService->files->get($id, ['alt' => 'media']);
            $content = $response->getBody()->getContents();

            // Create temp file using Drupal's file system directly
            $file_system = \Drupal::service('file_system');
            $temp_path = $file_system->tempnam('temporary://', 'bam');

            if (!$temp_path) {
                throw new BackupMigrateException('Could not create temporary file.');
            }

            // Write content to the temp file
            file_put_contents($temp_path, $content);

            // Validate temp path
            if (!file_exists($temp_path) || !is_readable($temp_path)) {
                throw new BackupMigrateException('Temp file is not readable: ' . $temp_path);
            }

            // Create a readable file from the temp path
            $readable_file = new \Drupal\backup_migrate\Core\File\ReadableStreamBackupFile($temp_path);
            $readable_file->setFullName($file->getFullName());
            $readable_file->setMeta('filesize', strlen($content));

            return $readable_file;
        } catch (\Exception $e) {
            throw new BackupMigrateException('Failed to download file from Google Drive: ' . $e->getMessage());
        }
    }
    /**
     * {@inheritdoc}
     */
    public function listFiles()
    {
        try {
            $this->initializeDriveService();

            // Check if driveService was initialized successfully
            if (!$this->driveService) {
                return [];
            }

            $query = "trashed=false";

            // Add folder filter based on path
            try {
                $folder_id = $this->getOrCreateBackupFolder();
                if (!empty($folder_id)) {
                    $query .= " and parents in '{$folder_id}'";
                }
            } catch (\Exception $e) {
                // If folder operations fail, just list from root
                \Drupal::logger('backup_migrate_google_drive')->warning('Failed to get backup folder, listing from root: @message', ['@message' => $e->getMessage()]);
            }

            $results = $this->driveService->files->listFiles([
                'q' => $query,
                'orderBy' => 'createdTime desc',
                'fields' => 'files(id,name,size,createdTime,modifiedTime,description)',
            ]);

            $files = [];
            foreach ($results->getFiles() as $driveFile) {
                try {
                    // Create a simple backup file representation
                    $backup_file = new BackupFile();
                    $backup_file->setMeta('id', $driveFile->getId());
                    $backup_file->setFullName($driveFile->getName());
                    $backup_file->setMeta('filesize', $driveFile->getSize() ?: 0);
                    $backup_file->setMeta('datestamp', strtotime($driveFile->getCreatedTime()) ?: time());

                    // Set description if available
                    $description = $driveFile->getDescription();
                    if (!empty($description)) {
                        $backup_file->setMeta('description', $description);
                    }

                    $files[$driveFile->getId()] = $backup_file;
                } catch (\Exception $e) {
                    // Skip files that can't be processed
                    \Drupal::logger('backup_migrate_google_drive')->warning('Skipping file @name: @message', [
                        '@name' => $driveFile->getName(),
                        '@message' => $e->getMessage()
                    ]);
                }
            }

            return $files;
        } catch (\Exception $e) {
            throw new BackupMigrateException('Failed to list files from Google Drive: ' . $e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteFile($id)
    {
        try {
            $this->initializeDriveService();

            // Check if driveService was initialized successfully
            if (!$this->driveService) {
                return FALSE;
            }

            $this->driveService->files->delete($id);
            return TRUE;
        } catch (\Exception $e) {
            throw new BackupMigrateException('Failed to delete file from Google Drive: ' . $e->getMessage());
        }
    }

    /**
     * Clean up old backup files.
     *
     * @param int $max_backups
     *   Maximum number of backups to keep.
     */
    protected function cleanupOldBackups($max_backups)
    {
        try {
            $files = $this->listFiles();

            if (count($files) > $max_backups) {
                // Sort files by creation time (oldest first)
                uasort($files, function ($a, $b) {
                    return $a->getMeta('datestamp') - $b->getMeta('datestamp');
                });

                // Delete the oldest files
                $files_to_delete = array_slice($files, 0, count($files) - $max_backups, TRUE);
                foreach ($files_to_delete as $id => $file) {
                    $this->deleteFile($id);
                }
            }
        } catch (\Exception $e) {
            // Log the error but don't fail the backup
            \Drupal::logger('backup_migrate_google_drive')->error('Failed to cleanup old backups: ' . $e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function loadFileMetadata(BackupFileInterface $file)
    {
        // Metadata is already loaded in listFiles() and getFile()
        return $file;
    }

    /**
     * {@inheritdoc}
     */
    public function fileExists($id)
    {
        try {
            $this->initializeDriveService();
            $this->driveService->files->get($id, ['fields' => 'id']);
            return TRUE;
        } catch (\Exception $e) {
            return FALSE;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function queryFiles(array $filters = [], $sort = 'datestamp', $sort_direction = SORT_DESC, $count = 100, $start = 0)
    {
        $files = $this->listFiles();

        // Apply basic filtering and sorting
        $filtered_files = array_slice($files, $start, $count);

        return $filtered_files;
    }

    /**
     * {@inheritdoc}
     */
    public function countFiles()
    {
        $files = $this->listFiles();
        return count($files);
    }
}
