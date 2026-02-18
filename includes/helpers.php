<?php
/**
 * Shared helper functions used across all Cerrito Schedule shortcodes.
 *
 * Functions are prefixed cerrito_ to avoid collisions with other plugins.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Location helpers ──────────────────────────────────────────────────────────

/**
 * Resolve an ACF relationship field (array or single object) to a WP_Post.
 */
function cerrito_resolve_location( mixed $field_value ): ?WP_Post {
    if ( is_array( $field_value ) && ! empty( $field_value ) ) {
        $field_value = $field_value[0];
    }
    return ( $field_value instanceof WP_Post ) ? $field_value : null;
}

/**
 * Resolve a location slug or numeric ID string to a post ID integer.
 */
function cerrito_resolve_location_id( string|int $location ): int {
    if ( is_numeric( $location ) ) return (int) $location;
    $post = get_page_by_path( $location, OBJECT, 'location' );
    return $post ? $post->ID : 0;
}

/**
 * Get a location logo URL, trying multiple possible ACF field names.
 */
function cerrito_get_location_logo( int $location_id ): string {
    foreach ( [ 'location_logo', 'logo', 'sponsor_logo' ] as $field_name ) {
        $field = get_field( $field_name, $location_id );
        if ( $field ) return cerrito_resolve_image_url( $field );
    }
    return '';
}

/**
 * Get a location address string, trying multiple possible ACF field names.
 */
function cerrito_get_location_address( int $location_id ): string {
    foreach ( [ 'location_address', 'address' ] as $field_name ) {
        $value = get_field( $field_name, $location_id );
        if ( $value ) return (string) $value;
    }
    return '';
}

// ── Game type helpers ─────────────────────────────────────────────────────────

/**
 * Look up a game_type term by name or slug.
 */
function cerrito_get_game_type_term( string $type_name ): ?WP_Term {
    if ( ! $type_name ) return null;
    $term = get_term_by( 'name', $type_name, 'game_type' );
    if ( ! $term ) {
        $term = get_term_by( 'slug', sanitize_title( $type_name ), 'game_type' );
    }
    return ( $term && ! is_wp_error( $term ) ) ? $term : null;
}

/**
 * Get the emoji for a game type by name.
 */
function cerrito_get_game_emoji( string $type_name ): string {
    $term = cerrito_get_game_type_term( $type_name );
    return $term ? (string) get_field( 'game_emoji', 'game_type_' . $term->term_id ) : '';
}

/**
 * Get the logo URL for a game type by name.
 */
function cerrito_get_game_logo( string $type_name ): string {
    $term = cerrito_get_game_type_term( $type_name );
    return $term ? cerrito_resolve_image_url( get_field( 'game_logo', 'game_type_' . $term->term_id ) ) : '';
}

/**
 * Get the description for a game type by name (native WP taxonomy description).
 */
function cerrito_get_game_description( string $type_name ): string {
    $term = cerrito_get_game_type_term( $type_name );
    return $term ? $term->description : '';
}

/**
 * Derive a CSS class ('trivia', 'bingo', or '') from a game type name string.
 */
function cerrito_get_event_class( string $event_type ): string {
    $lower = strtolower( $event_type );
    if ( str_contains( $lower, 'trivia' ) ) return 'trivia';
    if ( str_contains( $lower, 'bingo' )  ) return 'bingo';
    return '';
}

/**
 * Return a comma-separated string of game type names for a post.
 */
function cerrito_get_event_type_string( int $post_id ): string {
    $types = get_the_terms( $post_id, 'game_type' );
    if ( ! $types || is_wp_error( $types ) ) return '';
    return implode( ', ', wp_list_pluck( $types, 'name' ) );
}

// ── Theme helpers ─────────────────────────────────────────────────────────────

/**
 * Get the active theme for a game type on a specific date.
 * Checks the 'themed_dates' term meta array on the game type term.
 *
 * @param int    $term_id  game_type term ID
 * @param string $date     Y-m-d date string (defaults to today)
 * @return WP_Term|false   Theme term with ->emoji and ->image attached, or false
 */
function cerrito_get_event_theme( int $term_id, string $date = null ): WP_Term|false {
    if ( $date === null ) $date = date( 'Y-m-d' );

    $themed_dates = get_term_meta( $term_id, 'themed_dates', true );
    if ( empty( $themed_dates ) || ! is_array( $themed_dates ) ) return false;

    foreach ( $themed_dates as $entry ) {
        if ( ! isset( $entry['date'], $entry['theme_id'] ) ) continue;
        if ( $entry['date'] !== $date ) continue;

        $theme = get_term( $entry['theme_id'], 'game-theme' );
        if ( $theme && ! is_wp_error( $theme ) ) {
            $theme->emoji = get_field( 'theme_emoji', 'game-theme_' . $theme->term_id );
            $theme->image = cerrito_resolve_image_url( get_field( 'theme_image', 'game-theme_' . $theme->term_id ) );
            return $theme;
        }
    }

    return false;
}

/**
 * Find the next upcoming themed date for a game type that falls on a specific
 * day of the week, within $days_ahead days from today.
 *
 * Returns a formatted string like "St. Patrick's Day (Mar 19)" or ''.
 */
function cerrito_get_next_themed_date_for_day( int $term_id, string $day_name, int $days_ahead = 60 ): string {
    $start      = date( 'Y-m-d' );
    $end        = date( 'Y-m-d', strtotime( "+{$days_ahead} days" ) );
    $dated_meta = get_term_meta( $term_id, 'themed_dates', true );

    if ( empty( $dated_meta ) || ! is_array( $dated_meta ) ) return '';

    foreach ( $dated_meta as $entry ) {
        if ( ! isset( $entry['date'], $entry['theme_id'] ) ) continue;
        if ( $entry['date'] < $start || $entry['date'] > $end ) continue;

        $d = DateTime::createFromFormat( 'Y-m-d', $entry['date'] );
        if ( $d && $d->format( 'l' ) === $day_name ) {
            $theme = get_term( $entry['theme_id'], 'game-theme' );
            if ( $theme && ! is_wp_error( $theme ) ) {
                return $theme->name . ' (' . $d->format( 'M j' ) . ')';
            }
        }
    }

    return '';
}

// ── Image helpers ─────────────────────────────────────────────────────────────

/**
 * Resolve an ACF image field (array, attachment ID, or URL string) to a URL.
 */
function cerrito_resolve_image_url( mixed $field ): string {
    if ( is_array( $field ) )                    return $field['url'] ?? '';
    if ( is_numeric( $field ) )                  return (string) ( wp_get_attachment_url( (int) $field ) ?: '' );
    if ( is_string( $field ) && $field !== '' )  return $field;
    return '';
}

// ── Date helpers ──────────────────────────────────────────────────────────────

/**
 * Normalise an ACF date field to Y-m-d format.
 * Handles: Ymd (20250219), m/d/Y (02/19/2025), and Y-m-d strings.
 */
function cerrito_normalise_date( string $date ): string {
    if ( ! $date ) return '';

    // Ymd format
    if ( strlen( $date ) === 8 && ctype_digit( $date ) ) {
        return substr( $date, 0, 4 ) . '-' . substr( $date, 4, 2 ) . '-' . substr( $date, 6, 2 );
    }

    // m/d/Y format
    if ( str_contains( $date, '/' ) ) {
        $d = DateTime::createFromFormat( 'm/d/Y', $date );
        return $d ? $d->format( 'Y-m-d' ) : $date;
    }

    return $date;
}

// ── String helpers ────────────────────────────────────────────────────────────

/**
 * Replace newlines in an address string with spaces for single-line display.
 */
function cerrito_flatten_address( string $address ): string {
    return str_replace( [ "\r\n", "\n", "\r" ], ' ', $address );
}

// ── Query helpers ─────────────────────────────────────────────────────────────

/**
 * Filter an array of event WP_Posts to only those matching a game type slug or name.
 */
function cerrito_filter_by_game_type( array $events, string $game_type ): array {
    return array_filter( $events, function( WP_Post $event ) use ( $game_type ) {
        $types = get_the_terms( $event->ID, 'game_type' );
        if ( ! $types || is_wp_error( $types ) ) return false;
        foreach ( $types as $type ) {
            if ( $type->slug === $game_type || strtolower( $type->name ) === strtolower( $game_type ) ) {
                return true;
            }
        }
        return false;
    } );
}

/**
 * Filter an array of event WP_Posts to only those belonging to a given location ID.
 */
function cerrito_filter_by_location( array $events, int $location_id ): array {
    return array_filter( $events, function( WP_Post $event ) use ( $location_id ) {
        $loc = cerrito_resolve_location( get_field( 'event_location', $event->ID ) );
        return $loc && $loc->ID === $location_id;
    } );
}

// ── Render helpers ────────────────────────────────────────────────────────────

/**
 * Render a single event group card (used by master schedule and recurring schedule).
 *
 * @param array  $group              Keys: type, theme, class, events, show_date (optional)
 * @param bool   $is_recurring       Whether this group represents a recurring event
 * @param string $show_game_logo     'yes'|'no'
 * @param string $show_game_description 'yes'|'no'
 */
function cerrito_render_event_group( array $group, bool $is_recurring, string $show_game_logo = 'no', string $show_game_description = 'no' ): string {
    ob_start();

    $game_emoji       = cerrito_get_game_emoji( $group['type'] );
    $game_logo        = ( $show_game_logo        === 'yes' ) ? cerrito_get_game_logo( $group['type'] )        : '';
    $game_description = ( $show_game_description === 'yes' ) ? cerrito_get_game_description( $group['type'] ) : '';

    // Theme display fields
    $theme_emoji = '';
    if ( $group['theme'] ) {
        $theme_name = preg_replace( '/\s*\([^)]*\)$/', '', $group['theme'] ); // strip "(Mar 19)"
        $theme_term = get_term_by( 'name', $theme_name, 'game-theme' )
                   ?: get_term_by( 'slug', sanitize_title( $theme_name ), 'game-theme' );
        if ( $theme_term && ! is_wp_error( $theme_term ) ) {
            $theme_emoji = (string) get_field( 'theme_emoji', 'game-theme_' . $theme_term->term_id );
        }
    }

    $show_date = ( ! $is_recurring && isset( $group['show_date'] ) ) ? $group['show_date'] : '';
    ?>

    <div class="cerrito-event-group">

        <?php if ( $game_logo || $game_description ) : ?>
            <div class="cerrito-game-header">
                <?php if ( $game_logo ) : ?>
                    <div class="cerrito-game-logo">
                        <img src="<?php echo esc_url( $game_logo ); ?>" alt="<?php echo esc_attr( $group['type'] ); ?>">
                    </div>
                <?php endif; ?>
                <div class="cerrito-game-info">
                    <?php cerrito_render_occurrence_title( $game_emoji, $group['type'], $group['theme'], $show_date ); ?>
                    <?php if ( $game_description ) : ?>
                        <p class="cerrito-game-description"><?php echo esc_html( $game_description ); ?></p>
                    <?php endif; ?>
                    <?php if ( $group['theme'] && $theme_emoji ) : ?>
                        <div class="cerrito-theme-display">
                            <span class="cerrito-theme-emoji"><?php echo esc_html( $theme_emoji ); ?></span>
                            <span class="cerrito-theme-name"><?php echo esc_html( $group['theme'] ); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else : ?>
            <?php cerrito_render_occurrence_title( $game_emoji, $group['type'], $group['theme'], $show_date ); ?>
            <?php if ( $group['theme'] && $theme_emoji ) : ?>
                <div class="cerrito-theme-display">
                    <span class="cerrito-theme-emoji"><?php echo esc_html( $theme_emoji ); ?></span>
                    <span class="cerrito-theme-name"><?php echo esc_html( $group['theme'] ); ?></span>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php foreach ( $group['events'] as $event ) :
            cerrito_render_location_card( $event, $group['class'] );
        endforeach; ?>

    </div>

    <?php
    return ob_get_clean();
}

/**
 * Render the occurrence title line (emoji + type + theme badge + date badge).
 */
function cerrito_render_occurrence_title( string $emoji, string $type, string $theme, string $date = '' ): void {
    ?>
    <div class="cerrito-occurrence-title">
        <?php if ( $emoji ) : ?>
            <span class="cerrito-game-emoji"><?php echo esc_html( $emoji ); ?></span>
        <?php endif; ?>
        <?php echo esc_html( $type ); ?>
        <?php if ( $theme ) : ?>
            <span class="cerrito-theme-badge">Theme Rounds</span>
        <?php endif; ?>
        <?php if ( $date ) : ?>
            <span class="cerrito-date-badge"><?php echo esc_html( $date ); ?></span>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Render a location card for a single event.
 */
function cerrito_render_location_card( WP_Post $event, string $event_class ): void {
    $event_time      = get_field( 'event_time',      $event->ID );
    $location        = cerrito_resolve_location( get_field( 'event_location', $event->ID ) );
    $age_restriction = get_field( 'age_restriction', $event->ID );
    $special_notes   = get_field( 'special_notes',   $event->ID );

    if ( ! $location ) return;

    $location_logo    = cerrito_get_location_logo( $location->ID );
    $location_address = cerrito_get_location_address( $location->ID );
    ?>
    <div class="cerrito-location-card <?php echo esc_attr( $event_class ); ?>">
        <div class="cerrito-location-logo">
            <?php if ( $location_logo ) : ?>
                <img src="<?php echo esc_url( $location_logo ); ?>" alt="<?php echo esc_attr( $location->post_title ); ?>">
            <?php endif; ?>
        </div>
        <div class="cerrito-location-details">
            <div class="cerrito-location-name">
                <a href="<?php echo esc_url( get_permalink( $location->ID ) ); ?>">
                    <?php echo esc_html( $location->post_title ); ?>
                </a>
            </div>
            <?php if ( $event_time ) : ?>
                <div class="cerrito-location-time">🕐 <?php echo esc_html( $event_time ); ?></div>
            <?php endif; ?>
            <?php if ( $location_address ) : ?>
                <div class="cerrito-location-address">📍 <?php echo esc_html( cerrito_flatten_address( $location_address ) ); ?></div>
            <?php endif; ?>
            <?php if ( $age_restriction ) : ?>
                <div class="cerrito-location-badges">
                    <span class="cerrito-badge"><?php echo esc_html( $age_restriction ); ?></span>
                </div>
            <?php endif; ?>
            <?php if ( $special_notes ) : ?>
                <div class="cerrito-location-notes"><?php echo wp_kses_post( $special_notes ); ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Enqueue the schedule stylesheet (called by each shortcode so the CSS loads
 * even if wp_enqueue_scripts has already fired, e.g. in page builders).
 */
function cerrito_enqueue_styles(): void {
    if ( ! wp_style_is( 'cerrito-schedule', 'enqueued' ) ) {
        wp_enqueue_style( 'cerrito-schedule' );
    }
}
