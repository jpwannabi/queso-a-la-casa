<?php
/*
For Plugin: WooCommerce Table Rate Shipping
Description: Creates new settings page for zone shipping.
	Additionally functions are defined to compare data for other functions
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

	/**
	 * Check if WooCommerce is active
	 */
	if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

		if (!class_exists('WC_Shipping_Method')) return;

		include(plugin_dir_path(__FILE__).'zone-list-table.php');

		function create_new_tab() {
	    	$current_tab = ( empty( $_GET['tab'] ) ) ? 'general' : sanitize_text_field( urldecode( $_GET['tab'] ) );

			echo '<a href="' . admin_url( 'admin.php?page=woocommerce_settings&tab=shipping_zones' ) . '" class="nav-tab ';
			if( $current_tab == 'shipping_zones' ) echo 'nav-tab-active';
			echo '">Shipping Zones</a>';
		}
		add_action('woocommerce_settings_tabs','create_new_tab');

		function jquery_admin_init() {
	        /* Register our script. */
	        wp_enqueue_script( 'jquery-ui-sortable' );
	    }
	    add_action( 'admin_init', 'jquery_admin_init' );

		function be_table_rate_shipping_zones() {
			global $woocommerce;

			if(isset($_GET['upgrade']) && $_GET['upgrade'] == 'zones') :
				remove_action('woocommerce_update_options_shipping_zones','be_save_new_zone');
				BE_Table_Rate_Shipping::install_plugin_button();
			elseif(isset($_GET['action']) && ($_GET['action'] == 'edit' || $_GET['action'] == 'new' || $_GET['action'] == 'delete')) :
				if($_GET['action'] == 'delete' || isset($_POST['save'])) be_save_new_zone();
				else Zone_List_Table::tt_render_edit_page();
			elseif(isset($_POST['action'])) :
				be_save_new_zone();
			else :
				$GLOBALS['hide_save_button'] = true;
				Zone_List_Table::tt_render_list_page();
			endif;
		}
		add_action('woocommerce_settings_tabs_shipping_zones','be_table_rate_shipping_zones');

		function be_save_new_zone() {
			global $woocommerce;

	        $shipping_zones = array_filter( (array) get_option( 'be_woocommerce_shipping_zones' ) );
			if(isset($_POST['action'])) {
				if($_POST['action'] == 'delete') {
					if(is_array($_POST['zone']) && count($_POST['zone']) > 0) {
						foreach ($_POST['zone'] as $value) {
			                $zone_verify = $shipping_zones[$value];
			                if(is_array($zone_verify))  {
			                    unset($shipping_zones[$value]);
			                }
						}
			            update_option('be_woocommerce_shipping_zones', $shipping_zones);
					}
					$redirect = add_query_arg( array('saved' => 'true', 'action' => false, 'zone' => false ));
				} else {
					$i = 1;
					if(count($_POST['zone_id']) > 0) {
						$new_order = array();
						foreach ($_POST['zone_id'] as $value) {
							$zone_id = (int) $value;
			                $zone_verify = $shipping_zones[$zone_id];
			                if(is_array($zone_verify)){
			                    $new_order[$zone_id] = $shipping_zones[$zone_id];
			                    $new_order[$zone_id]['zone_order'] = $i;
			                }
						}
			            update_option('be_woocommerce_shipping_zones', $new_order);
					}
					$redirect = add_query_arg( array('saved' => 'true', 'action' => false, 'zone' => false ));
				}
			} elseif(isset($_GET['action']) && $_GET['action'] == 'delete') {
				if(isset($_GET['zone'])) {
					$zone_id = (int) $_GET['zone'];
	                $zone_verify = $shipping_zones[$zone_id];
	                if(is_array($zone_verify))  {
	                    unset($shipping_zones[$zone_id]);
		            	update_option('be_woocommerce_shipping_zones', $shipping_zones);
						$redirect = add_query_arg( array('saved' => 'true', 'action' => false, 'zone' => false ));
	                }
				}
			} elseif (isset($_GET['action']) && ( $_GET['action'] == 'new' || $_GET['action'] == 'edit') ) {
	            $totalitems = count($shipping_zones);
	            $max_keys = array();

				$zone_id_posted = (int) $_POST['zone_id'];
				$zone_enabled = ( isset( $_POST['zone_enabled'] ) ) ? '1' : '0';
				$zone_title = sanitize_text_field($_POST['zone_title']);
				$zone_description = sanitize_text_field($_POST['zone_description']);
				$zone_type = sanitize_text_field($_POST['zone_type']);

				if($zone_id_posted == 0) {
					if(count($shipping_zones) > 0) {
						foreach ($shipping_zones as $value) {
							$max_keys[] = $value['zone_order'];
						}
						$zone_order_max = max($max_keys);
						$zone_id_max = max(array_keys($shipping_zones))+1;
					} else {
						$zone_order_max = 0;
						$zone_id_max = 1;
					}
				}

				if($zone_type == 'countries') {
					$zone_country = ( isset( $_POST[ 'location_countries' ] ) ) ? (array) $_POST[ 'location_countries' ] : array();
					$zone_country = implode( ',', $zone_country );
					$zone_country_except = ( isset( $_POST[ 'location_countries_exceptS' ] ) ) ? (array) $_POST[ 'location_countries_exceptS' ] : array();
					$zone_country_except = implode( ',', $zone_country_except );
					$zone_postal_except = sanitize_text_field( $_POST['location_countries_except'] );
					$zone_postal_except = preg_replace( '/\s+/', '', $zone_postal_except );
					$zone_except = array('states' => $zone_country_except, 'postals' => $zone_postal_except);
					$zone_postal = '';
				} elseif($zone_type == 'postal') {
					$zone_country = sanitize_text_field( $_POST['location_country'] );
					$zone_postal = sanitize_text_field( $_POST['location_codes'] );
					//$zone_postal = preg_replace( '/\s+/', '', $zone_postal );
					$zone_except = ( isset( $_POST[ 'location_postal_except' ] ) ) ? sanitize_text_field( $_POST[ 'location_postal_except' ] ) : '';
					//$zone_except = preg_replace( '/\s+/', '', $zone_except );
				} else {
					$zone_country = $zone_postal = "";
					$zone_except = ( isset( $_POST[ 'location_everywhere_except' ] ) ) ? (array) $_POST[ 'location_everywhere_except' ] : array();
					$zone_except = implode( ',', $zone_except );
				}

				if($zone_id_posted != 0) :
					$shipping_zones[$zone_id_posted] = array(
						'zone_id' => $zone_id_posted,
						'zone_enabled' => $zone_enabled,
						'zone_title' => $zone_title,
						'zone_description' => $zone_description,
						'zone_type' => $zone_type,
						'zone_country' => $zone_country,
						'zone_postal' => $zone_postal,
						'zone_except' => $zone_except,
						'zone_order' => $shipping_zones[$zone_id_posted]['zone_order']
						);
				else :
					$shipping_zones[$zone_id_max] = array(
						'zone_id' => $zone_id_max,
						'zone_enabled' => $zone_enabled,
						'zone_title' => $zone_title,
						'zone_description' => $zone_description,
						'zone_type' => $zone_type,
						'zone_country' => $zone_country,
						'zone_postal' => $zone_postal,
						'zone_except' => $zone_except,
						'zone_order' => $zone_order_max+1
						);
				endif;
				update_option('be_woocommerce_shipping_zones', $shipping_zones);

				// Clear any unwanted data
				$woocommerce->clear_product_transients();
				if($zone_id == 0) $zone_id = $zone_id_posted;

				delete_transient( 'woocommerce_cache_excluded_uris' );

				$zone_id = ($zone_id_posted == 0) ? $zone_id_max : $zone_id_posted;

				// Redirect back to the settings page
				$redirect = add_query_arg( array('saved' => 'true', 'action' => 'edit', 'zone' => $zone_id ));
			}

			wp_safe_redirect( $redirect );
			exit;
		}
		add_action('woocommerce_update_options_shipping_zones','be_save_new_zone');

		function be_get_zones() {
			$zoneList = new Zone_List_Table();
			$zones = $zoneList->shipping_zones;
			return $zones;
		}

		function be_in_zone($zone_id, $country, $state, $zipcode) {
			$zones = get_option( 'be_woocommerce_shipping_zones' );
			$zone = $zones[$zone_id];
			if(count($zone) > 0) :
				if($zone['zone_enabled'] == 0) return false;

				switch ($zone['zone_type']) {
		            case 'everywhere':
                		$countries_abbr = explode(',', $zone['zone_except']);
		    			if(in_array($country, $countries_abbr) || in_array($country.":".$state, $countries_abbr))
		    				return false;
		    			else return true;
		            case 'countries':
                		$countries_abbr = explode(',', $zone['zone_country']);
		    			if(in_array($country, $countries_abbr) || in_array($country.":".$state, $countries_abbr)) {
		    				if( isset( $zone['zone_except']['states'] ) && count( $zone['zone_except']['states'] ) ) {
		    					$states_excluded = explode( ',', $zone['zone_except']['states'] );
		    					if(in_array($country, $states_excluded) || in_array($country.":".$state, $states_excluded))
		    						return false;
		    				}
		    				if( isset( $zone['zone_except']['postals'] ) && $zone['zone_except']['postals'] != '' ) {
		    					$postals_excluded = str_replace( ', ', ',', $zone['zone_except']['postals'] );
								foreach( explode( ',', $postals_excluded ) as $code ) {
									$code_clean = str_replace('^', '', $code);
			    					if($code_clean == $zipcode) {
										return false;
			    					} elseif(strstr( $code, '-' )) {
			    						$code_clean = str_replace( ' - ', '-', $code_clean );
			    						list($code_1,$code_2) = explode('-', $code_clean);
			    						$range = range($code_1,$code_2);
			    						if(in_array($zipcode, $range))
												return false;
			    					} elseif(strstr( $code, '*' )) {
										$code_length = strlen( $code_clean ) - 1;
										if (strtolower(substr($code_clean, 0, -1)) == strtolower(substr($zipcode, 0, $code_length)))
											return false;
									}
								}
		    				}
		    				return true;
		    			} else return false;
		            case 'postal':
		    			if($country == $zone['zone_country'] || $country.":".$state == $zone['zone_country']) {
		    				$zone['zone_postal'] = str_replace( ', ', ',', $zone['zone_postal'] );
		    				if ( $zone['zone_postal'] != '' ) {
		    					$in_range = false;
								foreach( explode( ',', $zone['zone_postal'] ) as $code ) {
									$code_clean = str_replace('^', '', $code);
			    					if($code_clean == $zipcode) {
										if(!strstr( $code, '^' )) $in_range = true; 
											else return false;
			    					} elseif(strstr( $code, '-' )) {
			    						$code_clean = str_replace( ' - ', '-', $code_clean );
			    						list($code_1,$code_2) = explode('-', $code_clean);
			    						$range = range($code_1,$code_2);
			    						if(in_array($zipcode, $range))
											if(!strstr( $code, '^' )) $in_range = true; 
												else return false;
			    					} elseif(strstr( $code, '*' )) {
										$code_length = strlen( $code_clean ) - 1;
										if (strtolower(substr($code_clean, 0, -1)) == strtolower(substr($zipcode, 0, $code_length)))
											if(!strstr( $code, '^' )) $in_range = true; 
												else return false;
									}
								}
								if($in_range) {
				    				if( isset( $zone['zone_except'] ) && $zone['zone_except'] != '' ) {
				    					$postals_excluded = str_replace( ', ', ',', $zone['zone_except'] );
										foreach( explode( ',', $postals_excluded ) as $code ) {
											$code_clean = str_replace('^', '', $code);
					    					if($code_clean == $zipcode) {
												return false;
					    					} elseif(strstr( $code, '-' )) {
					    						$code_clean = str_replace( ' - ', '-', $code_clean );
					    						list($code_1,$code_2) = explode('-', $code_clean);
					    						$range = range($code_1,$code_2);
					    						if(in_array($zipcode, $range))
														return false;
					    					} elseif(strstr( $code, '*' )) {
												$code_length = strlen( $code_clean ) - 1;
												if (strtolower(substr($code_clean, 0, -1)) == strtolower(substr($zipcode, 0, $code_length)))
													return false;
											}
										}
				    				}
				    				return true;
								} else return false;
							}
		    			}
					default:
						return false;
				}
			else :
				return false;
			endif;
		}
	}