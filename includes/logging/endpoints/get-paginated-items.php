<?php

ini_set( 'html_errors', 0 );
define( 'SHORTINIT', true );

function run() {
	require '../../../../../../wp-load.php';
	require '../../models/traits/trait-can-compare-dynamically.php';
	require '../../models/class-event-model.php';
	require '../../models/hydrators/class-hydrator-factory.php';
	require '../../datastore/interface-data-store.php';
	require '../../datastore/class-plugin-opts-data-store.php';
	require '../../users/class-roles.php';
	require '../../utils/class-recipient.php';
	require '../../utils/class-recipient-collection.php';
	require '../../utils/class-recipient-parser.php';
	require '../../enums/class-status-enum.php';
	require '../../models/hydrators/interface-hydrator.php';
	require '../../models/hydrators/class-hydrator-wp-mail.php';
	require '../../models/hydrators/class-hydrator-brevo.php';
	require '../../models/hydrators/class-hydrator-generic.php';
	require '../../models/hydrators/class-hydrator-mailgun.php';
	require '../../models/hydrators/class-hydrator-postmark.php';
	require '../../models/hydrators/class-hydrator-sendgrid.php';

	require_once( ABSPATH . WPINC . '/default-constants.php' );
	require_once( ABSPATH . WPINC . '/class-wp-textdomain-registry.php' );
	require_once( ABSPATH . WPINC . '/capabilities.php' );
	require_once( ABSPATH . WPINC . '/class-wp-session-tokens.php' );
	require_once( ABSPATH . WPINC . '/class-wp-user-meta-session-tokens.php' );
	require_once( ABSPATH . WPINC . '/class-wp-role.php' );
	require_once( ABSPATH . WPINC . '/class-wp-roles.php' );
	require_once( ABSPATH . WPINC . '/class-wp-user.php' );
	require_once( ABSPATH . WPINC . '/l10n.php' );
	require_once( ABSPATH . WPINC . '/user.php' );
	require_once( ABSPATH . WPINC . '/pluggable.php' );
	require_once( ABSPATH . WPINC . '/rest-api.php' );
	require_once( ABSPATH . WPINC . '/kses.php' );
	require_once( ABSPATH . WPINC . '/blocks.php' );

	wp_plugin_directory_constants();
	wp_cookie_constants();

	check_ajax_referer( 'activity_log_page', 'security' );

	$GLOBALS['wp_textdomain_registry'] = new WP_Textdomain_Registry();

	$per_page       = filter_input( INPUT_POST, 'per_page', FILTER_SANITIZE_NUMBER_INT );
	$requested_page = filter_input( INPUT_POST, 'requested_page', FILTER_SANITIZE_NUMBER_INT );
	$max_date       = filter_input( INPUT_POST, 'max_date' );
	$search_term    = filter_input( INPUT_POST, 'search_term' );
	$search_type = filter_input( INPUT_POST, 'search_type' );

	if ( ! empty( $max_date ) ) {
		$max_date = htmlspecialchars( $max_date );
	}

	if ( ! empty( $search_term ) ) {
		$search_term = htmlspecialchars( $search_term );
	}

	if ( ! empty( $search_type ) ) {
		$search_type = htmlspecialchars( $search_type );
	}

	$offset = ( $requested_page - 1 ) * $per_page;

	if ( ! $max_date ) {
		$max_date = date( 'Y-m-d H:i:s', time() );
	}

	if ( empty( $per_page ) ) {
		$per_page = 20;
	}

	$event_model = new \Gravity_Forms\Gravity_SMTP\Models\Event_Model( new \Gravity_Forms\Gravity_SMTP\Models\Hydrators\Hydrator_Factory(), new \Gravity_Forms\Gravity_SMTP\Data_Store\Plugin_Opts_Data_Store(), new \Gravity_Forms\Gravity_SMTP\Utils\Recipient_Parser() );
	$rows        = $event_model->paginate( $requested_page, $per_page, $max_date, $search_term, $search_type );
	$count       = $event_model->count( $search_term, $search_type );

	$data = array(
		'rows'      => get_formatted_data_rows( $rows ),
		'total'     => $count,
		'row_count' => count( $rows ),
	);

	wp_send_json_success( $data );
}

function get_formatted_data_rows( $data ) {
	$rows             = array();
	$recipient_parser = new \Gravity_Forms\Gravity_SMTP\Utils\Recipient_Parser();

	foreach ( $data as $row ) {
		$grid_actions = get_grid_actions( $row['id'] );
		$extra        = strpos( $row['extra'], '{' ) === 0 ? json_decode( $row['extra'], true ) : unserialize( $row['extra'] );
		$to_address   = $recipient_parser->parse( $extra['to'] )->first()->email();
		$more_count   = max( 0, $row['email_counts'] - 1 );

		$rows[] = array(
			'id'      => $row['id'],
			'subject' => array(
				'component' => 'Button',
				'props'     => array(
					'action'        => 'view',
					'customClasses' => array( 'gravitysmtp-data-grid__subject' ),
					'label'         => $row['subject'],
					'type'          => 'unstyled',
					'data'          => array(
						'event_id' => $row['id'],
					),
				),
			),
			'status'  => array(
				'component' => 'StatusIndicator',
				'props'     => array(
					'label'  => \Gravity_Forms\Gravity_SMTP\Enums\Status_Enum::label( $row['status'] ),
					'status' => \Gravity_Forms\Gravity_SMTP\Enums\Status_Enum::indicator( $row['status'] ),
					'hasDot' => false,
				),
			),
			'source'  => array(
				'component' => 'Text',
				'props'     => array(
					'content' => $row['source'],
					'size'    => 'text-sm',
				),
			),
			'to'      => array(
				'component'  => 'Box',
				'props'      => array(
					'customClasses' => array( 'gravitysmtp-activity-log-app__activity-log-table-recipient' ),
					'display'       => 'flex',
				),
				'components' => array(
					array(
						'component' => 'Text',
						'props'     => array(
							'content'       => $to_address,
							'customClasses' => array( 'gravitysmtp-activity-log-app__activity-log-table-recipient-email' ),
							'size'          => 'text-sm',
						),
					),
					array(
						'component'  => 'Box',
						'props'      => array(
							'customClasses' => array( 'gravitysmtp-activity-log-app__activity-log-table-recipient-meta' ),
							'display'       => 'flex',
						),
						'components' => array(
							array(
								'component' => 'Gravatar',
								'props'     => array(
									'circular'      => true,
									'customClasses' => array( 'gravitysmtp-activity-log-app__activity-log-table-recipient-gravatar' ),
									'defaultImage'  => 'mp',
									'emailHash'     => hash( 'sha256', $to_address ),
									'height'        => 24,
									'width'         => 24,
								),
							),
							array(
								'component' => $more_count > 0 ? 'Text' : null,
								'props'     => array(
									'content'       => '+' . (string) $more_count,
									'customClasses' => array( 'gravitysmtp-activity-log-app__activity-log-table-recipient-more' ),
									'size'          => 'text-xxs',
								),
							),
						),
					),
				),
			),
			'source'  => array(
				'component' => 'Text',
				'props'     => array(
					'content' => $row['source'],
					'size'    => 'text-sm',
				),
			),
			'date'    => array(
				'component' => 'Text',
				'props'     => array(
					'content' => convert_dates_to_timezone( $row['date_updated'] ),
					'size'    => 'text-sm',
				),
			),
			'actions' => $grid_actions,
		);
	}

	return $rows;
}

function convert_dates_to_timezone( $date ) {
	$gmt_time   = new \DateTimeZone( 'UTC' );
	$local_time = new \DateTimeZone( wp_timezone_string() );
	$datetime   = new \DateTime( $date, $gmt_time );
	$datetime->setTimezone( $local_time );

	return $datetime->format( 'F d, Y \a\t h:ia' );
}

function get_grid_actions( $event_id ) {
	$actions = array(
		'component'  => 'Box',
		'components' => array(
			array(
				'component' => 'Button',
				'props'     => array(
					'action'        => 'view',
					'customAttributes' => array(
						'title' => esc_html__( 'View email log', 'gravitysmtp' ),
					),
					'customClasses' => array( 'gravitysmtp-data-grid__action' ),
					'icon'          => 'eye',
					'iconPrefix'    => 'gravitysmtp-admin-icon',
					'spacing'       => [ 0, 2, 0, 0 ],
					'size'          => 'size-height-s',
					'type'          => 'icon-white',
					'data'          => array(
						'event_id' => $event_id,
					),
					'disabled' => ! current_user_can( \Gravity_Forms\Gravity_SMTP\Users\Roles::VIEW_EMAIL_LOG_DETAILS ),
				),
			),
			array(
				'component' => 'Button',
				'props'     => array(
					'action'        => 'preview',
					'customAttributes' => array(
						'title' => esc_html__( 'View email', 'gravitysmtp' ),
					),
					'customClasses' => array( 'gravitysmtp-data-grid__action' ),
					'icon'          => 'mail',
					'iconPrefix'    => 'gravitysmtp-admin-icon',
					'spacing'       => [ 0, 2, 0, 0 ],
					'size'          => 'size-height-s',
					'type'          => 'icon-white',
					'data'          => array(
						'event_id' => $event_id,
					),
					'disabled' => ! current_user_can( \Gravity_Forms\Gravity_SMTP\Users\Roles::VIEW_EMAIL_LOG_PREVIEW ),
				),
			),
			array(
				'component' => 'Button',
				'props'     => array(
					'action'        => 'delete',
					'customAttributes' => array(
						'title' => esc_html__( 'Delete email log', 'gravitysmtp' ),
					),
					'customClasses' => array( 'gravitysmtp-data-grid__action' ),
					'icon'          => 'trash',
					'iconPrefix'    => 'gravitysmtp-admin-icon',
					'size'          => 'size-height-s',
					'type'          => 'icon-white',
					'data'          => array(
						'event_id' => $event_id,
					),
					'disabled' => ! current_user_can( \Gravity_Forms\Gravity_SMTP\Users\Roles::DELETE_EMAIL_LOG ),
				),
			),
		),
	);

	return apply_filters( 'gravitysmtp_email_log_actions', $actions );
}

run();