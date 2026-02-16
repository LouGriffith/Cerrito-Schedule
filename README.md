# Cerrito Schedule System

A comprehensive WordPress plugin system for managing and displaying trivia and bingo events across multiple locations.

## Features

- **Event Management** - Create recurring and one-time events with themes
- **Location Management** - Manage venues with logos, addresses, and details
- **Game Type Categories** - Organize events by type (Trivia, Bingo, etc.)
- **Multiple Display Shortcodes** - Flexible ways to show your schedule
- **Admin Enhancements** - Custom columns, filters, and sorting
- **Automatic Location Detection** - Shortcodes auto-filter on location pages

## Components

### 1. Main Schedule Plugin (`cerrito-schedule-acf.php`)
The core plugin providing all schedule display functionality.

**Shortcodes:**
- `[cerrito_today]` - Today's events
- `[cerrito_schedule]` - Upcoming events  
- `[cerrito_recurring_schedule]` - Weekly recurring events
- `[cerrito_master_schedule]` - Combined recurring + upcoming
- `[cerrito_themed_rounds]` - Themed events display

### 2. Admin Columns Plugin (`cerrito-events-admin.php`)
Enhances the Events admin list with custom columns and filters.

## Installation

1. Download the latest release
2. Upload plugin files to `/wp-content/plugins/`
3. Activate both plugins in WordPress admin
4. Requires ACF (Advanced Custom Fields) - free or Pro version

## Quick Start

### 1. Create Game Types
Go to **Events → Game Types** and create categories:
- Name: "Music Bingo"
- Emoji: 🎶
- Logo: Upload image
- Description: Brief description

### 2. Create Locations
Go to **Locations → Add New**:
- Location Name
- Location Logo
- Address
- Specials (optional)

### 3. Create Events
Go to **Events → Add New**:
- Event Title
- Event Date & Time
- Select Location
- Select Game Type
- For recurring: Check "Is Recurring" and select day(s)

### 4. Display on Pages
Add shortcodes to any page:

**Homepage:**
```
[cerrito_today style="compact"]
```

**Full Schedule Page:**
```
[cerrito_master_schedule show_game_logo="yes"]
```

**Location Page (auto-detects location):**
```
[cerrito_schedule]
```

## Shortcode Reference

### Today's Events
```
[cerrito_today style="compact"]
[cerrito_today style="full" show_game_logo="yes"]
```

**Parameters:**
- `style` - "compact" or "full"
- `show_game_logo` - "yes" or "no"
- `show_game_description` - "yes" or "no"

### Upcoming Schedule
```
[cerrito_schedule days_ahead="60"]
[cerrito_schedule location="ghost-river-brewing"]
[cerrito_schedule game_type="music-bingo"]
```

**Parameters:**
- `days_ahead` - Number of days (default: 30)
- `location` - Location slug or ID
- `game_type` - Game type slug or name
- `show_coming_soon` - "yes" or "no"

### Recurring Schedule
```
[cerrito_recurring_schedule]
[cerrito_recurring_schedule location="hueys-cordova"]
```

**Parameters:**
- `location` - Filter by location
- `game_type` - Filter by game type

### Master Schedule
```
[cerrito_master_schedule show_game_logo="yes"]
[cerrito_master_schedule location="location-slug" days_ahead="90"]
```

**Parameters:**
- All parameters from upcoming + recurring schedules
- `show_game_logo` - "yes" or "no"
- `show_game_description` - "yes" or "no"

### Themed Rounds
```
[cerrito_themed_rounds]
[cerrito_themed_rounds days_ahead="90"]
[cerrito_themed_rounds game_type="music-bingo"]
```

Shows upcoming events with special themes in card layout.

## Auto Location Detection

When using shortcodes on single location pages, the location parameter is automatically detected:

```
[cerrito_schedule]
```
On `/location/ghost-river-brewing/` automatically shows only Ghost River events.

## Admin Features

### Enhanced Event List
- **Columns:** Date, Time, Location, Recurring Status, Game Type
- **Filters:** Filter by location, recurring/one-time
- **Sorting:** Click any column to sort

### Location Management
- Upload venue logos
- Store addresses and special offers
- Link to venue websites

### Game Type Management  
- Add emojis for visual appeal
- Upload game type logos
- Write descriptions

## Requirements

- WordPress 5.0+
- PHP 7.4+
- Advanced Custom Fields (Free or Pro)

## ACF Field Structure

### Events
- Event Date (Date Picker)
- Event Time (Text)
- Event Location (Relationship → Location)
- Special Theme (Text)
- Theme Image (Image)
- Age Restriction (Text)
- Special Notes (WYSIWYG)
- Is Recurring (True/False)
- Recurrence Pattern (Text)

### Locations  
- Location Logo (Image - return URL)
- What Time Is It Played? (Text)
- Address (Text Area)
- Specials (WYSIWYG)
- Website (URL)

### Game Types (Taxonomy)
- Game Emoji (Text)
- Game Logo (Image)
- Description (Native taxonomy description)

### When (Taxonomy)
Standard taxonomy for days of week (Monday, Tuesday, etc.)

## Development

### Version History
See [CHANGELOG.md](CHANGELOG.md) for detailed version history.

### Current Version
**4.5** - Added themed rounds shortcode

## Support

For issues or questions, please open an issue on GitHub.

## License

Proprietary - Cerrito Entertainment

## Credits

Developed for Cerrito Entertainment  
Built with Advanced Custom Fields

## Screenshots

### Today's Events (Compact)
Perfect for homepage widgets showing current day's schedule.

### Master Schedule  
Combined view of all recurring and upcoming events organized by day.

### Themed Rounds
Special themed events displayed in card format with dates and emojis.

### Admin Event List
Enhanced admin interface with custom columns and filters.

## Tips & Best Practices

1. **Create Game Types First** - Set up categories before adding events
2. **Use Emojis** - Add visual interest to schedules (🎶, 🤔, 🎲)
3. **Recurring Events** - Check "Is Recurring" and select day(s) in When taxonomy
4. **One-Time Events** - Set specific date, don't check recurring
5. **Location Slugs** - Find in URL when hovering over location name
6. **Test Shortcodes** - Try in draft pages before publishing

## Common Use Cases

**Show all Music Bingo events:**
```
[cerrito_schedule game_type="music-bingo"]
```

**Show everything at Ghost River:**
```
[cerrito_master_schedule location="ghost-river-brewing-on-main"]
```

**Homepage today's schedule:**
```
[cerrito_today style="compact"]
```

**Themed events graphic:**
```
[cerrito_themed_rounds]
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md)
