# <img src="https://cdn.kevp.us/tv/kptv-icon.png" alt="KPTV Logo" width="32" height="32"> KPTV Stream Manager

**Website:** [https://app.kptv.im](https://app.kptv.im)  
**Support:** [GitHub Issues](https://github.com/kpirnie/app.kptv.im/issues)

A web-based IPTV stream management platform that allows you to organize, filter, and export your IPTV content from multiple providers.

## Requirements

- A modern web browser with JavaScript enabled
- Valid IPTV provider credentials (for XC API or M3U sources)

## Getting Started

### 1. Account Registration

1. Visit [https://app.kptv.im](https://app.kptv.im)
2. Click **"Register"** in the navigation menu
3. Fill out the registration form with:
   - First and Last Name
   - Username (will be used for login)
   - Email address
   - Password (must be 8-64 characters with uppercase, lowercase, numbers, and special characters: `!@#$%*`)
4. Complete the reCAPTCHA verification
5. Check your email for an activation link
6. Click the activation link to activate your account

### 2. Logging In

1. Navigate to the **Login** page
2. Enter your username and password
3. Complete the reCAPTCHA verification
4. Click **"Login Now"**

## Managing Your IPTV Setup

### Provider Management

**Providers** are your IPTV sources (XC API services or M3U playlist URLs).

1. Go to **"Your Streams" → "Your Providers"**
2. Click the **"+"** icon to add a new provider
3. Configure your provider:
   - **Name**: A friendly name for identification
   - **Type**: Choose between XC API or M3U
   - **Domain**: Your provider's server URL
   - **Credentials**: Username and password (if required)
   - **Stream Type**: MPEGTS or HLS format
   - **Priority**: Order of preference (lower numbers = higher priority)
   - **Filtering**: Enable/disable content filtering for this provider

### Stream Categories

Your streams are automatically organized into three categories:

- **Live Streams**: Live TV channels and broadcasts
- **Series Streams**: TV shows, series, and episodic content  
- **Other Streams**: Uncategorized or miscellaneous content

#### Managing Streams

1. Navigate to the appropriate stream category
2. Use the search bar to find specific content
3. Click on stream names or channel numbers to edit them inline
4. Use the action buttons to:
   - **Play**: Test the stream in the built-in player
   - **Copy Link**: Copy the stream URL to clipboard
   - **Move**: Transfer streams between categories
   - **Edit**: Modify stream details
   - **Delete**: Remove unwanted streams

#### Bulk Operations

- Select multiple streams using the checkboxes
- Use the toolbar buttons to:
  - Move selected streams to different categories
  - Activate/deactivate multiple streams
  - Delete multiple streams at once

### Content Filtering

**Filters** help you automatically organize and exclude unwanted content.

1. Go to **"Your Streams" → "Your Filters"**
2. Create filters with these types:
   - **Include Name (regex)**: Only include streams matching a pattern
   - **Exclude Name**: Remove streams with specific names
   - **Exclude Name (regex)**: Remove streams matching a pattern
   - **Exclude Stream (regex)**: Filter by stream URL patterns
   - **Exclude Group (regex)**: Filter by group/category patterns

### Playlist Export

Export your organized streams as M3U playlists for use in media players:

#### Full Playlists
- **Live Streams**: Click the "Export the Playlist" link in the Live Streams menu
- **Series Streams**: Click the "Export the Playlist" link in the Series Streams menu

#### Provider-Specific Playlists
- Access provider-specific exports from the **Providers** page
- Each provider has separate Live and Series playlist export buttons

*Note: All playlist export links are conveniently located in the main navigation menus for easy access.*

## Built-in Stream Player

Test your streams directly in the browser:

1. Click the **Play** button next to any stream
2. The player supports multiple formats:
   - HLS (.m3u8) streams
   - MPEG-TS (.ts) streams
   - Standard MP4/WebM video files
3. If a stream fails to play, try copying the URL to an external player like VLC

## Account Management

### Password Changes
1. Go to **"Your Account" → "Change Your Password"**
2. Enter your current password
3. Enter and confirm your new password
4. Click **"Change Your Password"**

### Forgot Password
1. Click **"Forgot Your Password?"** on the login page
2. Enter your username and email address
3. A new temporary password will be emailed to you
4. Log in with the temporary password and change it immediately

## Tips for Best Results

- **Provider Priority**: Set lower priority numbers for your most reliable providers
- **Stream Organization**: Use descriptive names when editing streams for easier management
- **Filtering**: Start with broad filters and refine them based on your content needs
- **Testing**: Use the built-in player to verify stream quality before exporting playlists
- **Regular Maintenance**: Periodically review and clean up inactive or broken streams

## Support

For technical issues, feature requests, or bug reports, please visit our [GitHub Issues](https://github.com/kpirnie/app.kptv.im/issues) page.

---

*This platform is intended for legitimate IPTV management purposes only. Users are responsible for ensuring they have proper authorization for any content they manage through this service.*