# Cerrito Schedule Display

WordPress plugin for managing and displaying trivia and bingo event schedules across multiple locations.

## ⚠️ Installation

**Do not use the "Download ZIP" button on GitHub.** The source ZIP extracts with the wrong folder name and WordPress will not install it correctly.

Instead:

1. Go to the [Releases page](../../releases)
2. Download the **`cerrito-schedule-X.X.X.zip`** asset attached to the latest release
3. In WordPress, go to **Plugins → Add New → Upload Plugin**
4. Upload that ZIP and activate

WordPress auto-updates work automatically — once installed from the release ZIP, future updates are delivered through the standard WordPress update system.

---

## Shortcodes

| Shortcode | Description |
|---|---|
| `[cerrito_today]` | Today's events (recurring + one-time) |
| `[cerrito_schedule]` | Upcoming one-time events grouped by date |
| `[cerrito_recurring_schedule]` | Recurring events grouped by day of week |
| `[cerrito_master_schedule]` | Combined recurring + upcoming by day of week |
| `[cerrito_themed_rounds]` | Upcoming events with special themes |
| `[cerrito_upcoming_themes_list]` | Themed dates from game type term meta |
| `[cerrito_locations]` | Location directory with nested events |

## Requirements

- WordPress 5.0+
- PHP 7.4+
- Advanced Custom Fields (Free or Pro)

## Changelog

See [CHANGELOG.md](CHANGELOG.md)

## Author

[Lou Griffith](https://lougriffith.com)

## License

Proprietary — Cerrito Entertainment
