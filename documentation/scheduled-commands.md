# FunHub Scheduled Commands

This document provides a comprehensive overview of all scheduled commands in the FunHub Mobile Backend, including their runtime schedules and descriptions.

## Command Schedule Overview

| Schedule | Commands |
|----------|----------|
| Every Minute | `send-custom-notification`, `articles:run-engagements` |
| Every Five Minutes | `article:publish`, `byteplus:check-video-status` (when enabled) |
| Every Ten Minutes | `articles:sync-location-as-stores` |
| Every Fifteen Minutes | `merchant-offers:release`, `articles:sync-ratings-to-store-ratings`, `bubble:sync-user-store-ratings` (when enabled) |
| Every Thirty Minutes | `city-names:populate`, `articles:categorize` (when enabled) |
| Hourly | `fetch:news-feed`, `media-partner:auto-publish-by-keywords`, `ImportedContactMatching` job, `stores:auto-hide-unonboarded`, `redeem:send-review-reminder` |
| Every Two Hours | `generate:article-views`, `update:scheduled-views` |
| Daily (Specific Times) | Various commands at scheduled times between 00:00 and 01:00 |
| Daily | `telescope:prune` |

## Command Details

### Every Minute

#### `send-custom-notification`
- **Description**: Sends custom notifications to selected users at scheduled times
- **Implementation**: Retrieves system notifications scheduled within the last 5 minutes that haven't been sent yet and dispatches them to targeted users
- **Purpose**: Enables scheduled delivery of notifications for marketing, announcements, and user engagement

#### `articles:run-engagements`
- **Description**: Processes scheduled article engagement actions
- **Implementation**: Finds article engagements scheduled for the current time or earlier that haven't been executed yet and processes them
- **Purpose**: Allows for scheduling of automated engagement actions (likes, comments) on articles for promotional purposes

### Every Five Minutes

#### `article:publish`
- **Description**: Publishes articles that are scheduled to be published
- **Implementation**: Finds draft articles with a published_at date in the past, excluding articles from media partners and mobile sources
- **Purpose**: Enables content scheduling for better publication timing and workflow management

#### `byteplus:check-video-status` (when enabled)
- **Description**: Checks the status of videos being processed by BytePlus VOD service
- **Implementation**: Queries the BytePlus API for pending videos and updates their status in the database
- **Purpose**: Ensures video processing status is kept up-to-date for user-facing features

### Every Ten Minutes

#### `articles:sync-location-as-stores`
- **Description**: Creates store entries for locations mentioned in articles that don't already have associated stores
- **Implementation**: Finds locations with published articles that aren't linked to stores and creates new store entries for them
- **Purpose**: Ensures that all locations mentioned in content can be found in store searches, improving discoverability

### Every Fifteen Minutes

#### `merchant-offers:release`
- **Description**: Releases merchant offer stock from failed or pending transactions
- **Implementation**: Finds transactions for merchant offers that have been pending for longer than the configured time limit and releases their reserved stock
- **Purpose**: Prevents inventory from being permanently locked when payment processes fail or time out

#### `articles:sync-ratings-to-store-ratings`
- **Description**: Synchronizes article ratings to store ratings
- **Implementation**: Updates store rating data based on ratings given to articles associated with those stores
- **Purpose**: Ensures store ratings reflect the quality of content related to those stores

#### `bubble:sync-user-store-ratings` (when enabled)
- **Description**: Synchronizes user store ratings from the Bubble platform
- **Implementation**: Pulls user store ratings data from Bubble and updates the local database
- **Purpose**: Maintains consistency between platforms for store rating data

### Every Thirty Minutes

#### `city-names:populate`
- **Description**: Populates city names for locations
- **Implementation**: Finds locations without city names and attempts to populate them using geocoding services
- **Purpose**: Improves location data quality for better search and filtering

#### `articles:categorize` (when enabled)
- **Description**: Automatically categorizes articles using machine learning
- **Implementation**: Processes uncategorized articles and assigns categories based on content analysis
- **Purpose**: Reduces manual categorization work and improves content organization

### Hourly

#### `fetch:news-feed`
- **Description**: Fetches news from external sources
- **Implementation**: Connects to configured news APIs and imports relevant content
- **Purpose**: Keeps the platform's news content fresh and up-to-date

#### `media-partner:auto-publish-by-keywords`
- **Description**: Automatically publishes articles from media partners based on keywords
- **Implementation**: Scans imported but unpublished media partner articles for configured keywords and publishes matching content
- **Purpose**: Streamlines content curation from partner sources

#### `ImportedContactMatching` (job)
- **Description**: Matches imported contacts to users
- **Implementation**: Processes imported contact lists and attempts to match them with existing users
- **Purpose**: Helps users find connections they may know on the platform

#### `stores:auto-hide-unonboarded`
- **Description**: Automatically hides stores that haven't completed onboarding
- **Implementation**: Identifies stores that have been in an incomplete state for too long and hides them from search results
- **Purpose**: Maintains quality of store listings by hiding incomplete entries

#### `redeem:send-review-reminder`
- **Description**: Sends reminders to users to review redeemed offers
- **Implementation**: Identifies recent redemptions without reviews and sends notification reminders
- **Purpose**: Increases review submission rates for better merchant feedback

### Every Two Hours

#### `generate:article-views`
- **Description**: Generates article view statistics
- **Implementation**: Processes raw view data and generates aggregated statistics
- **Purpose**: Provides accurate view metrics for content performance analysis

#### `update:scheduled-views`
- **Description**: Updates scheduled view data
- **Implementation**: Processes and updates view data for scheduled content
- **Purpose**: Ensures view statistics are maintained for scheduled content

### Daily at Specific Times

#### `merchant-offers:publish` (00:00)
- **Description**: Publishes scheduled merchant offers
- **Implementation**: Finds merchant offers scheduled to be published and updates their status
- **Purpose**: Enables scheduling of offer publications for optimal timing

#### `merchant-offers:auto-archieve` (00:05)
- **Description**: Archives expired merchant offers
- **Implementation**: Identifies offers past their end date and archives them
- **Purpose**: Keeps the active offers list clean and relevant

#### `article:auto-archive` (00:10)
- **Description**: Archives old articles based on configuration
- **Implementation**: Finds articles older than the configured threshold and archives them
- **Purpose**: Maintains a fresh content library by moving older content to archive

#### `merchant-offers:send-expiring-notification` (00:25)
- **Description**: Sends notifications about expiring merchant offers
- **Implementation**: Identifies offers expiring soon and sends notifications to relevant users
- **Purpose**: Reminds users to use their offers before they expire

#### `merchant-offers:auto-move-vouchers-unsold` (00:35)
- **Description**: Moves unsold vouchers back to available inventory
- **Implementation**: Identifies vouchers that weren't sold within the allocation period and returns them to inventory
- **Purpose**: Optimizes voucher allocation for better inventory management

#### `campaign:redistribute-quantities` (00:45, when enabled)
- **Description**: Redistributes campaign quantities
- **Implementation**: Rebalances quantities across campaign offers based on performance
- **Purpose**: Optimizes inventory allocation for better campaign performance

#### `articles:check-expired` (01:00)
- **Description**: Checks for expired articles
- **Implementation**: Identifies articles that have reached their expiration date and updates their status
- **Purpose**: Ensures time-sensitive content is properly managed

### Daily

#### `telescope:prune`
- **Description**: Prunes old Telescope entries
- **Implementation**: Deletes Telescope monitoring data older than the configured retention period
- **Purpose**: Prevents database bloat from monitoring data

## Disabled Commands

The following commands are currently commented out in the scheduler:

#### `mixpanel:sync-voucher-sales`
- **Description**: Syncs voucher sales data to Mixpanel
- **Implementation**: Exports voucher sales data to Mixpanel for analytics
- **Purpose**: Provides advanced analytics on voucher sales performance

## Configuration Dependencies

Several commands depend on specific configuration settings:

1. **BytePlus VOD Integration**
   - Enabled by: `config('services.byteplus.enabled_vod') == true`
   - Affects: `byteplus:check-video-status`

2. **Bubble Integration**
   - Enabled by: `config('services.bubble.status') == true`
   - Affects: `bubble:sync-user-store-ratings`

3. **Automatic Article Categorization**
   - Enabled by: `config('app.auto_article_categories') == true`
   - Affects: `articles:categorize`

4. **Automatic Voucher Redistribution**
   - Enabled by: `config('app.auto_redistribute_vouchers') == true`
   - Affects: `campaign:redistribute-quantities`

## Server Configuration

All scheduled commands use the `onOneServer()` directive to ensure they only run on one server in a multi-server deployment. This prevents duplicate execution in scaled environments.

Many commands also use `withoutOverlapping()` to prevent concurrent execution of the same command, which is important for commands that might take longer than their scheduled interval to complete.

## Conclusion

The FunHub Mobile Backend uses a comprehensive set of scheduled commands to automate various maintenance, synchronization, and user engagement tasks. These commands ensure that the platform operates efficiently and provides timely updates to users while maintaining data consistency across different features and integrations.
