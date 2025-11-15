# Changelogify

Tested up to: 6.8
Stable tag: 1.0.0
License: GPLv2 or later
License URI: [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html)

Automatically generate versioned changelogs from Simple History, WP Activity Log, or native WordPress events.

## Description

Changelogify is a comprehensive WordPress plugin that automatically tracks and generates changelog releases from various event sources. It creates beautifully formatted changelogs with customizable sections (Added, Changed, Fixed, Removed, Security) and provides multiple ways to display them on your site.

## Quick Start

1. Install & Activate
   - Upload `changelogify` to `/wp-content/plugins/` and activate it in WordPress → Plugins.

2. Configure Sources
   - Go to WordPress Admin → Changelog → Settings.
   - Choose event sources (Simple History, WP Activity Log, or Native WordPress events).

3. Generate Your First Release
   - Go to Changelog → Generate Release.
   - Enter a version (e.g., `1.0.0`) and a date range (e.g., since last release), then click Generate.
   - Review the generated content and Publish.

4. Display the Changelog
   - Gutenberg block: add the “Changelog” block to any page/post and configure options.
   - Shortcode:

     ```wordpress
     [changelog limit="5" show_version="true" show_date="true"]
     ```

   - Archive: visit `/changelog/` to see all releases.

5. Optional: Automation
   - In Changelog → Settings, enable automatic generation and pick a frequency (daily/weekly via WP‑Cron).

## Features

### Event Source Adapters
- **Simple History Integration** - Pull events from Simple History plugin
- **WP Activity Log Integration** - Pull events from WP Activity Log plugin
- **Native WordPress Events** - Fallback tracking for core WordPress events
  - Post publishing
  - Plugin activation/deactivation
  - Theme changes
  - WordPress updates

### Custom Post Type
- Dedicated `changelog_release` post type
- Version field for semantic versioning
- Date range tracking
- Organized into sections: Added, Changed, Fixed, Removed, Security

### Release Generator
- Manual generation via admin button
- Automatic generation via WP-Cron (configurable)
- Date range options:
  - Since last release
  - Custom date range
  - Last 7/30 days
- Smart version suggestion

### Public Display
- **Gutenberg Block** - Add changelog to any page/post
- **Shortcode** - `[changelog limit="5"]` with customizable attributes
- **Archive Template** - Beautiful archive page for all releases
- **Single Template** - Detailed view for each release

### Settings
- Configure which event sources to use
- Custom event-to-section mapping
- Default date range preferences
- WP-Cron automation settings

## Installation

1. Upload the `changelogify` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Changelog → Settings to configure event sources
4. Go to Changelog → Generate Release to create your first changelog

## Usage

### Generating a Release

1. Navigate to **Changelog → Generate Release**
2. Enter the version number (e.g., 1.0.0)
3. Choose date range (since last release or custom)
4. Click "Generate Release"
5. Review and publish the generated changelog

### Displaying Changelogs

#### Using the Gutenberg Block
1. Edit any page or post
2. Add the "Changelog" block
3. Configure display options in the sidebar
4. Publish

#### Using the Shortcode
```
[changelog limit="5" show_version="true" show_date="true"]
```

**Attributes:**
- `limit` - Number of releases to show (default: 5)
- `show_version` - Display version numbers (default: true)
- `show_date` - Display dates (default: true)
- `version` - Show specific version only

#### Archive Page
Visit `/changelog/` on your site to see all releases.

### Configuring Event Sources

1. Go to **Changelog → Settings**
2. Select which event sources to enable
3. Configure custom event mappings
4. Save settings

### Automatic Generation

Enable WP-Cron automation:
1. Go to **Changelog → Settings**
2. Check "Enable automatic changelog generation"
3. Choose frequency (daily or weekly)
4. Save settings

## Event Mapping

Map specific events to changelog sections:

**Example:**
- `plugin_activated` → Changed
- `plugin_uninstalled` → Removed
- `wordpress_updated` → Security
- `post_publish` → Added

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher

## Optional Dependencies

- Simple History plugin (for enhanced event tracking)
- WP Activity Log plugin (for enhanced event tracking)

## Filters & Hooks

### Filters

```php
// Modify event categorization
apply_filters('changelogify_event_category', $category, $event);

// Modify release content
apply_filters('changelogify_release_content', $content, $sections);
```

### Actions

```php
// Before release generation
do_action('changelogify_before_generate_release', $version, $date_from, $date_to);

// After release generation
do_action('changelogify_after_generate_release', $post_id, $version);
```

## Development

### File Structure
```
changelogify/
├── changelogify.php                # Main plugin file
├── includes/
│   ├── class-cpt-changelog-release.php
│   ├── class-event-sources.php
│   ├── class-release-generator.php
│   ├── class-settings.php
│   └── class-public-display.php
├── assets/
│   ├── css/
│   │   └── public.css
│   └── js/
│       └── block.js
├── templates/
│   ├── archive-changelog.php
│   └── single-changelog.php
└── README.md
```

## Changelog

### 1.0.0
- Initial release
- Simple History integration
- WP Activity Log integration
- Native WordPress event tracking
- Custom Post Type for releases
- Release generator with manual and cron options
- Gutenberg block
- Shortcode support
- Archive and single templates
- Settings page

## License

GPL v2 or later

## Author

Eric Swanson - [Portfolio](https://www.ericsdevportfolio.com)

## Support

For issues and feature requests, please visit [GitHub Issues](https://www.github.com/erics1337/changelogify/issues)
