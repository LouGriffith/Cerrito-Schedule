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
 *
 * @param mixed $field_value
 * @return WP_Post|null
 */
function cerrito_resolve_location( $field_value ) {
    if ( is_array( $field_value ) && ! empty( $field_value ) ) {
        $field_value = $field_value[0];
    }
    return ( $field_value instanceof WP_Post ) ? $field_value : null;
}

/**
 * Resolve a location slug or numeric ID string to a post ID integer.
 *
 * @param string|int $location
 * @return int
 */
function cerrito_resolve_location_id( $location ) {
    if ( is_numeric( $location ) ) return (int) $location;
    $post = get_page_by_path( $location, OBJECT, 'location' );
    return $post ? $post->ID : 0;
}

/**
 * Get a location logo URL, trying multiple possible ACF field names.
 *
 * @param int $location_id
 * @return string
 */
function cerrito_get_location_logo( $location_id ) {
    foreach ( [ 'location_logo', 'logo', 'sponsor_logo' ] as $field_name ) {
        $field = get_field( $field_name, $location_id );
        if ( $field ) return cerrito_resolve_image_url( $field );
    }
    return '';
}

/**
 * Get a location address string, trying multiple possible ACF field names.
 *
 * @param int $location_id
 * @return string
 */
function cerrito_get_location_address( $location_id ) {
    foreach ( [ 'location_address', 'address' ] as $field_name ) {
        $value = get_field( $field_name, $location_id );
        if ( $value ) return (string) $value;
    }
    return '';
}

// ── Game type helpers ─────────────────────────────────────────────────────────

/**
 * Look up a game_type term by name or slug.
 *
 * @param string $type_name
 * @return WP_Term|null
 */
function cerrito_get_game_type_term( $type_name ) {
    if ( ! $type_name ) return null;
    $term = get_term_by( 'name', $type_name, 'game_type' );
    if ( ! $term ) {
        $term = get_term_by( 'slug', sanitize_title( $type_name ), 'game_type' );
    }
    return ( $term && ! is_wp_error( $term ) ) ? $term : null;
}

/**
 * Get the emoji for a game type by name.
 *
 * @param string $type_name
 * @return string
 */
function cerrito_get_game_emoji( $type_name ) {
    $term = cerrito_get_game_type_term( $type_name );
    return $term ? (string) get_field( 'game_emoji', 'game_type_' . $term->term_id ) : '';
}

/**
 * Get the logo URL for a game type by name.
 *
 * @param string $type_name
 * @return string
 */
function cerrito_get_game_logo( $type_name ) {
    $term = cerrito_get_game_type_term( $type_name );
    return $term ? cerrito_resolve_image_url( get_field( 'game_logo', 'game_type_' . $term->term_id ) ) : '';
}

/**
 * Get the description for a game type by name (native WP taxonomy description).
 *
 * @param string $type_name
 * @return string
 */
function cerrito_get_game_description( $type_name ) {
    $term = cerrito_get_game_type_term( $type_name );
    return $term ? $term->description : '';
}

/**
 * Derive a CSS class ('trivia', 'bingo', or '') from a game type name string.
 *
 * @param string $event_type
 * @return string
 */
function cerrito_get_event_class( $event_type ) {
    $lower = strtolower( $event_type );
    if ( strpos( $lower, 'trivia' ) !== false ) return 'trivia';
    if ( strpos( $lower, 'bingo' )  !== false ) return 'bingo';
    return '';
}

/**
 * Return a comma-separated string of game type names for a post.
 *
 * @param int $post_id
 * @return string
 */
function cerrito_get_event_type_string( $post_id ) {
    $types = get_the_terms( $post_id, 'game_type' );
    if ( ! $types || is_wp_error( $types ) ) return '';
    return implode( ', ', wp_list_pluck( $types, 'name' ) );
}

// ── Theme helpers ─────────────────────────────────────────────────────────────

/**
 * Get the active theme for a game type on a specific date.
 *
 * @param int    $term_id
 * @param string $date     Y-m-d
 * @return WP_Term|false
 */
function cerrito_get_event_theme( $term_id, $date = null ) {
    if ( $date === null ) $date = wp_date( 'Y-m-d' );

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
 * @param int    $term_id
 * @param string $day_name
 * @param int    $days_ahead
 * @return string  e.g. "St. Patrick's Day (Mar 19)" or ''
 */
function cerrito_get_next_themed_date_for_day( $term_id, $day_name, $days_ahead = 60 ) {
    $start      = wp_date( 'Y-m-d' );
    $end        = wp_date( 'Y-m-d', strtotime( "+{$days_ahead} days" ) );
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
 *
 * @param mixed $field
 * @return string
 */
function cerrito_resolve_image_url( $field ) {
    if ( is_array( $field ) )                    return $field['url'] ?? '';
    if ( is_numeric( $field ) )                  return (string) ( wp_get_attachment_url( (int) $field ) ?: '' );
    if ( is_string( $field ) && $field !== '' )  return $field;
    return '';
}

// ── Date helpers ──────────────────────────────────────────────────────────────

/**
 * Normalise an ACF date field to Y-m-d format.
 * Handles: Ymd (20250219), m/d/Y (02/19/2025), and Y-m-d strings.
 *
 * @param string $date
 * @return string
 */
function cerrito_normalise_date( $date ) {
    if ( ! $date ) return '';

    // Ymd format
    if ( strlen( $date ) === 8 && ctype_digit( $date ) ) {
        return substr( $date, 0, 4 ) . '-' . substr( $date, 4, 2 ) . '-' . substr( $date, 6, 2 );
    }

    // m/d/Y format
    if ( strpos( $date, '/' ) !== false ) {
        $d = DateTime::createFromFormat( 'm/d/Y', $date );
        return $d ? $d->format( 'Y-m-d' ) : $date;
    }

    return $date;
}

// ── String helpers ────────────────────────────────────────────────────────────

/**
 * Replace newlines in an address string with spaces for single-line display.
 *
 * @param string $address
 * @return string
 */
function cerrito_flatten_address( $address ) {
    return str_replace( [ "\r\n", "\n", "\r" ], ' ', $address );
}

// ── Query helpers ─────────────────────────────────────────────────────────────

/**
 * Filter an array of event WP_Posts to only those matching a game type slug or name.
 *
 * @param WP_Post[] $events
 * @param string    $game_type
 * @return WP_Post[]
 */
function cerrito_filter_by_game_type( array $events, $game_type ) {
    return array_filter( $events, function( $event ) use ( $game_type ) {
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
 *
 * @param WP_Post[] $events
 * @param int       $location_id
 * @return WP_Post[]
 */
function cerrito_filter_by_location( array $events, $location_id ) {
    return array_filter( $events, function( $event ) use ( $location_id ) {
        $loc = cerrito_resolve_location( get_field( 'event_location', $event->ID ) );
        return $loc && $loc->ID === $location_id;
    } );
}

// ── Sort helpers ──────────────────────────────────────────────────────────────

/**
 * Convert a free-text event_time string to minutes-since-midnight for sorting.
 * Handles formats like "7:00 PM", "7pm", "19:00", "7:30", "7".
 * Returns 9999 for blank/unparseable values so they sort last.
 *
 * @param string $time_str
 * @return int
 */
function cerrito_time_to_minutes( $time_str ) {
    $time_str = trim( (string) $time_str );
    if ( $time_str === '' ) return 9999;

    // strtotime handles most common human formats
    $ts = strtotime( $time_str );
    if ( $ts === false ) return 9999;

    return (int) date( 'H', $ts ) * 60 + (int) date( 'i', $ts );
}

/**
 * Sort an array of WP_Post event objects by their event_time ACF field,
 * earliest first. Events with no time sort to the end.
 * Returns a new sorted array; does not modify in place.
 *
 * @param WP_Post[] $events
 * @return WP_Post[]
 */
function cerrito_sort_events_by_time( array $events ) {
    usort( $events, function( $a, $b ) {
        $ta = cerrito_time_to_minutes( get_field( 'event_time', $a->ID ) );
        $tb = cerrito_time_to_minutes( get_field( 'event_time', $b->ID ) );
        return $ta - $tb;
    } );
    return $events;
}

// ── Skip-date helpers ─────────────────────────────────────────────────────────

/**
 * Check whether a recurring event has been marked as skipped on a specific date.
 *
 * Uses the ACF repeater field `skip_dates` (sub-field `skip_date`, Date Picker).
 * Returns true if $date appears in the repeater, false otherwise.
 *
 * ACF setup required:
 *   Field group : Events
 *   Field label : Skip Dates
 *   Field name  : skip_dates          (Repeater)
 *   Sub-field   : skip_date           (Date Picker — return format Y-m-d)
 *
 * @param int    $post_id  Event post ID
 * @param string $date     Y-m-d date to check
 * @return bool
 */
function cerrito_is_skipped_on( $post_id, $date ) {
    $rows = get_field( 'skip_dates', $post_id );
    if ( empty( $rows ) || ! is_array( $rows ) ) return false;
    foreach ( $rows as $row ) {
        $skip = cerrito_normalise_date( (string) ( $row['skip_date'] ?? '' ) );
        if ( $skip === $date ) return true;
    }
    return false;
}

/**
 * Given a day name (e.g. "Tuesday"), return the Y-m-d of the next calendar
 * occurrence of that weekday on or after today (WordPress timezone).
 *
 * Used by recurring-schedule shortcodes to know which concrete date an event
 * "would" fall on so skip_dates can be checked.
 *
 * @param string $day_name  Full English weekday name, e.g. "Monday"
 * @return string           Y-m-d
 */
function cerrito_next_date_for_day( $day_name ) {
    $today    = (int) wp_date( 'N' ); // 1 (Mon) … 7 (Sun)
    $day_map  = [ 'Monday'=>1,'Tuesday'=>2,'Wednesday'=>3,'Thursday'=>4,'Friday'=>5,'Saturday'=>6,'Sunday'=>7 ];
    $target   = $day_map[ $day_name ] ?? 1;
    $diff     = ( $target - $today + 7 ) % 7;
    return wp_date( 'Y-m-d', strtotime( "+{$diff} days" ) );
}

// ── Render helpers ────────────────────────────────────────────────────────────

/**
 * Render a single event group card (used by master schedule and recurring schedule).
 *
 * @param array  $group              Keys: type, theme, class, events, show_date (optional)
 * @param bool   $is_recurring       Whether this group represents a recurring event
 * @param string $show_game_logo     'yes'|'no'
 * @param string $show_game_description 'yes'|'no'
 * @param string $display            'full'|'compact'
 * @return string
 */
function cerrito_render_event_group( array $group, $is_recurring, $show_game_logo = 'no', $show_game_description = 'no', $display = 'full' ) {
    $group['events'] = cerrito_sort_events_by_time( $group['events'] );

    if ( $display === 'compact' ) {
        return cerrito_render_event_group_compact( $group, $is_recurring );
    }

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
    $themed_attr = ! empty( $group['theme'] ) ? ' data-themed="1"' : '';
    ?>

    <div class="cerrito-event-group"<?php echo $themed_attr; ?>>

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
 * Render a compact (no-card) event group — just type label + inline venue rows.
 *
 * @param array $group
 * @param bool  $is_recurring
 * @return string
 */
function cerrito_render_event_group_compact( array $group, $is_recurring ) {
    $group['events'] = cerrito_sort_events_by_time( $group['events'] );
    ob_start();

    $game_emoji  = cerrito_get_game_emoji( $group['type'] );
    $show_date   = ( ! $is_recurring && isset( $group['show_date'] ) ) ? $group['show_date'] : '';
    $themed_attr = ! empty( $group['theme'] ) ? ' data-themed="1"' : '';
    ?>
    <div class="cerrito-event-group cerrito-event-group--compact"<?php echo $themed_attr; ?>>

        <div class="cerrito-compact-type <?php echo esc_attr( $group['class'] ); ?>">
            <?php if ( $game_emoji ) : ?>
                <span class="cerrito-game-emoji"><?php echo esc_html( $game_emoji ); ?></span>
            <?php endif; ?>
            <?php echo esc_html( $group['type'] ); ?>
            <?php if ( $group['theme'] ) : ?>
                <span class="cerrito-theme-badge"><?php echo esc_html( $group['theme'] ); ?></span>
            <?php endif; ?>
            <?php if ( $show_date ) : ?>
                <span class="cerrito-date-badge">(<?php echo esc_html( $show_date ); ?>)</span>
            <?php endif; ?>
        </div>

        <?php foreach ( $group['events'] as $event ) :
            $event_time = get_field( 'event_time', $event->ID );
            $location   = cerrito_resolve_location( get_field( 'event_location', $event->ID ) );
            if ( ! $location ) continue;
        ?>
            <div class="cerrito-compact-row">
                <span class="cerrito-compact-time">
                    <?php echo $event_time ? esc_html( $event_time ) : ''; ?>
                </span>
                <span class="cerrito-compact-arrow">→</span>
                <span class="cerrito-compact-venue">
                    <a href="<?php echo esc_url( get_permalink( $location->ID ) ); ?>">
                        <?php echo esc_html( $location->post_title ); ?>
                    </a>
                </span>
            </div>
        <?php endforeach; ?>

    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render the occurrence title line (emoji + type + theme badge + date badge).
 *
 * @param string $emoji
 * @param string $type
 * @param string $theme
 * @param string $date
 */
function cerrito_render_occurrence_title( $emoji, $type, $theme, $date = '' ) {
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
 *
 * @param WP_Post $event
 * @param string  $event_class
 */
function cerrito_render_location_card( $event, $event_class ) {
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
 * Enqueue the schedule stylesheet.
 */
function cerrito_enqueue_styles() {
    if ( ! wp_style_is( 'cerrito-schedule', 'enqueued' ) ) {
        wp_enqueue_style( 'cerrito-schedule' );
    }
}
