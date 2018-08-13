<?php

function wpam_has_purchase_record($purchaseLogId){
    global $wpdb;
    $has_record = true;
    $query = "SELECT * FROM ".WPAM_TRANSACTIONS_TBL." WHERE referenceId = %s";        
    $txn_record = $wpdb->get_row($wpdb->prepare($query, $purchaseLogId));
    if($txn_record == NULL) {
        $has_record = false;
    }
    return $has_record;
}

function wpam_generate_refkey_from_affiliate_id($aff_id){
    $db = new WPAM_Data_DataAccess();
    $affiliateRepos1 = $db->getAffiliateRepository();
    $wpam_refkey = NULL;
    $affiliate = $affiliateRepos1->loadBy(array('affiliateId' => $aff_id, 'status' => 'active'));
    if ( $affiliate === NULL ) {  //affiliate with this ID does not exist
        WPAM_Logger::log_debug("generate_refkey_from_affiliate_id function - affiliate ID ".$aff_id." does not exist");
    }
    else
    {
        $default_creative_id = get_option(WPAM_PluginConfig::$DefaultCreativeId);
        if(!empty($default_creative_id))
        {
            $creative = $db->getCreativesRepository()->load($default_creative_id);
            $linkBuilder = new WPAM_Tracking_TrackingLinkBuilder($affiliate, $creative);
            $strRefKey = $linkBuilder->getTrackingKey()->pack();
            $refKey = new WPAM_Tracking_TrackingKey();
            $refKey->unpack( $strRefKey );

            $idGenerator = new WPAM_Tracking_UniqueIdGenerator();
            $trackTokenModel = new WPAM_Data_Models_TrackingTokenModel();

            $trackTokenModel->dateCreated = time();
            $trackTokenModel->sourceAffiliateId = $aff_id;
            $trackTokenModel->sourceCreativeId = $refKey->getCreativeId();
            $trackTokenModel->trackingKey = $idGenerator->generateId();
            $trackTokenModel->referer = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : NULL;
            /* add a new visit so it doesn't fail while awarding commission */
            $db->getTrackingTokenRepository()->insert( $trackTokenModel );
            $db->getEventRepository()->quickInsert( time(), $trackTokenModel->trackingKey, 'visit' );
            /* */
            $binConverter = new WPAM_Util_BinConverter();
            $wpam_refkey = $binConverter->binToString( $trackTokenModel->trackingKey );
        }
    }
    return $wpam_refkey;
}

function wpam_get_cookie_life_time() {
    $cookie_expiry = get_option( WPAM_PluginConfig::$CookieExpireOption );
    $cookie_life_time = 0; //if set to 0 or omitted, the cookie will expire at the end of the session (when the browser closes).
    if (is_numeric($cookie_expiry) && $cookie_expiry > 0) {
        $cookie_life_time = time() + $cookie_expiry * 86400;
    }
    return $cookie_life_time;
}

function wpam_get_total_woocommerce_order_fees($order)
{
    //Calculate total fee (if any) for this order
    $total_fee = 0;
    $order_fee_items = $order->get_fees();
    if(!is_array($order_fee_items)){
        return $total_fee;
    }

    foreach ( $order_fee_items as $fee_item ) {
        $total_fee += $fee_item['line_total'];
    }
    return $total_fee;
}

function wpam_filter_from_email($address) {
    $addrOverride = get_option( WPAM_PluginConfig::$EmailAddressOption );
    if(!empty($addrOverride)){
        return $addrOverride;
    }
    return $address;
}

function wpam_filter_from_name($name) {
    $nameOverride = get_option( WPAM_PluginConfig::$EmailNameOption );
    if(!empty($nameOverride)){
        return $nameOverride;
    }
    return $name;
}

function wpam_sanitize_array($arr) {
  $result = array();
  foreach ($arr as $key => $val)
  {
      $result[$key] = is_array($val) ? wpam_sanitize_array($val) : sanitize_text_field($val);
  }
  return $result;
}
