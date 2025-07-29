<?php

namespace Drupal\backup_migrate_google_drive\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;

/**
 * Controller for Google Drive authentication.
 */
class GoogleAuthController extends ControllerBase
{

    /**
     * Redirect to Google OAuth authorization page.
     */
    public function authorize()
    {
        try {
            $config = \Drupal::config('backup_migrate_google_drive.settings');
            $client_id = $config->get('client_id');
            $client_secret = $config->get('client_secret');

            if (empty($client_id) || empty($client_secret)) {
                $this->messenger()->addError($this->t('OAuth credentials not configured. Please set up your Google credentials first.'));
                return $this->redirect('backup_migrate_google_drive.settings');
            }

            $client = new GoogleClient();
            $client->setClientId($client_id);
            $client->setClientSecret($client_secret);
            $client->setRedirectUri(Url::fromRoute('backup_migrate_google_drive.callback', [], ['absolute' => TRUE])->toString());
            $client->addScope(GoogleDrive::DRIVE_FILE);
            $client->setAccessType('offline');
            $client->setPrompt('consent');

            $auth_url = $client->createAuthUrl();
            return new TrustedRedirectResponse($auth_url);
        } catch (\Exception $e) {
            $this->messenger()->addError($this->t('Failed to create authorization URL: @error', ['@error' => $e->getMessage()]));
            return $this->redirect('backup_migrate_google_drive.settings');
        }
    }

    /**
     * Handle the callback from Google OAuth.
     */
    public function callback(Request $request)
    {
        $code = $request->query->get('code');
        $error = $request->query->get('error');

        if ($error) {
            $this->messenger()->addError($this->t('Authorization failed: @error', ['@error' => $error]));
            return $this->redirect('entity.backup_migrate_destination.collection');
        }

        if (!$code) {
            $this->messenger()->addError($this->t('No authorization code received.'));
            return $this->redirect('entity.backup_migrate_destination.collection');
        }

        try {
            $config = \Drupal::config('backup_migrate_google_drive.settings');
            $client_id = $config->get('client_id');
            $client_secret = $config->get('client_secret');

            $client = new GoogleClient();
            $client->setClientId($client_id);
            $client->setClientSecret($client_secret);
            $client->setRedirectUri(Url::fromRoute('backup_migrate_google_drive.callback', [], ['absolute' => TRUE])->toString());

            $token = $client->fetchAccessTokenWithAuthCode($code);

            if (isset($token['error'])) {
                throw new \Exception($token['error_description'] ?? $token['error']);
            }

            // Store tokens in Drupal configuration
            \Drupal::configFactory()->getEditable('backup_migrate_google_drive.settings')
                ->set('access_token', $token['access_token'])
                ->set('refresh_token', $token['refresh_token'] ?? '')
                ->save();

            $this->messenger()->addStatus($this->t('✅ Google Drive connected successfully! You can now create backup destinations.'));

            // Redirect back to Google Drive settings page
            return $this->redirect('backup_migrate_google_drive.settings');
        } catch (\Exception $e) {
            $this->messenger()->addError($this->t('Failed to complete authorization: @error', ['@error' => $e->getMessage()]));
            return $this->redirect('entity.backup_migrate_destination.collection');
        }
    }

    /**
     * Revoke Google Drive authorization.
     */
    public function revoke()
    {
        // Clear tokens from Drupal configuration
        \Drupal::configFactory()->getEditable('backup_migrate_google_drive.settings')
            ->delete('access_token')
            ->delete('refresh_token')
            ->save();

        $this->messenger()->addStatus($this->t('🔗 Google Drive disconnected successfully.'));

        // Redirect back to settings page
        return $this->redirect('backup_migrate_google_drive.settings');
    }
}
