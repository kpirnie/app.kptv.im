# IPTV Provider Sync

PHP 8.4 application for synchronizing IPTV provider streams to a MySQL database. Supports both Xtreme Codes API and M3U playlist formats with advanced filtering capabilities.

## Features

- **Multi-Provider Support**: Sync from Xtreme Codes API or M3U playlists
- **Intelligent Filtering**: Regex-based include/exclude filters for streams
- **Stream Management**:
  - Full synchronization with update/insert logic
  - Missing stream detection
  - Metadata fixup across similar streams
- **Efficient Processing**: Batch operations for handling thousands of streams
- **Flexible Targeting**: Sync all providers, specific users, or individual providers
- **PHP 8.4**: Built with modern PHP features including enums, readonly properties, and named arguments

## Requirements

- PHP 8.4+
- MySQL/MariaDB database
- Composer

## Installation

1. Clone the repository:
```bash
git clone <repository-url>
cd kptv-sync
```

2. Install dependencies:
```bash
composer install
```

3. Configure database connection:

Create `.kptvconf` in the project root with your database credentials:
```json
{
  "dbserver": "localhost",
  "dbport": 3306,
  "dbuser": "your_username",
  "dbpassword": "your_password",
  "dbschema": "your_database",
  "db_tblprefix": "kptv_"
}
```

4. Import database schema:
```bash
mysql -u your_username -p your_database < db_schema.sql
```

## Usage

### Basic Commands

```bash
# Sync all providers
php kptv-sync.php sync

# Sync specific user's providers
php kptv-sync.php sync --user-id 1

# Sync specific provider
php kptv-sync.php sync --provider-id 32

# Check for missing streams
php kptv-sync.php testmissing

# Fix up stream metadata
php kptv-sync.php fixup

# Enable debug logging
php kptv-sync.php sync --debug

# Ignore specific fields during sync
php kptv-sync.php sync --ignore tvg_id,logo
```

### Actions

**sync**: Fetches streams from providers and updates database
- New streams inserted as inactive (s_active=0)
- Existing streams updated (preserves s_active and s_type_id)
- Stream URIs, logos, TVG IDs refreshed
- Supports `--ignore` flag to skip updating specific metadata fields

**testmissing**: Identifies streams in database that no longer exist at provider
- Records missing streams to `kptv_stream_missing` table
- Useful for cleanup operations

**fixup**: Consolidates metadata across duplicate/similar streams
- Updates s_name, s_channel, s_tvg_logo, s_tvg_id
- Uses most recently updated stream as source
- Groups by exact s_orig_name match

### Ignore Fields

Use the `--ignore` option to skip syncing specific metadata fields:
- `tvg_id`: Skip updating TVG ID
- `logo`: Skip updating logo URL
- `tvg_group`: Skip updating group/category

Example: `php kptv-sync.php sync --ignore tvg_id,logo`

## Provider Configuration

### Provider Types (sp_type)
- `0`: Xtreme Codes API
- `1`: M3U Playlist

### Stream Types (s_type_id)
- `0`: Live TV
- `4`: Video on Demand (VOD)
- `5`: Series
- `99`: Other

### Filter Types (sf_type_id)
- `0`: Always include (regex on name)
- `1`: Exclude (string match on name)
- `2`: Exclude (regex on name)
- `3`: Exclude (regex on stream URI)
- `4`: Exclude (regex on group)

## Database Tables

- `kptv_streams`: Main stream storage
- `kptv_stream_providers`: Provider configurations
- `kptv_stream_filters`: User filter rules
- `kptv_stream_temp`: Temporary sync staging
- `kptv_stream_missing`: Missing stream tracking
- `kptv_stream_other`: Additional stream storage

## Architecture

### Dependencies

- **kevinpirnie/kpt-database**: Database abstraction layer with query builder
- **guzzlehttp/guzzle**: HTTP client for API and M3U requests
- **monolog/monolog**: Logging framework

### Directory Structure

```
kptv-sync/
├── src/
│   ├── Config.php              # Configuration loader
│   ├── ProviderManager.php     # Provider CRUD operations
│   ├── FilterManager.php       # Stream filtering logic
│   ├── SyncEngine.php          # Main sync orchestration
│   ├── MissingChecker.php      # Missing stream detection
│   ├── FixupEngine.php         # Metadata consolidation
│   └── Parsers/
│       ├── BaseProvider.php
│       ├── XtremeCodesProvider.php
│       ├── M3UProvider.php
│       └── ProviderFactory.php
├── kptv-sync.php               # CLI entry point
├── composer.json
├── .kptvconf                   # Config file (not in git)
└── db_schema.sql
```

## Troubleshooting

**Connection Reset Errors**
- Provider may be rate limiting
- Retry logic built-in (3 attempts with backoff)
- Check provider availability

**No Streams Synced**
- Verify provider credentials in database
- Check filter configuration (sf_active=1)
- Use --debug flag to see detailed logs

**Filters Not Working**
- Type 0 filters (include) take precedence
- If include filters exist, streams must match at least one
- Test regex patterns separately before adding

**Slow Performance**
- Batch sizes are optimized (500-1000 per batch)
- Ensure database indexes are present
- Consider running per-provider instead of all at once

**Composer Install Fails**
- Ensure PHP 8.4 is installed: `php -v`
- Check composer version: `composer --version`
- Update composer if needed: `composer self-update`

## Logs

Application logs written to `iptv_sync.log` in working directory with automatic rotation (7 days).

Use `--debug` flag for verbose output during development.

## Development

### Running Tests
```bash
composer test
```

### Code Style
Follow PSR-12 coding standards with PHP 8.4 features:
- Use typed properties
- Use readonly where appropriate
- Use enums for constants
- Use named arguments for clarity

## License

MIT License
Copyright (c) 2025

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
