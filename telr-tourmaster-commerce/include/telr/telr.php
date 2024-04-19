<?php
/* 	
 * 	Payment Telr Plugin
 * 	---------------------------------------------------------------------
 * 	creating the telr payment option
 * 	---------------------------------------------------------------------
 */
define('TELR_DEBUG', false);

add_filter('goodlayers_credit_card_payment_gateway_options', 'goodlayers_telr_payment_gateway_options');
if (!function_exists('goodlayers_telr_payment_gateway_options')) {

    function goodlayers_telr_payment_gateway_options($options)
    {
        $options['telr'] = esc_html__('Telr', 'tourmaster');
        return $options;
    }

}

add_filter('goodlayers_plugin_payment_option', 'goodlayers_telr_payment_option');
if (!function_exists('goodlayers_telr_payment_option')) {

    function goodlayers_telr_payment_option($options)
    {

        $options['telr'] = array(
            'title' => esc_html__('Telr', 'tourmaster'),
            'options' => array(
                'telr-live-mode' => array(
                    'title' => __('Telr Live Mode', 'tourmaster'),
                    'type' => 'checkbox',
                    'default' => 'disable'
                ),
                'telr-store_secret' => array(
                    'title' => __('Telr Authentication Key', 'tourmaster'),
                    'type' => 'text'
                ),
                'telr-store_id' => array(
                    'title' => __('Telr Store ID', 'tourmaster'),
                    'type' => 'text'
                ),
                'telr-currency' => array(
                    'title' => __('Telr Currency Code', 'tourmaster'),
                    'type' => 'text',
                    'default' => 'usd'
                ),
            )
        );

        return $options;
    }

// goodlayers_telr_payment_option
}

$current_payment_gateway = apply_filters('goodlayers_payment_get_option', '', 'credit-card-payment-gateway');

if ($current_payment_gateway == 'telr') {

    add_filter('goodlayers_plugin_payment_attribute', 'goodlayers_telr_payment_attribute');
    add_filter('goodlayers_telr_payment_form', 'goodlayers_telr_payment_form', 10, 2);
   
}


// add attribute for payment button
if (!function_exists('goodlayers_telr_payment_attribute')) {
    /**
     * 
     *
     * @param array $attributes
     * @return array
     */
    function goodlayers_telr_payment_attribute($attributes)
    {
        return array('method' => 'ajax', 'type' => 'telr');
    }

}

// payment form
if (!function_exists('goodlayers_telr_payment_form')) {
    add_filter('telr_tourmaster_get_booking_data', 'tourmaster_get_booking_data', 10, 3);

    /**
     * Request to the API TELR
     * @param string $url
     * @param array $data
     * @return type
     */
    function telr_api_request($url, $data)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, count($data));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
        $results = curl_exec($ch);
        curl_close($ch);
        $results = json_decode($results, true);
        return $results;
    }

    /**
     * Preparation of HTML Forms of payment via Telr
     * @param string $ret
     * @param string $tid
     * @return string
     */
    function goodlayers_telr_payment_form($ret = '', $tid = '')
    {
        ob_start();
        $ret = goodlayers_telr_payment_charge($tid);

        if ($ret && !empty($ret['status']) && $ret['status'] == 'success') {
            ?>
            <div class="goodlayers-telr-redirecting-message" >
                <?php esc_html_e('Please wait while we redirect to Telr.', 'tourmaster') ?>
            </div>
            <form action="<?= $ret['url']; ?>" method="POST" id="goodlayers-telr-payment-form">
                <input class="goodlayers-payment-button submit" type="submit" value="<?php esc_html_e('Send Payment', 'tourmaster'); ?>" />
            </form>

            <script type="text/javascript">
                (function($){
                $('#goodlayers-telr-payment-form').submit();
                })(jQuery);
            </script>
            <?php
            $res = ob_get_contents();
            ob_end_clean();
            return $res;
        }
        if (!empty($ret['message'])) {
            $err_message = $ret['message'];
        }
        else {
            $err_message = __('Invalid request tour', 'tourmaster');
        }
        ?>
        <div class="goodlayers-payment-form goodlayers-with-border" >
            <form action="" method="POST" id="goodlayers-telr-payment-form">
                <div class="now-loading"></div>
                <div class="payment-errors"><?= $err_message ?> </div>
                <input type="hidden" name="tid" value="<?php echo esc_attr($tid) ?>" />
                <input class="goodlayers-payment-button submit" type="submit" value="<?php esc_html_e('Repeat Payment', 'tourmaster'); ?>" />
                <!-- for proceeding to last step -->
                <div class="goodlayers-payment-plugin-complete" ></div>
            </form>
        </div>
        <script type="text/javascript">
            (function($){
            $('.payment-errors').show(200);
            })(jQuery);
        </script>
        <?php
        $res = ob_get_contents();
        ob_end_clean();
        return $res;
    }

// goodlayers_telr_payment_form
}
//  for payment submission
if (!function_exists('get_telr_cartid')) {
    /**
     * transforms the current TID into CARTID for Telr based on the table of the language sites
     * @param string|null $tid - Account for payment
     * @param string $variant - An attempt to pay for this account (taking into account the fact that now there is an opportunity to pay a few payments)
     * @return string
     */
    function get_telr_cartid($tid = null,$variant='0')
    {
        $blog_id = sprintf('%02d',get_current_blog_id());
        return '04' .$blog_id. "/".date('dmY') ."/". $tid."/".$variant;
        
    }
}
//  for payment submission
if (!function_exists('goodlayers_telr_payment_charge')) {


    
    /**
     * Checking the possibility of paying through TELR
     *
     * @param [type] $tid
     * @return void
     */
    function goodlayers_telr_payment_charge($tid = null)
    {

        $ret = array();
        if (($tid = (int) $tid)) {

            $t_data = apply_filters('telr_tourmaster_get_booking_data', array('id' => $tid), array('single' => true), '*');
            
            telr_add_log(array($t_data, $tid,apply_filters('goodlayers_payment_get_option', 'off', 'telr-live-mode')), 'info',__FUNCTION__."::".__LINE__);

            if (empty($t_data)) {
                $ret['status'] = 'failed';
                $ret['message'] = esc_html__('Cannot retrieve pricing data, please try again.', 'tourmaster');
            }
            else {
                $pricing_info = json_decode($t_data->pricing_info, true);
                if ($pricing_info['deposit-price']) {
                    $pricing_info['price'] = $pricing_info['deposit-price'];
                }
                else {
                    $pricing_info['price'] = $pricing_info['pay-amount'];
                }
                /** @var array $contact_info  контактная информация заказчика  */
                $contact_info = json_decode($t_data->contact_info, true);
                /** @var array $payment_infos  количество вариантов оплаты которое было использовано для оплаты счета */
                $payment_infos = json_decode($t_data->payment_info, true);
                if(!is_array($payment_infos)){
                    $payment_infos=array();
                }
                $ver=count($payment_infos)+1;
                /** @var array $payment_info текущий вариант для оплаты этого счета */
                $payment_info = array(
                        'payment_method' => 'telr',
                        'submission_date' => current_time('mysql'),
                        'ver'=>$ver
                );
                try {
                    $pricing_info['price'] = round(floatval($pricing_info['price']) * 100) / 100;
                    $data = array(
                        'ivp_method' => "create",
                        'ivp_source' => get_option('home'),
                        'ivp_store' => trim(apply_filters('goodlayers_payment_get_option', '', 'telr-store_id')),
                        'ivp_authkey' => trim(apply_filters('goodlayers_payment_get_option', '', 'telr-store_secret')),
                        'ivp_cart' => get_telr_cartid ($tid,$ver),
                        'ivp_test' => (int)!(apply_filters('goodlayers_payment_get_option', 'disable', 'telr-live-mode') == 'enable'),
                        'ivp_amount' => $pricing_info['price'],
                        'ivp_currency' => strtoupper(trim(apply_filters('goodlayers_payment_get_option', 'USD', 'telr-currency'))),
                        'ivp_desc' => get_the_title($t_data->tour_id),
                        'return_auth' => add_query_arg(array('tid' => $tid,'ver'=>$ver, 'step' => 3, 'payment_method' => 'telr', 'action' => 'return_url'), tourmaster_get_template_url('payment')),
                        'return_can' => add_query_arg(array('tid' => $tid, 'ver'=>$ver,'step' => 3, 'payment_method' => 'telr', 'action' => 'return_cancel'), tourmaster_get_template_url('payment')),
                        'return_decl' => add_query_arg(array('tid' => $tid, 'ver'=>$ver,'step' => 3, 'payment_method' => 'telr', 'action' => 'return_cancel'), tourmaster_get_template_url('payment')),
                        'bill_fname' => $contact_info['first_name'],
                        'bill_sname' => $contact_info['last_name'],
                        'bill_addr1' => $contact_info['contact_address'],
                        'bill_email' => $contact_info['email'],
                        'bill_phone' => $contact_info['phone'],
                    );
                    $result = telr_api_request('https://secure.telr.com/gateway/order.json', $data);
                    telr_add_log(array($data, $result,apply_filters('goodlayers_payment_get_option', 'off', 'telr-live-mode')), 'info');
                    if (!empty($result['order'])) {
                        // создание счета для оплаты и получение ссылки на переход
                        $telr_ref = trim($result['order']['ref']);
                        $telr_url = trim($result['order']['url']);
                        $payment_info = array_merge($payment_info,array(
                            'order_ref' => $telr_ref,
                            'payment_url' => $telr_url,
                            'method' => "create",
                        ));
                        $payment_infos = tourmaster_payment_info_format($payment_infos, $result->order_status);
                        $payment_infos[] = $payment_info;
                        tourmaster_update_booking_data(
                                array(
                                    'payment_info' => json_encode($payment_infos),
                                ),
                              array('id' => $tid, 'payment_date' => $t_data->payment_date),
                              array('%s'),
                              array('%d', '%s')
                        );
                        $ret['status'] = 'success';
                        $ret['url'] = $telr_url;
                        return $ret;
                    }
                    else if (!empty($result['error'])) {
                        $ret['status'] = 'failed';
                        $ret['message'] = trim($result['error']['message']) . " " . trim($result['error']['note']);
                        $payment_info = array_merge($payment_info, array(
                            'ver'=>'',
                            'error' => trim($result['error']['message']) . " " . trim($result['error']['note']),
                        ));
                        $payment_infos = tourmaster_payment_info_format($payment_infos, $result->order_status);
                        $payment_infos[] = $payment_info;
                        telr_add_log(array($data, $result), 'error');
                        tourmaster_update_booking_data(
                                array(
                                    'payment_info' => json_encode($payment_infos),
                                ),
                              array('id' => $tid, 'payment_date' => $t_data->payment_date),
                              array('%s'),
                              array('%d', '%s')
                        );
                    }
                }
                catch (Exception $e) {
                    $ret['status'] = 'failed';
                    $ret['message'] = $e->getMessage();
                }
            }
        }
        return $ret;
    }

// goodlayers_telr_payment_charge
}


if (!function_exists('tourmaster_telr_process_ipn')) {
    add_action('init', 'tourmaster_telr_process_ipn', 1000);

    /**
     *
     */
    function tourmaster_telr_process_ipn()
    {
        $ret = array();
        if (isset($_GET['payment_method']) && $_GET['payment_method'] == 'telr') {

            if (isset($_GET['action']) && isset($_GET['ver']) && isset($_GET['tid']) && ($_GET['action'] == 'return_url')) {
                $ret = goodlayers_telr_payment_transaction_result($_GET['tid'],__FUNCTION__."::".__LINE__);
                telr_add_log(array($_GET, $_POST, $ret), 'trace');
                if (isset($ret['status']) && ($ret['status'] == 'success')) {
                    wp_redirect(add_query_arg(array('tid' => (int) $_GET['tid'], 'step' => 4), tourmaster_get_template_url('payment')));
                    die();
                }
            }
            else if (isset($_GET['action']) && ($_GET['action'] == 'return_cancel')) {
                telr_add_log(array($_GET, $_POST, $ret), 'trace',__FUNCTION__."::".__LINE__);
                wp_redirect(add_query_arg(array('page' => 'payment-cancel'), home_url('/')));
                die();
            }
        }
    }
    /**
     * Payment through TELR
     * @param string|null $tid
     * @return string
     */
    function goodlayers_telr_payment_transaction_result($tid = null)
    {
        $ret = $payment_info= array();
        $ver=(isset($_GET['ver']))?(int)$_GET['ver']:false;
        if (($tid = (int) $tid) && $ver) {
            $t_data = apply_filters('telr_tourmaster_get_booking_data', array('id' => $tid), array('single' => true), '*');
            telr_add_log(array('ver'=>$ver,'tid'=>$tid,'data'=>$t_data), 'info',__FUNCTION__."::".__LINE__);
            if (is_object($t_data) && ($payment_infos = json_decode($t_data->payment_info, true))) {
                // проверка наличия ордера с текущими показателями для приема оплаты.
                foreach($payment_infos AS $key=>$info){
                    if(array_key_exists('ver', $info) && ($info['ver']==$ver)){
                      $payment_info=$info;
                      $payment_index=$key;
                    }   
                }
                if(!($payment_info && $payment_info['payment_method']=='telr' && $payment_info['method']=='create') ){
                    $ret['status'] = 'failed';
                    $ret['message'] = esc_html__('No order found for current payment, please try again.', 'tourmaster');
                    telr_add_log(array('ver'=>$ver,'tid'=>$tid, 'ret'=>$ret), 'error' , __FUNCTION__."::".__LINE__);
                    return $ret;
                }
                
                // set price
                $pricing_info = json_decode($t_data->pricing_info, true);
                $price = '';
                if( $pricing_info['deposit-price'] ){
                        $price = $pricing_info['deposit-price'];
                        if( !empty($pricing_info['deposit-price-raw']) ){
                                $deposit_amount = $pricing_info['deposit-price-raw'];
                        }
                }else{
                        $price = $pricing_info['pay-amount'];
                        if( !empty($pricing_info['pay-amount-raw']) ){
                                $pay_amount = $pricing_info['pay-amount-raw'];
                        }
                }
                if( empty($price) ){
                        $ret['status'] = 'failed';
                        $ret['message'] = esc_html__('Cannot retrieve pricing data, please try again.', 'tourmaster');
                        telr_add_log(array('ver'=>$ver,'tid'=>$tid, 'ret'=>$ret), 'error' , __FUNCTION__."::".__LINE__);
                        return $ret;
                }
                //start read telr payment
                try {
                    $data = array(
                        'ivp_method' => "check",
                        'ivp_store' => trim(apply_filters('goodlayers_payment_get_option', '', 'telr-store_id')),
                        'ivp_authkey' => trim(apply_filters('goodlayers_payment_get_option', '', 'telr-store_secret')),
                        'order_ref' => $payment_info['order_ref'],
                    );
                    telr_add_log($data, 'info', __FUNCTION__."::".__LINE__);
                    $result = telr_api_request('https://secure.telr.com/gateway/order.json', $data);
                    telr_add_log($result, 'info', __FUNCTION__."::".__LINE__);

                    if (!empty($result['error'])) {
   
                        $payment_info = array_merge($payment_info, array(
                            'payment_method' => 'telr',
                            'submission_date' => current_time('mysql'),
                            'error' => trim($result['error']['message']) . " " . trim($result['error']['note']),
                        ));
                        $payment_infos = tourmaster_payment_info_format($payment_infos, $result->order_status);
                        $payment_infos[$payment_index] = $payment_info;
                        tourmaster_update_booking_data(
                                array(
                                    'payment_info' => json_encode($payment_infos),
                                ),
                              array('id' => $tid, 'payment_date' => $t_data->payment_date),
                              array('%s'),
                              array('%d', '%s')
                        );
                        
                        $ret['status'] = 'failed';
                        $ret['message'] = trim($result['error']['message']);
                        telr_add_log(array('ver'=>$ver,'tid'=>$tid, 'ret'=>$ret), 'error' , __FUNCTION__."::".__LINE__);
                        return $ret;
                        
                    }else if (!empty($result['order']) && ($result['order']['cartid'] == get_telr_cartid($tid,$ver))) {
                        // удаляем текущий предварительный запрос прежде чем добавим результаты положительной оплаты 
                        unset( $payment_infos[$payment_index] );
                        tourmaster_update_booking_data(
                                array(
                                    'payment_info' => json_encode($payment_infos),
                                ),
                              array('id' => $tid, 'payment_date' => $t_data->payment_date),
                              array('%s'),
                              array('%d', '%s')
                        );
                        
                        $payment_info['amount'] = $result['order']['amount'];
                        $payment_info['transaction_id'] = $result['order']['transaction']['ref'];
                        $payment_info['payment_status'] = 'paid';
                        $payment_info['submission_date'] = current_time('mysql');
                        $payment_info['method'] = "check";

                        if( !empty($deposit_amount) ){
                                $payment_info['deposit_amount'] = $deposit_amount;
                        }
                        if( !empty($pay_amount) ){
                                $payment_info['pay_amount'] = $pay_amount;
                        }

                        do_action('goodlayers_set_payment_complete', $tid, $payment_info);
                        
                        $ret['status'] = 'success';
                        return $ret;
                    }
                    
                    
                }
                catch (Exception $e) {
                    $ret['status'] = 'failed';
                    $ret['message'] = $e->getMessage();
                    telr_add_log(array('ver'=>$ver,'tid'=>$tid, 'ret'=>$ret), 'error' , __FUNCTION__."::".__LINE__);
                }
            }
            else {
                $ret['status'] = 'failed';
                $ret['message'] = esc_html__('Cannot retrieve pricing data, please try again.', 'tourmaster');
                telr_add_log(array('ver'=>$ver,'tid'=>$tid, 'ret'=>$ret), 'error' , __FUNCTION__."::".__LINE__);
            }
        }
        return $ret;
    }

}
if (!function_exists('telr_add_log')) {

    function telr_add_log($message, $status = 'info', $category = '')
    {
        if (TELR_DEBUG || ( in_array($status, array('error')) )) {
            if (!is_string($message)) {
                $message = json_encode($message);
            }
            $text = "[" . date("Y-m-d H:i:s.u") . "]; " . $category . "; " . $message . "\n";
            if(!is_dir(ABSPATH."/_logs" )){
                @mkdir(ABSPATH."/_logs" ,0777);
            }
            $logFile = ABSPATH . '/_logs/telr.' . $status . '.log';
            // делаем замену файла если он превышает допустимый размер 10 мбм
            if (is_file($logFile) && filesize($logFile) > 10000 * 1024) {
                if (($fp = @fopen($logFile, 'a')) !== false) {
                    @flock($fp, LOCK_EX);
                    for ($i = 5; $i >= 0; --$i) {
                        $rotateFile = $logFile . "." . $i;
                        if (is_file($rotateFile)) {
                            if (($i === 5)) {
                                @unlink($rotateFile);
                                continue;
                            }
                            $newFile = $logFile . '.' . ($i + 1);
                            @rename($rotateFile, $newFile);
                        }
                    }
                    @flock($fp, LOCK_UN);
                    @fclose($fp);
                    // заменяем название файла
                    @rename($logFile, $rotateFile);
                }
            }
            error_log($text, 3, $logFile);
        }
    }

}