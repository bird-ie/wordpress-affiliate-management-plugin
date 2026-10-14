<?php
/**
 * RetaEU - Affiliate Invitation Banner
 * Display an affiliate invitation banner on the WooCommerce
 * My Account Dashboard for loyal customers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*--------------------------------------------------------------
# Affiliate Banner
--------------------------------------------------------------*/

add_action( 'woocommerce_account_dashboard', 'retaeu_affiliate_dashboard_banner', 15 );

function retaeu_affiliate_dashboard_banner() {

	if ( ! is_user_logged_in() ) {
		return;
	}

	$user_id = get_current_user_id();

	/**
	 * Hide if user already dismissed it.
	 */
// 	if ( get_user_meta( $user_id, '_retaeu_hide_affiliate_banner', true ) ) {
// 		return;
// 	}

	/**
	 * Only show to customers who have at least
	 * one processing or completed order.
	 */
	$orders = wc_get_orders( array(
		'customer_id' => $user_id,
		'status'      => array(
			'processing',
			'completed',
		),
		'limit'       => 1,
		'return'      => 'ids',
	) );

	if ( empty( $orders ) ) {
		return;
	}

	/**
	 * Hide if user is already registered
	 * in the custom affiliate system.
	 */
	global $wpdb;

	$affiliate_exists = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT id
			FROM {$wpdb->prefix}act_affiliates
			WHERE user_id = %d",
			$user_id
		)
	);

	if ( $affiliate_exists ) {
		return;
	}

	/**
	 * Affiliate registration page.
	 * Change if needed.
	 */
	$affiliate_url = home_url( '/affiliate-registration/' );
	?>

	<div class="retaeu-affiliate-banner">

		<button
			class="retaeu-affiliate-close"
			type="button"
			aria-label="<?php esc_attr_e( 'Dismiss', 'retaeu' ); ?>">
			&times;
		</button>

		<div class="retaeu-affiliate-content">

			<div class="retaeu-affiliate-icon">

				<svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

					<path d="M12 2L15 8L22 9L17 14L18.5 21L12 17.8L5.5 21L7 14L2 9L9 8L12 2"/>

				</svg>

			</div>

			<div class="retaeu-affiliate-text">

				<h3>
					Become an Official RetaEU Affiliate
				</h3>

				<p>
					Love your experience with RetaEU?
					Share your personal discount code,
					earn commission on every successful referral,
					and become part of our growing affiliate community.
				</p>

			</div>

			<div class="retaeu-affiliate-action">

				<a
					href="<?php echo esc_url( $affiliate_url ); ?>"
					class="button">

					Become an Affiliate

				</a>

			</div>

		</div>

	</div>

	<?php
}

/*--------------------------------------------------------------
# Assets
--------------------------------------------------------------*/

add_action( 'wp_footer', 'retaeu_affiliate_banner_assets' );

function retaeu_affiliate_banner_assets() {

	if ( ! is_account_page() || ! is_user_logged_in() ) {
		return;
	}

	?>

	<style>
.retaeu-affiliate-banner{
	position:relative !important;
	margin:28px 0 !important;
	padding:32px !important;
	border-radius:18px !important;
	background:linear-gradient(135deg,#0C3B78,#1559C4) !important;
	box-shadow:0 18px 45px rgba(15,43,84,.15) !important;
	overflow:hidden !important;
	color:#fff !important;
}

.retaeu-affiliate-banner::before{
	content:"" !important;
	position:absolute !important;
	right:-80px !important;
	top:-80px !important;
	width:220px !important;
	height:220px !important;
	background:rgba(255,255,255,.08) !important;
	border-radius:50% !important;
}

.retaeu-affiliate-banner::after{
	content:"" !important;
	position:absolute !important;
	right:120px !important;
	bottom:-70px !important;
	width:160px !important;
	height:160px !important;
	background:rgba(255,255,255,.05) !important;
	border-radius:50% !important;
}

.retaeu-affiliate-content{
	position:relative !important;
	z-index:2 !important;
	display:flex !important;
	align-items:center !important;
	gap:24px !important;
	flex-wrap:wrap !important;
}

.retaeu-affiliate-icon{
	width:74px !important;
	height:74px !important;
	background:rgba(255,255,255,.15) !important;
	border-radius:50% !important;
	display:flex !important;
	align-items:center !important;
	justify-content:center !important;
	flex-shrink:0 !important;
}

.retaeu-affiliate-icon svg{
	color:#fff !important;
	width:42px !important;
	height:42px !important;
}

.retaeu-affiliate-text{
	flex:1 !important;
	min-width:260px !important;
}

.retaeu-affiliate-text h3{
	margin:0 0 10px !important;
	font-size:24px !important;
	font-weight:700 !important;
	color:#fff !important;
	line-height:1.3 !important;
}

.retaeu-affiliate-text p{
	margin:0 !important;
	font-size:15px !important;
	line-height:1.8 !important;
	color:rgba(255,255,255,.92) !important;
}

.retaeu-affiliate-action{
	flex-shrink:0 !important;
}

.retaeu-affiliate-action .button{
	display:inline-flex !important;
	align-items:center !important;
	justify-content:center !important;
	padding:16px 34px !important;
	border-radius:50px !important;
	background:#fff !important;
	color:#0C3B78 !important;
	font-size:15px !important;
	font-weight:700 !important;
	text-decoration:none !important;
	border:none !important;
	box-shadow:0 8px 20px rgba(0,0,0,.12) !important;
	transition:all .3s ease !important;
}

.retaeu-affiliate-action .button:hover{
	background:#f5f7fb !important;
	color:#0C3B78 !important;
	transform:translateY(-3px) !important;
	box-shadow:0 14px 30px rgba(0,0,0,.18) !important;
}

.retaeu-affiliate-close{
	position:absolute !important;
	top:16px !important;
	right:18px !important;
	width:34px !important;
	height:34px !important;
	border:none !important;
	background:rgba(255,255,255,.12) !important;
	border-radius:50% !important;
	color:#fff !important;
	font-size:22px !important;
	line-height:1 !important;
	cursor:pointer !important;
	display:flex !important;
	align-items:center !important;
	justify-content:center !important;
	transition:all .25s ease !important;
	z-index:5 !important;
	padding:0 !important;
}

.retaeu-affiliate-close:hover{
	background:rgba(255,255,255,.22) !important;
	transform:rotate(90deg) !important;
}

@media (max-width:991px){

	.retaeu-affiliate-content{
		flex-direction:column !important;
		align-items:flex-start !important;
	}

	.retaeu-affiliate-action{
		width:100% !important;
	}

	.retaeu-affiliate-action .button{
		width:100% !important;
	}

}

@media (max-width:767px){

	.retaeu-affiliate-banner{
		padding:24px !important;
	}

	.retaeu-affiliate-icon{
		width:62px !important;
		height:62px !important;
	}

	.retaeu-affiliate-text h3{
		font-size:21px !important;
	}

}
	</style>

	<script>

	jQuery(function($){

		$('.retaeu-affiliate-close').on('click', function(e){

			e.preventDefault();

			$('.retaeu-affiliate-banner').slideUp(250);

		});

	});

	</script>

	<?php

}