<?php

namespace Drupal\sos_promotion\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;

class ConfigForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'sos_promotion_config_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        // Get Config settings
        $config = \Drupal::config('sos_promotion.configuration');

        // Promotion Config
        $form['white_list'] = [
            '#type' => 'textarea',
            '#title' => $this->t('Enter domain to be whitelisted'),
            '#description' => $this->t('Be sure to add "," after each domain'),
            '#default_value' => $config->get('white_list'),
        ];

        // Add "Save" button.
        $form['promotion_config']['save'] = [
            '#type' => 'submit',
            '#value' => $this->t('Save'),
            '#attributes' => array(
                'class' => array(
                    'button button--action button--primary'
                )
            ),
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $config = \Drupal::configFactory()->getEditable('sos_promotion.configuration');
        $config->set('white_list', $form_state->getValue('white_list'));

        $config->save();

        \Drupal::messenger()->addMessage($this->t('Configuration saved successfully.'));
    }
}
