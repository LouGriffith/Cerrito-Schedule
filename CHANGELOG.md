# Changelog

All notable changes to the Cerrito Schedule System will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [6.5] - 2026-03-10

### Added
- `display="compact"` parameter for `[cerrito_master_schedule]` and `[cerrito_recurring_schedule]`
  — replaces full venue cards with tight inline rows: `time → venue name`
- `show_day_filter="yes"` parameter for `[cerrito_master_schedule]`
  — renders pill buttons (All · Mon · Tue …) above the schedule; only days with events appear
- `default_day="Tuesday"` parameter — pre-selects a day on page load
- `show_themed_filter="yes"` parameter for `[cerrito_master_schedule]`
  — adds a 🎭 Themed toggle button; works independently or combined with the day filter
- Both filters are two-dimensional: selecting "Tuesday + Themed" shows only themed Tuesday events;
  days with no matching groups are automatically hidden

### Changed
- Events within each group are now sorted by start time (earliest first) across all shortcodes
- `date()` replaced with `wp_date()` throughout for correct WordPress timezone handling
- All PHP 8-only syntax removed for PHP 7.4 compatibility (union return types, `mixed` hints,
  typed properties replaced with docblock comments)
- Plugin URI updated to `https://github.com/lougriffith/cerrito-schedule`
- GitHub updater repo references lowercased to match actual repository name

### Fixed
- `data-themed` attribute now stamped on event group divs in both full and compact display modes
- Themed filter correctly hides entire day blocks when no themed events remain after filtering

---

## [6.4] - 2026-02-28

### Fixed
- Timezone bug: replaced all `date()` calls with `wp_date()` across 6 files so event dates
  respect the WordPress-configured timezone rather than the server's UTC setting

---

## [6.2] - 2026-02-20

### Added
- `[cerrito_upcoming_themes_list]` shortcode — formatted upcoming themed dates from
  game_type term meta, sorted by date with location/time details

### Changed
- CSS consolidated into single `schedule.css` loaded once via `wp_enqueue_style()`

---

## [4.5] - 2026-02-16

### Added
- `[cerrito_themed_rounds]` shortcode — card-based display of upcoming themed events
- Day abbreviation, M/D date format, game emoji, hover effects, colour coding

## [4.4] - 2026-02-16

### Added
- Automatic location detection on single location pages (`is_singular('location')`)

## [4.3] - 2026-02-16

### Added
- `style="compact"` parameter for `[cerrito_today]`

## [4.2] - 2026-02-16

### Added
- `[cerrito_today]` shortcode — all events (recurring + one-time) for the current day

## [4.1] - 2026-02-16

### Changed
- Game descriptions pulled from native taxonomy description field instead of custom ACF field

## [4.0] - 2026-02-16

### Added
- `[cerrito_master_schedule]` shortcode combining recurring and upcoming events by day of week
- `show_game_logo` and `show_game_description` parameters

## [3.0] - 2026-02-16

### Added
- `[cerrito_recurring_schedule]` shortcode — weekly recurring events with full location cards
- "Coming Soon" section for events without a day assignment

## [2.0] - 2026-02-16

### Added
- `game_type` and `location` filtering parameters across all shortcodes

## [1.0] - 2026-02-16

### Added
- Initial release: `[cerrito_schedule]` shortcode, ACF integration, admin columns plugin
