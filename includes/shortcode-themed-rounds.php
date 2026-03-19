<?php
/**
 * Shortcode: [cerrito_themed_rounds]
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function cerrito_themed_rounds_shortcode( array $atts ) {
    $atts = shortcode_atts( [
        'days_ahead' => '60',
        'game_type'  => '',
    ], $atts );

    cerrito_enqueue_styles();
    ob_start();

    $today    = wp_date( 'Y-m-d' );
    $end_date = wp_date( 'Y-m-d', strtotime( '+' . (int) $atts['days_ahead'] . ' days' ) );

    $manual_theme_events = get_posts( [
        'post_type'      => 'event',
        'posts_per_page' => -1,
        'meta_key'       => 'event_date',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
        'meta_query'     => [
            'relation' => 'AND',
            [ 'key' => 'event_date',    'value' => [ $today, $end_date ], 'compare' => 'BETWEEN', 'type' => 'DATE' ],
            [ 'key' => 'special_theme', 'compare' => 'EXISTS' ],
            [ 'key' => 'special_theme', 'value' => '', 'compare' => '!=' ],
        ],
    ] );

    $all_range_events = get_posts( [
        'post_type'      => 'event',
        'posts_per_page' => -1,
        'meta_key'       => 'event_date',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
        'meta_query'     => [ [
            'key'     => 'event_date',
            'value'   => [ $today, $end_date ],
            'compare' => 'BETWEEN',
            'type'    => 'DATE',
        ] ],
    ] );

    $seen_ids      = array_column( $manual_theme_events, 'ID' );
    $themed_events = $manual_theme_events;

    foreach ( $all_range_events as $event ) {
        if ( in_array( $event->ID, $seen_ids, true ) ) continue;
        $event_date = cerrito_normalise_date( get_field( 'event_date', $event->ID ) );
        $types      = get_the_terms( $event->ID, 'game_type' );
        if ( $types && ! is_wp_error( $types ) && $event_date ) {
            if ( cerrito_get_event_theme( $types[0]->term_id, $event_date ) ) {
                $themed_events[] = $event;
            }
        }
    }

    if ( ! empty( $atts['game_type'] ) ) {
        $themed_events = cerrito_filter_by_game_type( $themed_events, $atts['game_type'] );
    }

    echo '<div class="cerrito-themed-rounds">';

    if ( ! empty( $themed_events ) ) {
        foreach ( $themed_events as $event ) {
            $event_date    = cerrito_normalise_date( get_field( 'event_date', $event->ID ) );
            $special_theme = get_field( 'special_theme', $event->ID );

            if ( empty( $special_theme ) && $event_date ) {
                $types = get_the_terms( $event->ID, 'game_type' );
                if ( $types && ! is_wp_error( $types ) ) {
                    $auto = cerrito_get_event_theme( $types[0]->term_id, $event_date );
                    if ( $auto ) $special_theme = $auto->name;
                }
            }

            if ( ! $special_theme || ! $event_date ) continue;

            $date_obj = DateTime::createFromFormat( 'Y-m-d', $event_date );
            if ( ! $date_obj ) continue;

            $day_name = strtoupper( substr( $date_obj->format( 'l' ), 0, 3 ) );
            $date_num = $date_obj->format( 'n/j' );

            $types       = get_the_terms( $event->ID, 'game_type' );
            $event_class = '';
            $game_emoji  = '';

            if ( $types && ! is_wp_error( $types ) ) {
                $event_class = cerrito_get_event_class( $types[0]->name );
                $game_emoji  = (string) get_field( 'game_emoji', 'game_type_' . $types[0]->term_id );
            }
            ?>
            <div class="cerrito-themed-round">
                <div class="cerrito-themed-date <?php echo esc_attr( $event_class ); ?>">
                    <div class="cerrito-themed-day"><?php echo esc_html( $day_name ); ?></div>
                    <div class="cerrito-themed-num"><?php echo esc_html( $date_num ); ?></div>
                </div>
                <div class="cerrito-themed-info">
                    <?php if ( $game_emoji ) : ?>
                        <div class="cerrito-themed-emoji"><?php echo esc_html( $game_emoji ); ?></div>
                    <?php endif; ?>
                    <div class="cerrito-themed-name">
                        <a href="<?php echo esc_url( get_permalink( $event->ID ) ); ?>">
                            <?php echo esc_html( $special_theme ); ?>
                        </a>
                    </div>
                </div>
            </div>
            <?php
        }
    } else {
        echo '<div class="cerrito-empty"><p>No themed rounds scheduled at this time.</p></div>';
    }

    echo '</div>'; // .cerrito-themed-rounds
    return ob_get_clean();
}
add_shortcode( 'cerrito_themed_rounds', 'cerrito_themed_rounds_shortcode' );
