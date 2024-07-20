<?php
namespace Drupal\sos_promotion\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Database\Database;
use DateTimeZone;
use DateTime;
use Drupal\sos_promotion\Services\Tools;

class DataBaseController extends ControllerBase {

    public function checkWhiteList($request, $dev = false)
    {
        $config = \Drupal::config('sos_promotion.configuration');

        // Remove any spaces, carriage returns and line breaks
        $whitelist = str_replace([' ', "\r", "\n"], '', $config->get('white_list'));

        if (empty($whitelist)) {
            return false;
        }

        $allowed_domains = explode(',', $whitelist);

        $referrer = $request->headers->get('referer');
        $host = $this->getFromURL($referrer, 'host');

        if ((isset($host) && in_array($host, $allowed_domains)) || ($dev === true)) {
            return true;
        } else {
            return false;
        }
    }

    private function getFromURL($url, $what) {
        if(empty($url))
            return;

        $pattern = '/^(https?):\/\/([^\/?]+)(\/[^\?]*)(\?.*)?$/';

        if (preg_match($pattern, $url, $matches)) {
            $scheme = $matches[1];
            $host = $matches[2];
            $path = $matches[3];
            $query = isset($matches[4]) ? $matches[4] : '';

            // You can then parse the query string if needed
            parse_str(ltrim($query, '?'), $queryArray);

            return $$what;
        }
    }

    /**
     * Get Promotions API
     * ex. https://example.com/api/promotions
     */
    public function getPromotions(Request $request){

        if ($this->checkWhiteList($request)) {

            // Check if soldToId is set and sanitize
            if (!empty($_GET['soldToId'])) {
                $soldToId = htmlspecialchars($_GET['soldToId']);
            } else {
                \Drupal::logger('DataBaseController::getPromotions')->error(t('soldToId not provided'));

                return new Response('soldToId not provided', 400);
            }

            try {
                $connection = Database::getConnection();
                $query = $connection->select('sos_promotions', 'sp')
                    ->fields('sp');
                $promotions = $query->execute()->fetchAll();

                $data = []; // initialized

                foreach ($promotions as $record) {
                    // Checks if campaign is within the range dates, then add to promotion data
                    $product_list_json_decode = json_decode($record->product_list, TRUE);
                    $user_list_json_decode = json_decode($record->user_list, TRUE);

                    $data[] = [
                        "id" => $record->id,
                        "eligible_products" => $this->filterProductData($product_list_json_decode),
                        "is_eligible" => $this->checkEligiblePromotion($user_list_json_decode['shipToId'], $soldToId),
                        "campaign_start" => $this->campaignStartCheck($record->start_date, $record->expiry_date),
                        "start_date" => $record->start_date,
                        "expiry_date" => $record->expiry_date,
                        "message" => $record->message,
                        "discount" => $record->discount,
                        "flyer_url" => $record->flyer_fid ? Tools::getFileUrl($record->flyer_fid) : "", // If no value just return empty string not null
                    ];
                }

                $response_data = json_encode($data);

                return new Response($response_data, 200, ['Content-Type' => 'application/json']);

            } catch (\Exception $e) {
                \Drupal::logger('DataBaseController::getPromotions')->error(t('sos_promotions table connection error'));

                \Drupal::messenger()->addError($this->t('Error: %error', ['%error' => $e->getMessage()]));
            }
        }

        return new JsonResponse(array('error' => 'Access denied.'), 403);
    }

    /**
     * Check the current date if within the campaign start & expiry date
     */
    private function campaignStartCheck(string $start_date, string $expiry_date): bool
    {
        // Set the timezone to New York
        $timezone = new DateTimeZone('America/New_York');

        // Get the current date in New York timezone
        $current_date = new DateTime('now', $timezone);

        // Convert start_date and expiry_date to DateTime objects
        $start = new DateTime($start_date, $timezone);
        $expiry = new DateTime($expiry_date, $timezone);

        // Check if current date is within start and expiry dates
        if ($current_date >= $start && $current_date <= $expiry) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    /**
     * Filters json product data to front-end
     */
    private function filterProductData(array $productId): array
    {
        $filteredData = [];

        foreach ($productId['data'] as $data){
            $filteredData[] = $data['product_id'];
        }

        return $filteredData;
    }

    /**
     * Check shipToId if eligible for this promotion
     */
    private function checkEligiblePromotion(array $dataArray, string $shipToId): bool
    {
        if (in_array($shipToId, $dataArray)) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    /**
     * Validate discount field value
     */
    public static function validateDiscount($discount){
        if(!empty($discount)){
            if($discount < 0){
                \Drupal::logger('AddPromotionForm::savePromotion')->error(t('%error', ['%error' => "Discount can't be negative value."]));
                \Drupal::messenger()->addError(t('%error', ['%error' => "Discount can't be negative value."]));

                return FALSE;
            }
            // Check if $discount contains only numeric characters and decimal point
            if(preg_match('/[^0-9.]/', $discount)) {
                \Drupal::logger('AddPromotionForm::savePromotion')->error(t('%error', ['%error' => "Discount field can't have non-numeric value."]));
                \Drupal::messenger()->addError(t('%error', ['%error' => "Discount field can't have non-numeric value."]));

                return FALSE;
            }
            if($discount > 100){
                \Drupal::logger('AddPromotionForm::savePromotion')->error(t('%error', ['%error' => "Discount field range 0-100 only."]));
                \Drupal::messenger()->addError(t('%error', ['%error' => "Discount field range 0-100 only."]));

                return FALSE;
            }
        }else{
            \Drupal::messenger()->addError(t('%error', ['%error' => "Discount field can't be empty."]));

            return FALSE;
        }

        // Passed all validation
        return TRUE;
    }
}