<?php

namespace Drupal\sos_promotion\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\Html;
use Drupal\sos_promotion\Controller\DataBaseController;
use Drupal\sos_promotion\Services\Tools;

class ManagePromotionForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'sos_promotion_update_delete_form';
    }

    /**
     * Display promotion records.
     */
    public function buildForm(array $form, FormStateInterface $form_state) {

        // Get the current date in New York timezone
        date_default_timezone_set("America/New_York");
        $current_timestamp = \Drupal::time()->getRequestTime();
        $datetime = date('YmdHis', $current_timestamp);

        $form['hr'] = [
            '#type' => 'markup',
            '#markup' => '<br/><hr/><h4>Promotion Records:</h4>',
        ];

        try {
            $connection = Database::getConnection();
            $query = $connection->select('sos_promotions', 'sp')
                ->fields('sp');
            $promotions = $query->execute()->fetchAll();

            if (!empty($promotions)) {
                foreach ($promotions as $key => $record) {
                    $record_id = $record->id;

                    $form['item_'.$key] = array(
                        '#type' => 'details',
                        '#title' => t($record->title),
                        '#open' => FALSE, // Whether the accordion section is open by default.
                    );

                    $form['item_'.$key]['title'] = [
                        '#type' => 'textfield',
                        '#required' => TRUE,
                        '#value' => $record->title,
                        '#name' => 'title_' . $record_id,
                        '#title' => $this->t('Title'),
                    ];

                    $form['item_'.$key]['product_list'] = [
                        '#type' => 'managed_file',
                        '#title' => $this->t("Product ID"),
                        '#required' => TRUE,
                        '#description' => $this->t('Warning: Clicking the remove button will automatically remove the file in the server and you need to re-upload the file.'),
                        '#upload_location' => 'public://promotions/product-list-'.$datetime,
                        '#default_value' => $record->product_fid ? [$record->product_fid] : [],
                        '#upload_validators' => array(
                            'file_validate_extensions' => array('csv'),
                        ),
                    ];

                    $form['item_'.$key]['user_list'] = [
                        '#type' => 'managed_file',
                        '#title' => $this->t("User List"),
                        '#required' => TRUE,
                        '#description' => $this->t('Warning: Clicking the remove button will automatically remove the file in the server and you need to re-upload the file.'),
                        '#upload_location' => 'public://promotions/user-list-'.$datetime,
                        '#default_value' => $record->user_fid ? [$record->user_fid] : [],
                        '#upload_validators' => array(
                            'file_validate_extensions' => array('csv'),
                        ),
                    ];

                    $form['item_'.$key]['flyer'] = [
                        '#type' => 'managed_file',
                        '#title' => $this->t("Flyer PDF (Optional)"),
                        '#required' => FALSE,
                        '#description' => $this->t('Warning: Clicking the remove button will automatically remove the file in the server and you need to re-upload the file.'),
                        '#upload_location' => 'public://promotions/flyer-'.$datetime,
                        '#default_value' => $record->flyer_fid ? [$record->flyer_fid] : [],
                        '#upload_validators' => array(
                            'file_validate_extensions' => array('pdf'),
                        ),
                    ];

                    $form['item_'.$key]['message'] = [
                        '#type' => 'textarea',
                        '#title' => $this->t('Message'),
                        '#value' => $record->message,
                        '#name' => 'message_' . $record_id,
                        '#required' => TRUE,
                    ];

                    $form['item_'.$key]['discount'] = [
                        '#type' => 'textfield',
                        '#title' => $this->t('Discount'),
                        '#required' => TRUE,
                        '#value' => $record->discount,
                        '#name' => 'discount_' . $record_id,
                        '#placeholder' => '0.0'
                    ];

                    $form['item_'.$key]['start_date'] = [
                        '#type' => 'date',
                        '#title' => $this->t('Start Date'),
                        '#required' => TRUE,
                        '#value' => $record->start_date,
                        '#name' => 'start_date_' . $record_id,
                        '#date_date_format' => 'Y-m-d', // Date-only format.
                    ];

                    $form['item_'.$key]['expiry_date'] = [
                        '#type' => 'date',
                        '#title' => $this->t('Expiry Date'),
                        '#required' => TRUE,
                        '#value' => $record->expiry_date,
                        '#name' => 'expiry_date_' . $record_id,
                        '#date_date_format' => 'Y-m-d', // Date-only format.
                    ];
                    $form['item_'.$key]['save'] = [
                        '#type' => 'submit',
                        '#value' => $this->t('Save'),
                        '#name' => 'update_' . $record_id,
                        '#submit' => ['::updatePromotion'],
                        '#attributes' => array(
                            'class' => array(
                                'button button--action button--primary'
                            )
                        ),
                    ];

                    $form['item_'.$key]['delete'] = [
                        '#type' => 'submit',
                        '#value' => $this->t('Delete'),
                        '#name' => 'delete_' . $record_id,
                        '#submit' => ['::deletePromotion'],
                        '#attributes' => array(
                            'class' => array(
                                'button button--action button--danger delete-promotion-button'
                            )
                        ),
                    ];
                }

            } else {
                $form['no_records'] = [
                    '#type' => 'markup',
                    '#markup' => $this->t('No promotion records found.'),
                ];
            }
        } catch (\Exception $e) {
            \Drupal::messenger()->addError($this->t('Error: %error', ['%error' => $e->getMessage()]));
        }

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        // Do nothing on form submission.
        // Submit handlers for adding and deleting promotions are defined separately.
    }

    /**
     * Update promotion record.
     */
    public function updatePromotion(array &$form, FormStateInterface &$form_state) {
        $triggering_element = $form_state->getTriggeringElement();
        $button_name = $triggering_element['#name'];
        $record_id = str_replace('update_', '', $button_name);

        // Extract form values.
        $values = $form_state->getUserInput();

        // Ensure the specific promotion item exists in the form state values.
        if (!empty($values)) {

            date_default_timezone_set("America/New_York");

            $current_timestamp = \Drupal::time()->getRequestTime();
            $datetime = date('Y-m-d H:i:s', $current_timestamp);

            // Validate and process the input data.
            $title = Html::escape($values['title_' . $record_id]);
            $product_list_id = $form_state->getValue('product_list');
            $user_list_id = $form_state->getValue('user_list');
            $flyer_id = $form_state->getValue('flyer');
            $message = Html::escape($values['message_' . $record_id]);
            $discount = Html::escape($values['discount_' . $record_id]);
            $start_date = $values['start_date_' . $record_id];
            $expiry_date = $values['expiry_date_' . $record_id];

            // Validation for decimal
            if (DataBaseController::validateDiscount($discount)) {

                // JSON data ready
                $product_list_json = Tools::fileDataEncode($product_list_id, 'product_list');
                $user_list_json = Tools::fileDataEncode($user_list_id, 'user_list');

                // Returns file upload ID
                $product_file_id = Tools::getFileId($product_list_id);
                $user_file_id = Tools::getFileId($user_list_id);
                $flyer_file_id = !!$flyer_id ? Tools::getFileId($flyer_id) : null;

                // Validate file ids and saved permanent
                Tools::fileValidate(array($product_file_id,$user_file_id,$flyer_file_id));

                try {
                    $connection = Database::getConnection();
                    $connection->update('sos_promotions')
                        ->fields([
                            'title' => $title,
                            'product_list' => $product_list_json,
                            'user_list' => $user_list_json,
                            'message' => $message,
                            'discount' => $discount,
                            'start_date' => $start_date,
                            'expiry_date' => $expiry_date,
                            'last_updated' => $datetime,
                            'product_fid' => $product_file_id,
                            'user_fid' => $user_file_id,
                            'flyer_fid' =>  $flyer_file_id,
                        ])
                        ->condition('id', $record_id)
                        ->execute();

                    \Drupal::messenger()->addMessage($this->t('Promotion record updated.'));
                } catch (\Exception $e) {
                    \Drupal::logger('AddPromotionForm::savePromotion')->error(t('%error', ['%error' => $e->getMessage()]));

                    \Drupal::messenger()->addError($this->t('%error', ['%error' => 'Please check your input. Data not saved.']));
                }
            }
        } else {
            \Drupal::messenger()->addError($this->t('Promotion record not found.'.$record_id));
        }

        $form_state->setRebuild(TRUE);
    }

    /**
     * Delete promotion record.
     */
    public function deletePromotion(array &$form, FormStateInterface $form_state) {
        $triggering_element = $form_state->getTriggeringElement();
        $button_name = $triggering_element['#name'];
        $id = str_replace('delete_', '', $button_name);

        try {
            $connection = Database::getConnection();
            $connection->delete('sos_promotions')
                ->condition('id', $id)
                ->execute();

            \Drupal::messenger()->addMessage($this->t('Promotion record deleted.'));
        } catch (\Exception $e) {
            \Drupal::messenger()->addError($this->t('Error: %error', ['%error' => $e->getMessage()]));
        }

        $form_state->setRebuild(TRUE);
    }

}
