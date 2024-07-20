<?php

namespace Drupal\sos_promotion\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\Html;
use Drupal\sos_promotion\Services\Tools;
use Drupal\sos_promotion\Controller\DataBaseController;

class AddPromotionForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'sos_promotion_add_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {

            // Get the current date in New York timezone
            date_default_timezone_set("America/New_York");
            $current_timestamp = \Drupal::time()->getRequestTime();
            $datetime = date('YmdHis', $current_timestamp);

            // Build form for each promotion.
            $form['add'] = array(
                '#type' => 'details',
                '#title' => t('Add Promotion'),
                '#open' => FALSE, // Whether the accordion section is open by default.
            );

            $form['add']['title'] = [
                '#type' => 'textfield',
                '#required' => TRUE,
                '#title' => $this->t('Title'),
            ];

            $form['add']['product_list'] = [
                '#type' => 'managed_file',
                '#title' => $this->t("Product ID"),
                '#required' => TRUE,
                '#description' => $this->t('Upload a CSV file with product ID\'s and names.'),
                '#upload_location' => 'public://promotions/product-list-'.$datetime,
                '#upload_validators' => array(
                    'file_validate_extensions' => array('csv'),
                ),
            ];

            $form['add']['user_list'] = [
                '#type' => 'managed_file',
                '#title' => $this->t("User List"),
                '#required' => TRUE,
                '#description' => $this->t('Upload a CSV file with a list of shipToId\'s.'),
                '#upload_location' => 'public://promotions/user-list-'.$datetime,
                '#upload_validators' => array(
                    'file_validate_extensions' => array('csv'),
                ),
            ];

            $form['add']['flyer'] = [
                '#type' => 'managed_file',
                '#title' => $this->t("Flyer PDF (Optional)"),
                '#required' => FALSE,
                '#description' => $this->t('Upload a flyer PDF file'),
                '#upload_location' => 'public://promotions/flyer-'.$datetime,
                '#upload_validators' => array(
                    'file_validate_extensions' => array('pdf'),
                ),
            ];

            $form['add']['message'] = [
                '#type' => 'textarea',
                '#title' => $this->t('Message'),
                '#required' => TRUE,
            ];

            $form['add']['discount'] = [
                '#type' => 'textfield',
                '#title' => $this->t('Discount'),
                '#required' => TRUE,
                '#placeholder' => '0.0'
            ];

            $form['add']['start_date'] = [
                '#type' => 'date',
                '#title' => $this->t('Start Date'),
                '#required' => TRUE,
                '#date_date_format' => 'Y-m-d', // Date-only format.
            ];

            $form['add']['expiry_date'] = [
                '#type' => 'date',
                '#title' => $this->t('Expiry Date'),
                '#required' => TRUE,
                '#date_date_format' => 'Y-m-d', // Date-only format.
            ];

            $form['add']['save'] = [
                '#type' => 'submit',
                '#value' => $this->t('Save'),
                '#name' => 'save',
                '#submit' => ['::savePromotion'],
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
    public function submitForm(array &$form, FormStateInterface $form_state) {
        // Do nothing on form submission.
        // Submit handlers for adding and deleting promotions are defined separately.
    }

    /**
     * Save promotion record.
     */
    public function savePromotion(array &$form, FormStateInterface $form_state) {
        date_default_timezone_set("America/New_York");

        $current_timestamp = \Drupal::time()->getRequestTime();
        $datetime = date('Y-m-d H:i:s', $current_timestamp);

        // Extract values from the form state and sanitize them.
        $title = Html::escape($form_state->getValue('title'));
        $product_list_id = $form_state->getValue('product_list');
        $user_list_id = $form_state->getValue('user_list');
        $flyer_id = $form_state->getValue('flyer');
        $message = Html::escape($form_state->getValue('message'));
        $discount = Html::escape($form_state->getValue('discount'));
        $start_date = $form_state->getValue('start_date');
        $expiry_date = $form_state->getValue('expiry_date');

        // Validation for decimal
        if(DataBaseController::validateDiscount($discount)){

            // JSON data ready
            $product_list_json = Tools::fileDataEncode($product_list_id, 'product_list');
            $user_list_json = Tools::fileDataEncode($user_list_id, 'user_list');

            // Returns file upload ID
            $product_file_id = Tools::getFileId($product_list_id);
            $user_file_id = Tools::getFileId($user_list_id);
            $flyer_file_id = !!$flyer_id ? Tools::getFileId($flyer_id) : null;

            // Prepare data for insertion.
            $data = [
                'title' => $title,
                'product_list' => $product_list_json,
                'user_list' => $user_list_json,
                'message' => $message,
                'discount' => $discount,
                'start_date' => $start_date,
                'expiry_date' => $expiry_date,
                'created' => $datetime,
                'last_updated' => $datetime,
                'product_fid' => $product_file_id,
                'user_fid' => $user_file_id,
                'flyer_fid' => $flyer_file_id,
            ];

            // Validate file ids and saved permanent
            Tools::fileValidate(array($product_file_id,$user_file_id,$flyer_file_id));

            // Insert data into the promotions table.
            try {
                $connection = Database::getConnection();
                $connection->insert('sos_promotions')
                    ->fields($data)
                    ->execute();

                // Optionally set a message to inform the user.
                \Drupal::messenger()->addMessage($this->t('Promotion saved successfully.'));
            } catch (\Exception $e) {
                \Drupal::logger('AddPromotionForm::savePromotion')->error(t('%error', ['%error' => $e->getMessage()]));

                \Drupal::messenger()->addError($this->t('%error', ['%error' => 'Please check your input. Data not saved.']));
            }
        }
    }
}
