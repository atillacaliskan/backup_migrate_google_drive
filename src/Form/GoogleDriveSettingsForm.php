<?php

namespace Drupal\backup_migrate_google_drive\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Google Drive settings form.
 */
class GoogleDriveSettingsForm extends ConfigFormBase
{

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames()
    {
        return ['backup_migrate_google_drive.settings'];
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'backup_migrate_google_drive_settings_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $config = $this->config('backup_migrate_google_drive.settings');

        // Check if user is already authorized
        $access_token = $config->get('access_token');
        $is_authorized = !empty($access_token);

        // OAuth Credentials Setup
        $client_id = $config->get('client_id');
        $client_secret = $config->get('client_secret');
        $has_credentials = !empty($client_id) && !empty($client_secret);

        if (!$has_credentials) {
            $form['credentials_setup'] = [
                '#type' => 'fieldset',
                '#title' => $this->t('Google OAuth Setup'),
                '#description' => $this->t('Configure your Google Cloud OAuth credentials to connect to Google Drive.'),
            ];

            $form['credentials_setup']['instructions'] = [
                '#type' => 'markup',
                '#markup' => $this->t('
                    <div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-left: 4px solid #0073aa;">
                        <h4>Setup Instructions:</h4>
                        <ol>
                            <li>Go to <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
                            <li>Create a new project or select an existing one</li>
                            <li>Enable the Google Drive API for your project</li>
                            <li>Go to "Credentials" and create OAuth 2.0 Client ID</li>
                            <li>Select "Web application" as the application type</li>
                            <li>Add this redirect URI: <code>@redirect_uri</code></li>
                            <li>Copy the Client ID and Client Secret to the fields below</li>
                        </ol>
                        <p><strong>Note:</strong> You need a Google account to create OAuth credentials. This is completely free.</p>
                    </div>
                ', ['@redirect_uri' => Url::fromRoute('backup_migrate_google_drive.callback', [], ['absolute' => TRUE])->toString()]),
            ];

            $form['credentials_setup']['client_id'] = [
                '#type' => 'textfield',
                '#title' => $this->t('Client ID'),
                '#default_value' => $client_id,
                '#required' => TRUE,
                '#description' => $this->t('Enter your Google OAuth Client ID'),
            ];

            $form['credentials_setup']['client_secret'] = [
                '#type' => 'textfield',
                '#title' => $this->t('Client Secret'),
                '#default_value' => $client_secret,
                '#required' => TRUE,
                '#description' => $this->t('Enter your Google OAuth Client Secret'),
            ];

            return parent::buildForm($form, $form_state);
        }

        if ($is_authorized) {
            $form['status'] = [
                '#type' => 'markup',
                '#markup' => '<div class="messages messages--status">' . $this->t('✅ Google Drive is connected and ready to use!') . '</div>',
            ];

            $form['actions_authorized'] = [
                '#type' => 'fieldset',
                '#title' => $this->t('Account Management'),
            ];

            $form['actions_authorized']['disconnect'] = [
                '#type' => 'link',
                '#title' => $this->t('Disconnect Google Drive'),
                '#url' => Url::fromRoute('backup_migrate_google_drive.revoke'),
                '#attributes' => [
                    'class' => ['button', 'button--danger'],
                ],
            ];
        } else {
            $form['connect'] = [
                '#type' => 'fieldset',
                '#title' => $this->t('Connect to Google Drive'),
                '#description' => $this->t('Click the button below to connect your Google Drive account. You will be redirected to Google to authorize this application.'),
            ];

            $form['connect']['authorize'] = [
                '#type' => 'link',
                '#title' => $this->t('🔗 Connect Google Drive'),
                '#url' => Url::fromRoute('backup_migrate_google_drive.auth'),
                '#attributes' => [
                    'class' => ['button', 'button--primary', 'button--large'],
                    'style' => 'font-size: 16px; padding: 12px 24px;',
                ],
            ];

            $form['connect']['info'] = [
                '#type' => 'markup',
                '#markup' => $this->t('
          <div style="margin-top: 15px; padding: 15px; background: #f8f9fa; border-left: 4px solid #0073aa;">
            <h4>How it works:</h4>
            <ol>
              <li>Click "Connect Google Drive" button</li>
              <li>Sign in with your Gmail/Google account</li>
              <li>Grant permission to access your Google Drive</li>
              <li>You\'ll be redirected back here automatically</li>
              <li>Start backing up to Google Drive!</li>
            </ol>
            <p><strong>Note:</strong> This uses your own Google OAuth application for maximum security and control.</p>
          </div>
        '),
            ];
        }

        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $config = $this->configFactory()->getEditable('backup_migrate_google_drive.settings');

        // Save OAuth credentials if provided
        if ($form_state->getValue('client_id')) {
            $config->set('client_id', $form_state->getValue('client_id'));
        }
        if ($form_state->getValue('client_secret')) {
            $config->set('client_secret', $form_state->getValue('client_secret'));
        }

        $config->save();

        $this->messenger()->addStatus($this->t('Settings saved successfully!'));

        parent::submitForm($form, $form_state);
    }
}
