# Mission & Rewards Module

The Mission & Rewards module is a core component of the FunHub Mobile Backend, providing functionality for users to complete missions and earn rewards (FUNBOX points and Ingredients). This module enables users to track progress on various missions, claim rewards upon completion, and accumulate points and ingredients through different activities on the platform.

## User Stories

| As a | I want to | Acceptance Criteria |
|------|-----------|---------------------|
| User | View available missions | - I can see all available missions<br>- I can filter missions by completion status<br>- I can filter missions by claim status<br>- I can filter missions by frequency (daily, monthly, one-off, accumulated)<br>- I can see mission requirements and rewards |
| User | Track mission progress | - I can see my current progress for each mission<br>- I can see which missions are completed<br>- I can see which missions are claimed<br>- I can receive notifications when missions are started<br>- I can receive notifications when missions are completed |
| User | Complete missions | - I can complete missions by performing required actions<br>- I can see when a mission is completed<br>- I can claim rewards for completed missions<br>- I can receive rewards automatically for auto-disburse missions |
| User | Earn rewards | - I can earn FUNBOX points from completed missions<br>- I can earn Ingredients from completed missions<br>- I can see my reward history<br>- I can receive notifications when rewards are received |
| User | Progress through mission chains | - I can unlock new missions by completing prerequisite missions<br>- I can see which missions are prerequisites for others<br>- I can track my progress through mission chains |

## Key Methods and Logic Flow

### MissionController

#### `index()`
The main method for retrieving missions with filtering capabilities.

**Implementation Details:**
- Constructs a query to fetch enabled missions with related data
- Applies filters based on request parameters:
  - Claimed only filtering to show only claimed or unclaimed missions
  - Completed only filtering to show only completed or incomplete missions
  - Frequency filtering to show missions of specific frequencies (daily, monthly, one-off, accumulated)
- Uses eager loading to preload related data:
  - Mission rewards (missionable)
  - User participation records
  - Predecessor missions (for mission chains)
- Handles time-based filtering for different mission frequencies:
  - Daily missions within the current day
  - Monthly missions within the current month
  - Accumulated missions with special handling for claiming
- Returns a paginated collection of missions with all relevant relationships and progress information

#### `postCompleteMission()`
Handles manual completion and reward claiming for missions.

**Implementation Details:**
- Validates input data for required mission ID
- Verifies the user is participating in the mission
- Checks if the mission allows manual completion (not auto-disburse)
- Verifies the mission is actually completed before allowing claim
- Disburses rewards to the user through the MissionService
- For accumulated missions, creates a new instance after claiming to allow repeated completion
- Returns confirmation with completed mission ID and reward details
- Includes error handling for various failure scenarios

#### `getClaimableMissions()`
Retrieves missions that the user is participating in but hasn't completed yet.

**Implementation Details:**
- Queries missions where the user has started participation
- Calculates mission progress by comparing current values to required values
- Determines which missions are claimable based on progress
- Returns a collection of missions with claimable status indicated

#### `getEnabledMissionFrequency()`
Retrieves the list of mission frequencies that are currently enabled in the system.

**Implementation Details:**
- Queries distinct frequency values from enabled missions
- Returns an array of available frequencies (daily, monthly, one-off, accumulated)
- Used by the frontend to display appropriate mission category filters

### MissionService

#### `handleEvent()`
Core method that processes mission progress when events occur in the system.

**Implementation Details:**
- Accepts event type, user, and optional context parameters
- Retrieves eligible missions for the event type and user
- Processes mission progress for each eligible mission
- Uses database transactions to ensure data consistency
- Includes comprehensive error handling and logging

#### `getEligibleMissions()`
Determines which missions are eligible for progress based on event type and user status.

**Implementation Details:**
- Finds missions that include the triggered event type
- Handles mission prerequisites:
  - Includes missions with no prerequisites
  - Includes missions where all prerequisites have been completed
- Filters missions based on eligibility rules for each frequency type
- Returns a collection of missions that should be updated for the event

#### `processMissionProgress()`
Updates mission progress when an event occurs.

**Implementation Details:**
- Gets or creates a user mission participation record
- Handles special cases for accumulated missions:
  - Creates new instances when previous ones are completed and claimed
- Determines if progress should be updated based on mission frequency and completion status
- Updates progress values and checks for mission completion
- Triggers completion logic when requirements are met

#### `disburseReward()`
Handles the distribution of rewards to users upon mission completion.

**Implementation Details:**
- Checks if the mission has reached its reward limit
- Creates a disbursement record to track reward distribution
- Processes the reward based on type (FUNBOX points or Ingredients)
- Updates claimed status for manual claim missions
- Sends notifications to the user about received rewards
- Uses database transactions to ensure data consistency

#### `processReward()`
Processes different types of rewards based on the mission configuration.

**Implementation Details:**
- Determines reward type (FUNBOX points or Ingredients)
- For FUNBOX points, uses PointService to credit points to the user
- For Ingredients, uses PointComponentService to credit components to the user
- Includes descriptive transaction information for tracking
- Handles reward quantity based on mission configuration

### MissionEventListener

#### `handle()`
Entry point for processing all mission-related events in the system.

**Implementation Details:**
- Uses pattern matching to route events to appropriate handlers
- Supports a wide range of events:
  - Interaction events (likes, shares, bookmarks)
  - Comment events (creation, likes)
  - Article creation
  - User following
  - Profile completion
  - Merchant offer purchases
  - Store ratings
  - Support ticket closures
- Includes comprehensive error handling and logging

#### Event-specific handlers
Specialized methods for processing different types of events.

**Implementation Details:**
- `handleInteractionCreated()`: Processes likes, shares, and bookmarks
  - Maps interactions to appropriate mission event types
  - Includes spam detection to prevent abuse
  - Handles accumulated events for content creators
- `handleCommentCreated()`: Processes comment creation events
  - Updates missions for both the commenter and content creator
  - Includes spam detection for rapid commenting
- `handleFollowings()`: Processes user follow events
  - Updates missions for both the follower and followed user
  - Includes spam detection for follow/unfollow abuse
- `handlePurchasedMerchantOffer()`: Processes offer purchase events
  - Handles both points and cash purchases
- `handleRatedStore()`: Processes store rating events
  - Includes verification to prevent rating abuse

#### Spam detection methods
Specialized methods to prevent mission abuse through spam actions.

**Implementation Details:**
- `isSpamInteraction()`: Detects rapid repeated interactions on the same content
  - Uses both caching and database checks
  - Implements cooldown periods between allowed interactions
- `isSpamFollowing()`: Detects follow/unfollow abuse
  - Tracks follow patterns over time
  - Prevents gaming the system through repeated follows
- `isSpamComment()`: Detects comment spam
  - Implements rate limiting for comments
  - Prevents mission farming through rapid commenting
- `isOwnArticleInteraction()`: Prevents self-interaction abuse
  - Detects when users interact with their own content
  - Ensures missions require genuine social interaction

## Security Considerations
- Spam detection mechanisms prevent abuse of the mission system
- Transaction isolation ensures data consistency during reward disbursement
- Authorization checks ensure users can only claim their own missions
- Rate limiting prevents excessive mission progress in short timeframes
- Validation of mission completion status before allowing claims
- Prerequisite checks ensure missions are completed in the correct order
- Reward limits prevent excessive reward distribution

## Performance Considerations
- Eager loading relationships to prevent N+1 query issues
- Selective field retrieval to minimize data transfer
- Caching of spam detection data to reduce database load
- Database transactions to ensure data consistency
- Background processing for notification sending
- Optimized queries for mission eligibility checks
- Efficient handling of accumulated missions to prevent duplicate records
