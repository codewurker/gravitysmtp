<?php

namespace Gravity_Forms\Gravity_SMTP\Connectors\Endpoints;

use Gravity_Forms\Gravity_SMTP\Connectors\Connector_Factory;
use Gravity_Forms\Gravity_SMTP\Data_Store\Plugin_Opts_Data_Store;
use Gravity_Forms\Gravity_SMTP\Logging\Debug\Debug_Logger;
use Gravity_Forms\Gravity_SMTP\Models\Event_Model;
use Gravity_Forms\Gravity_SMTP\Models\Log_Details_Model;
use Gravity_Forms\Gravity_Tools\Endpoints\Endpoint;

class Send_Test_Endpoint extends Endpoint {

	const PARAM_EMAIL = 'email';
	const PARAM_CONNECTOR_NAME = 'connector_name';

	const ACTION_NAME = 'send_test';


	/**
	 * @var Connector_Factory $connector_factory
	 */
	protected $connector_factory;

	/**
	 * @var Plugin_Opts_Data_Store
	 */
	protected $plugin_data;

	/**
	 * @var Event_Model
	 */
	protected $emails;

	/**
	 * @var Log_Details_Model
	 */
	protected $logs;

	/**
	 * @var Get_Single_Email_Data_Endpoint
	 */
	protected $email_endpoint;

	protected $required_params = array(
		self::PARAM_EMAIL,
	);

	public function __construct( $connector_factory, $plugin_data_store, $emails_model, $log_model, $email_endpoint ) {
		$this->connector_factory = $connector_factory;
		$this->plugin_data       = $plugin_data_store;
		$this->emails            = $emails_model;
		$this->logs              = $log_model;
		$this->email_endpoint    = $email_endpoint;
	}

	protected function get_nonce_name() {
		return self::ACTION_NAME;
	}

	protected function get_test_email_markup() {
		$image_base_url = \Gravity_Forms\Gravity_SMTP\Gravity_SMTP::get_base_url() . '/assets/images/send-test/';

		return '<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Email Template</title>
	<style>
		body {
			margin: 0;
			padding: 0;
			background: #fff;
			font-family: inter, -apple-system, blinkmacsystemfont, \'Segoe UI\', roboto, oxygen-sans, ubuntu, cantarell, \'Helvetica Neue\', sans-serif;
		}

		img {
			border: 0 none;
			height: auto;
			line-height: 100%;
			outline: none;
			text-decoration: none;
		}

		a img {
			border: 0 none;
		}

		table, td {
			border-collapse: collapse;
		}

		#bodyTable {
			height: 100% !important;
			margin: 0;
			padding: 0;
			width: 100% !important;
		}
		.wrapper {
			max-width: 680px;
			margin: 0 auto;
		}
		.content {
			padding: 20px 20px 200px;
		}

		@media only screen and (max-width: 480px) {
			.content {
				padding: 20px 20px 120px;
			}
		}
	</style>
</head>
<body>
<table id="bodyTable" role="presentation" width="100%" align="center"
       style="background: url(\'' . $image_base_url . 'gravitysmtp-arrow-bg.png\') no-repeat top right / 514px 647px #fff; margin: 0;">
	<tr>
		<td>
			<table class="wrapper" role="presentation">
				<!-- Header with Logo -->
				<tr>
					<td style="padding: 70px 20px 32px; text-align: center;">
						<img src="' . $image_base_url . 'gravitysmtp-email-logo.png" alt="' . esc_html__( 'Logo', 'gravitysmtp' ) . '"
						     style="display: block; margin: 0 auto; max-width: 200px">
					</td>
				</tr>
				<!-- Content Area -->
				<tr>
					<td class="content">
						<img src="' . $image_base_url . 'gravitysmtp-success.png" alt="' . esc_html__( 'Mail Icon', 'gravitysmtp' ) . '"
						     style="display: block; margin: 0 auto; max-width: 308px">
						<h1 style="color: #242748; text-align: center; font-family: inter, -apple-system, blinkmacsystemfont, \'Segoe UI\', roboto, oxygen-sans, ubuntu, cantarell, \'Helvetica Neue\', sans-serif; font-size: 30px; font-style: normal; font-weight: 600; line-height: 30px; padding: 20px 0 32px; margin: 0;">' . esc_html__( 'Test Successful', 'gravitysmtp' ) . '</h1>
						<div
							style="border-radius: 3px; border: 1px solid #d5d7e9; box-shadow: 0px 2px 2px 0px rgba(58, 58, 87, 0.06);">
							<p style="margin: 0; padding: 12px 24px; background: #f6f9fc; font-family: inter, -apple-system, blinkmacsystemfont, \'Segoe UI\', roboto, oxygen-sans, ubuntu, cantarell, \'Helvetica Neue\', sans-serif; border-bottom: 1px solid #d5d7e9; color: #242748; font-size: 14px; font-style: normal; font-weight: 500; line-height: 18px;">' . esc_html__( 'Congratulations! Gravity SMTP is sending emails correctly!', 'gravitysmtp' ) . '</p>
							<p style="color: #5b5e80; background: #fff; margin: 0; padding: 16px 24px; font-family: inter, -apple-system, blinkmacsystemfont, \'Segoe UI\', roboto, oxygen-sans, ubuntu, cantarell, \'Helvetica Neue\', sans-serif; font-size: 14px; font-style: normal; font-weight: 400; line-height: 20px">' . esc_html__( 'Gravity SMTP is taking care of sending your emails, so now you can focus on the content of your emails and leave the technical details to us.', 'gravitysmtp' ) . '</p>
						</div>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
</body>
</html>';
	}

	public function handle() {
		if ( ! $this->validate() ) {
			wp_send_json_error( __( 'Missing required parameters.', 'gravitysmtp' ), 400 );
		}

		$email     = filter_input( INPUT_POST, self::PARAM_EMAIL, FILTER_SANITIZE_EMAIL );
		$connector = filter_input( INPUT_POST, self::PARAM_CONNECTOR_NAME );

		if ( empty( $connector ) ) {
			$connector = $this->plugin_data->get_active_connector();
		} else {
			$connector = htmlspecialchars( $connector );
		}

		if ( empty( $connector ) ) {
			Debug_Logger::log_message(
				__( 'Send a test error: No email integration configured.', 'gravitysmtp' ),
				'error'
			);
			wp_send_json_error( __( 'No email integration configured, please configure an integration and try again.', 'gravitysmtp' ), 500 );
		}

		$provider = $this->connector_factory->create( $connector );
		$provider->init( array( 'email' => $email ), __( 'Test Email from Gravity SMTP', 'gravitysmtp' ), $this->get_test_email_markup(), array( 'content-type' => 'text/html' ), array(), 'GravitySMTP Test' );

		$success = $provider->send();

		if ( $success === true ) {
			wp_send_json_success( array( 'email' => $email ) );
		}

		$full_log = $this->get_full_log_data( $success );
		$issues   = array();
		$log_copy = '';
		if ( is_array( $full_log['technical_information']['log'] ) ) {
			array_push( $issues, end( $full_log['technical_information']['log'] ) );
			$log_copy = implode( "\r\n", $full_log['technical_information']['log'] );
		}

		$error_data = array(
			'error_message'     => __( 'There was a problem sending the test email.', 'gravitysmtp' ),
			'issues'            => $issues,
			'full_log'          => $full_log,
			'log_copy'          => $log_copy,
			'possible_reasons'  => array(
				__( 'Incorrect plugin settings, such as invalid SMTP credentials or expired API key.', 'gravitysmtp' ),
				__( 'The SMTP server blocking the incoming connection.', 'gravitysmtp' ),
				__( 'Your web host rejecting the connection.', 'gravitysmtp' ),
			),
			'recommended_steps' => array(
				__( 'Triple check the plugin settings and ensure they are accurate, especially if you copy-pasted the values.', 'gravitysmtp' ),
				__( 'Contact your web hosting provider to verify if your server allows outside connections and if any firewall or security policies are in place that could interfere.', 'gravitysmtp' ),
				__( 'Consider using one of the other available integration types.', 'gravitysmtp' ),
			),
		);

		Debug_Logger::log_message(
			sprintf(
				__( 'Send a test error: %1$s', 'gravitysmtp' ),
				json_encode( $error_data )
			),
			'error'
		);

		wp_send_json_error( $error_data, 500 );
	}

	private function get_full_log_data( $email_id ) {
		return $this->email_endpoint->get_log_details( $email_id );
	}

}