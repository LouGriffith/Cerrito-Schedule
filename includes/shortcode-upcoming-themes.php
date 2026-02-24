<?php
/**
 * Shortcode: [cerrito_upcoming_themes_list]
 *
 * Displays a formatted list of upcoming themed dates pulled from the
 * game_type 'themed_dates' term meta, grouped and sorted by date.
 *
 * Parameters:
 *   days_ahead  int     How far ahead to look (default 90)
 *   game_type   string  Filter by game type slug
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function cerrito_upcoming_themes_list_shortcode( array $atts ): string {
    $atts = shortcode_atts( [
        'days_ahead' => '90',
        'game_type'  => '',
    ], $atts );

    cerrito_enqueue_styles();
    ob_start();

    $today    = wp_date( 'Y-m-d' );
    $end_date = wp_date( 'Y-m-d', strtotime( '+' . (int) $atts['days_ahead'] . ' days' ) );

    // Fetch relevant game types
    $terms_args = [ 'taxonomy' => 'game_type', 'hide_empty' => false ];
    if ( ! empty( $atts['game_type'] ) ) {
        $terms_args['slug'] = $atts['game_type'];
    }
    $game_types = get_terms( $terms_args );

    // Build a flat list of themed date entries with their theme term attached
    $themed_list = [];

    foreach ( $game_types as $game_type ) {
        $themed_dates = get_term_meta( $game_type->term_id, 'themed_dates', true );
        if ( empty( $themed_dates ) || ! is_array( $themed_dates ) ) continue;

        foreach ( $themed_dates as $entry ) {
            if ( ! isset( $entry['date'], $entry['theme_id'] ) ) continue;
            if ( $entry['date'] < $today || $entry['date'] > $end_date ) continue;

            $theme = get_term( $entry['theme_id'], 'game-theme' );
            if ( ! $theme || is_wp_error( $theme ) ) continue;

            $key = $entry['date'] . '_' . $entry['theme_id'];
            if ( ! isset( $themed_list[ $key ] ) ) {
                $themed_list[ $key ] = [
                    'date'       => $entry['date'],
                    'theme'      => $theme,
                    'game_types' => [],
                ];
            }
            $themed_list[ $key ]['game_types'][] = $game_type;
        }
    }

    // Sort ascending by date
    usort( $themed_list, fn( $a, $b ) => strcmp( $a['date'], $b['date'] ) );

    echo '<div class="cerrito-themes-list">';

    if ( ! empty( $themed_list ) ) {
        foreach ( $themed_list as $item ) {
            $date_obj  = DateTime::createFromFormat( 'Y-m-d', $item['date'] );
            $day_label = $date_obj ? strtoupper( $date_obj->format( 'l' ) ) : '';
            $date_num  = $date_obj ? $date_obj->format( 'n/j' ) : $item['date'];

            $theme       = $item['theme'];
            $theme_emoji = (string) get_field( 'theme_emoji', 'game-theme_' . $theme->term_id );

            // Gather locations + times for all game types on this date
            $locations_times = cerrito_get_locations_for_themed_date( $item, $date_obj );
            ?>
            <div class="cerrito-themes-list-item">
                <div class="cerrito-themes-date-box">
                    <div class="cerrito-themes-day"><?php echo esc_html( $day_label ); ?></div>
                    <div class="cerrito-themes-date-num"><?php echo esc_html( $date_num ); ?></div>
                </div>

                <div class="cerrito-themes-info-box">
                    <div class="cerrito-themes-header">
                        <?php if ( $theme_emoji ) : ?>
                            <span class="cerrito-themes-emoji"><?php echo esc_html( $theme_emoji ); ?></span>
                        <?php endif; ?>
                        <div class="cerrito-themes-name"><?php echo esc_html( strtoupper( $theme->name ) ); ?></div>
                    </div>

                    <?php if ( ! empty( $locations_times ) ) : ?>
                        <div class="cerrito-themes-locations">
                            <?php foreach ( $locations_times as $loc ) : ?>
                                <div class="cerrito-themes-location">
                                    <?php if ( $loc['time'] ) : ?>
                                        <span class="cerrito-themes-time"><?php echo esc_html( $loc['time'] ); ?></span>
                                        <span class="cerrito-themes-arrow">→</span>
                                    <?php endif; ?>
                                    <span class="cerrito-themes-location-name">
                                        <a href="<?php echo esc_url( $loc['url'] ); ?>">
                                            <?php echo esc_html( $loc['name'] ); ?>
                                        </a>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        }
    } else {
        echo '<div class="cerrito-empty"><p>No themed rounds scheduled at this time.</p></div>';
    }

    echo '</div>'; // .cerrito-themes-list
    return ob_get_clean();
}
add_shortcode( 'cerrito_upcoming_themes_list', 'cerrito_upcoming_themes_list_shortcode' );

/**
 * For a themed-date list item, find all events/locations that occur on that date
 * for the associated game types. Returns array of [ name, url, time ] arrays.
 */
function cerrito_get_locations_for_themed_date( array $item, DateTime $date_obj ): array {
    $locations = [];
    $date_str  = $date_obj->format( 'Y-m-d' );
    $day_name  = $date_obj->format( 'l' );

    foreach ( $item['game_types'] as $game_type ) {
        $events = get_posts( [
            'post_type'      => 'event',
            'posts_per_page' => -1,
            'tax_query'      => [ [
                'taxonomy' => 'game_type',
                'field'    => 'term_id',
                'terms'    => $game_type->term_id,
            ] ],
        ] );

        foreach ( $events as $event ) {
            $is_recurring = (bool) get_field( 'is_recurring', $event->ID );
            $matches      = false;

            if ( $is_recurring ) {
                $when_terms = get_the_terms( $event->ID, 'when' );
                if ( $when_terms && ! is_wp_error( $when_terms ) ) {
                    foreach ( $when_terms as $wt ) {
                        if ( $wt->name === $day_name ) { $matches = true; break; }
                    }
                }
            } else {
                $matches = ( cerrito_normalise_date( get_field( 'event_date', $event->ID ) ) === $date_str );
            }

            if ( ! $matches ) continue;

            $location = cerrito_resolve_location( get_field( 'event_location', $event->ID ) );
            if ( ! $location ) continue;

            $loc_id = $location->ID;
            if ( ! isset( $locations[ $loc_id ] ) ) {
                $locations[ $loc_id ] = [
                    'name' => $location->post_title,
                    'url'  => get_permalink( $location->ID ),
                    'time' => get_field( 'event_time', $event->ID ),
                ];
            }
        }
    }

    return array_values( $locations );
}
