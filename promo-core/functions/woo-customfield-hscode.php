<?php
defined('ABSPATH') || exit;

//////////////////////////////////
// Function contents start HERE //
//////////////////////////////////



/**
 * 1) Maak de Shipping-tab zichtbaar voor alle producttypes.
 */
add_filter( 'woocommerce_product_data_tabs', function( $tabs ) {
	if ( isset( $tabs['shipping']['class'] ) && is_array( $tabs['shipping']['class'] ) ) {
		$tabs['shipping']['class'] = array_values(
			array_diff(
				$tabs['shipping']['class'],
				array( 'hide_if_virtual', 'hide_if_grouped', 'hide_if_external' )
			)
		);
	}

	return $tabs;
} );

/**
 * 2) Voeg HS Code text field toe in de Shipping-tab.
 */
add_action( 'woocommerce_product_options_shipping', function() {
	global $product_object;

	$value = '';
	if ( $product_object instanceof WC_Product ) {
		$value = $product_object->get_meta( '_hs_code', true );
	}

	woocommerce_wp_text_input(
		array(
			'id'          => '_hs_code',
			'label'       => __( 'HS Code', 'your-textdomain' ),
			'description' => __( 'Harmonized System code voor douane/verzending.', 'your-textdomain' ),
			'desc_tip'    => true,
			'type'        => 'text',
			'value'       => $value,
		)
	);
} );

/**
 * 3) Sla HS Code op bij product save.
 */
add_action( 'woocommerce_admin_process_product_object', function( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return;
	}

	$hs_code = isset( $_POST['_hs_code'] )
		? sanitize_text_field( wp_unslash( $_POST['_hs_code'] ) )
		: '';

	if ( '' === $hs_code ) {
		$product->delete_meta_data( '_hs_code' );
	} else {
		$product->update_meta_data( '_hs_code', $hs_code );
	}
} );

/**
 * 4) Kopieer HS Code naar de orderregel bij checkout.
 * Daardoor komt hij automatisch mee in e-mails en orderdetails.
 */
add_action( 'woocommerce_checkout_create_order_line_item', function( $item, $cart_item_key, $values, $order ) {
	if ( empty( $values['data'] ) || ! $values['data'] instanceof WC_Product ) {
		return;
	}

	$product = $values['data'];
	$hs_code = $product->get_meta( '_hs_code', true );

	// Fallback voor variaties: pak parent product meta als de variatie zelf geen HS Code heeft.
	if ( '' === $hs_code && $product->is_type( 'variation' ) ) {
		$parent_id = $product->get_parent_id();

		if ( $parent_id ) {
			$parent_product = wc_get_product( $parent_id );

			if ( $parent_product instanceof WC_Product ) {
				$hs_code = $parent_product->get_meta( '_hs_code', true );
			}
		}
	}

	$hs_code = is_string( $hs_code ) ? trim( $hs_code ) : '';

	if ( '' !== $hs_code ) {
		$item->add_meta_data( 'HS Code', $hs_code, true );
	}
}, 10, 4 );
