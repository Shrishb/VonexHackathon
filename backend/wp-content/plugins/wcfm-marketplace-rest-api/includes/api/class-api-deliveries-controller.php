<?php
class WCFM_REST_Deliveries_Controller extends WCFM_REST_Controller {
/**
   * Endpoint namespace
   *
   * @var string
   */
  protected $namespace = 'wcfmmp/v1';

  /**
    * Route name
    *
    * @var string
    */
  protected $base = 'deliveries';
  
  /**
    * Post status
    */
    protected $post_status = array();

    /**
     * Stores the request.
     * @var array
     */
    protected $request = array();

    /**
     * Load autometically when class initiate
     *
     * @since 1.0.0
     *
     * @return array
     */
    public function __construct() {
      
    }
    
  /**
   * Register the routes for notifications.
   */
  public function register_routes() {
    register_rest_route( $this->namespace, '/' . $this->base, array(
      array(
          'methods'             => WP_REST_Server::READABLE,
          'callback'            => array( $this, 'get_deliveries' ),
          'permission_callback' => array( $this, 'get_deliveries_permissions_check' ),
          'args'                => $this->get_collection_params(),
      ),
      'schema' => array( $this, 'get_public_item_schema' ),
    ) );

    register_rest_route( $this->namespace, '/' . $this->base . '/(?P<id>[\d]+)/', array(
      'args' => array(
          'id' => array(
              'description' => __( 'Unique identifier for the object.', 'wcfm-marketplace-rest-api' ),
              'type'        => 'integer',
          )
      ),
      array(
          'methods'             => WP_REST_Server::EDITABLE,
          'callback'            => array( $this, 'update_delivery_status' ),
          
          'permission_callback' => array( $this, 'update_delivery_permissions_check' ),
      ),
    ));
  }

  /**
     * Checking if have any permission to view notifications
     *
     * @since 1.0.0
     *
     * @return boolean
     */
  public function get_deliveries_permissions_check() {     
    //if( apply_filters( 'wcfm_is_allow_notification_message', true ) )
      return true;
    //return false;
  }

  public function update_delivery_permissions_check() {     
    //if( apply_filters( 'wcfm_is_allow_notification_message', true ) )
      return true;
    //return false;
  }
  
  public function get_deliveries($request) {
    global $WCFM, $WCFMd;

    $_POST["controller"] = 'wcfm-delivery-boys-stats';
    $_POST['length'] = !empty($request['per_page']) ? intval($request['per_page']) : 10;
    $_POST['start'] = !empty($request['page']) ? ( intval($request['page']) - 1 ) * $_POST['length'] : 0;
    $_POST['status_type'] = !empty($request['delivery_status']) ? $request['delivery_status'] : '';
    $_POST['wcfm_delivery_boy'] = !empty($request['wcfm_delivery_boy']) ? intval($request['wcfm_delivery_boy']) : get_current_user_id();

    $wcfm_delivery_orders_array = array();
    $response = array();
    //$wcfm_delivery_orders_json_arr = array();

    define('WCFM_REST_API_CALL', TRUE);
    $WCFM->init();
    //$WCFMd->init_wcfmd();
    //print_r( $_POST );
    $wcfm_delivery_orders_array = $WCFMd->ajax->wcfmd_ajax_controller();
    //print_r($wcfm_delivery_orders_array);
    if( !empty($wcfm_delivery_orders_array) ) {
      $response = $this->get_delivery_items($wcfm_delivery_orders_array);

      return rest_ensure_response( $response );
    } else {
      return rest_ensure_response( $response );
    }

    return $deliveries_objects;
  }

  public function get_delivery_items($wcfm_delivery_orders_array) {
    global $WCFM, $WCFMd;
    $response = array();
    foreach( $wcfm_delivery_orders_array as $key => $wcfm_delivery_order_single ) {
      $the_order = wc_get_order( $wcfm_delivery_order_single->order_id );
      if( !is_a( $the_order, 'WC_Order' ) ) continue;
      $response[$key]['delivery_id'] = $wcfm_delivery_order_single->ID;
      $response[$key]['order_id'] = esc_attr( $the_order->get_order_number() );
      $response[$key]['delivery_status'] = $wcfm_delivery_order_single->delivery_status;
      if ( $the_order->get_payment_method_title() ) {
        $response[$key]['payment_method'] = $the_order->get_payment_method_title();
      }

      if( in_array( $wcfm_delivery_order_single->payment_method, array( 'cod' ) ) ) {
        $gross_sales_total = $WCFMd->frontend->wcfmd_get_delivery_meta( $wcfm_delivery_order_single->ID, 'gross_sales_total', true );
        if( $gross_sales_total ) {
          $response[$key]['payment_remaining'] = $gross_sales_total;
        }
      }
      $response[$key]['currency'] = get_woocommerce_currency();
      //ITEM
      try {
        $line_item = new WC_Order_Item_Product( $wcfm_delivery_order_single->item_id );
        $product   = $line_item->get_product();
        $response[$key]['item']['name'] = $line_item->get_name();
        $response[$key]['item']['quantity'] = $line_item->get_quantity();

        if ( $product && $product->get_sku() ) {
          $response[$key]['item']['sku'] = esc_html( $product->get_sku() );
        }
      } catch (Exception $e) {
        wcfm_log( "Order List Error Rest API CALL ::" . $wcfm_delivery_order_single->order_id . " => " . $e->getMessage() );
        unset( $response[$key] );
        continue;
      }

      //STORE
      if( $wcfm_delivery_order_single->vendor_id ) {
        $response[$key]['store']['name'] = $WCFM->wcfm_vendor_support->wcfm_get_vendor_store_name_by_vendor( $wcfm_delivery_order_single->vendor_id );
        if( apply_filters( 'wcfm_is_allow_store_location_to_delivery_boys', true ) ) {
          $store_user = wcfmmp_get_store( $wcfm_delivery_order_single->vendor_id );
          if( $store_user->get_address() ) {
            $response[$key]['store']['address'] = $store_user->get_address();

            $store_country = $response[$key]['store']['address']['country'];
            $store_state = $response[$key]['store']['address']['state'];
            
            $response[$key]['store']['address']['country'] = ( isset( WC()->countries->countries[ $store_country ] ) ) ? WC()->countries->countries[ $store_country ] : $store_country;
            $response[$key]['store']['address']['state'] = ( $store_country && $store_state && isset( WC()->countries->states[ $store_country ][ $store_state ] ) ) ? WC()->countries->states[ $store_country ][ $store_state ] : $store_state;

          }
        }
      }

      //CUSTOMER
      $response[$key]['customer'] = array();
      //name
      if( apply_filters( 'wcfm_allow_view_customer_name', true ) ) {
        $user_info = array();
        if ( $the_order->get_user_id() ) {
          $user_info = get_userdata( $the_order->get_user_id() );
        }

        if ( ! empty( $user_info ) ) {

          $username = '';

          if ( $user_info->first_name || $user_info->last_name ) {
            $username .= esc_html( sprintf( _x( '%1$s %2$s', 'full name', 'wc-frontend-manager' ), ucfirst( $user_info->first_name ), ucfirst( $user_info->last_name ) ) );
          } else {
            $username .= esc_html( ucfirst( $user_info->display_name ) );
          }

        } else {
          if ( $the_order->get_billing_first_name() || $the_order->get_billing_last_name() ) {
            $username = trim( sprintf( _x( '%1$s %2$s', 'full name', 'wc-frontend-manager' ), $the_order->get_billing_first_name(), $the_order->get_billing_last_name() ) );
          } else if ( $the_order->get_billing_company() ) {
            $username = trim( $the_order->get_billing_company() );
          } else {
            $username = __( 'Guest', 'wc-frontend-manager' );
          }
        }
        
        $username = apply_filters( 'wcfm_order_by_user', $username, $the_order->get_id() );
      } else {
        $username = __( 'Guest', 'wc-frontend-manager' );
      }

      $response[$key]['customer']['name'] = $username;

      //email
      if( apply_filters( 'wcfm_allow_view_customer_email', true ) && $the_order->get_billing_email() ) {
        $user_email = $the_order->get_billing_email();
      }
      $response[$key]['customer']['email'] = $user_email;

      if( apply_filters( 'wcfm_allow_view_customer_phone', true ) && $the_order->get_billing_phone() ) {
        $user_phone = $the_order->get_billing_phone();
      }
      $response[$key]['customer']['phone'] = $user_phone;

      if( apply_filters( 'wcfm_allow_customer_shipping_details', true ) ) {
        if ( $the_order->get_formatted_shipping_address() ) {
          $response[$key]['shipping_address'] = $the_order->get_address( 'shipping' );
          $country = $response[$key]['shipping_address']['country'];
          $state = $response[$key]['shipping_address']['state'];

          $response[$key]['shipping_address']['country'] = ( isset( WC()->countries->countries[ $country ] ) ) ? WC()->countries->countries[ $country ] : $country;
          $response[$key]['shipping_address']['state'] = ( $country && $state && isset( WC()->countries->states[ $country ][ $state ] ) ) ? WC()->countries->states[ $country ][ $state ] : $state;
        }
      }

      if( $wcfm_delivery_order_single->delivery_status == 'delivered' && $wcfm_delivery_order_single->delivery_date ) {
        $response[$key]['delivered_on'] = $wcfm_delivery_order_single->delivery_date;
      }

    }

    return $response;
  }

  public function update_delivery_status($request) {
    global $WCFM, $WCFMd, $wpdb;
    $id = isset( $request['id'] ) ? absint( $request['id'] ) : 0;
    if ( empty( $id ) ) {
        return new WP_Error( "wcfmapi_rest_invalid_delivery_id", __( 'Invalid delivery ID', 'wcfm-marketplace-rest-api' ), array(
            'status' => 404,
        ) );
    }

    $sql  = "SELECT * FROM `{$wpdb->prefix}wcfm_delivery_orders`";
    $sql .= " WHERE 1=1";
    $sql .= " AND ID = {$id}";
    $delivery_details = $wpdb->get_results( $sql );

    if ( empty( $delivery_details ) ) {
        return new WP_Error( "wcfmapi_rest_invalid_delivery_id", __( 'Invalid delivery ID', 'wcfm-marketplace-rest-api' ), array(
            'status' => 404,
        ) );
    }

    $_POST['delivery_id'] = $id;
    define('WCFM_REST_API_CALL', TRUE);
    $WCFM->init();
    $WCFMd->ajax->wcfmd_mark_order_delivered();

    $sql  = "SELECT * FROM `{$wpdb->prefix}wcfm_delivery_orders`";
    $sql .= " WHERE 1=1";
    $sql .= " AND ID = {$id}";
    $wcfm_delivery_orders_array = $wpdb->get_results( $sql );

    $response = $this->get_delivery_items($wcfm_delivery_orders_array);

    return rest_ensure_response( $response );
  }
  
}