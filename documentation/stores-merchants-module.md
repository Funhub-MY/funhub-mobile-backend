# Stores and Merchants Module

The Stores and Merchants module is a core component of the FunHub Mobile Backend, providing functionality for discovering, retrieving, and interacting with stores and merchants. This module enables users to find stores based on various criteria, view store details, rate stores, and access merchant information and menus.

## User Stories

| As a | I want to | Acceptance Criteria |
|------|-----------|---------------------|
| User | Discover stores | - I can view a list of stores<br>- I can filter stores by category<br>- I can filter stores by merchant<br>- I can search for stores by name<br>- I can see stores I've visited before<br>- I can find stores near my location |
| User | View store details | - I can see basic store information (name, description, etc.)<br>- I can view store ratings and reviews<br>- I can see store locations<br>- I can view store menus<br>- I can see store categories |
| User | Rate and review stores | - I can rate a store<br>- I can see my previous ratings<br>- I can view ratings from other users<br>- I can filter ratings by various criteria |
| User | Interact with merchants | - I can view merchant information<br>- I can see all stores associated with a merchant<br>- I can find merchants near my location<br>- I can view merchant ratings |
| User | Access merchant offers | - I can view available offers from merchants<br>- I can filter offers by various criteria<br>- I can claim offers<br>- I can redeem claimed offers<br>- I can view my claimed offers |

## Key Methods and Logic Flow

### StoreController

#### `index()`
The main method for retrieving stores with complex filtering and sorting capabilities.

**Implementation Details:**
- Constructs a query to fetch stores based on various filters:
  - Category filtering using whereHas to filter by merchant categories
  - Merchant filtering to show stores associated with specific merchants
  - Store ID filtering to retrieve specific stores
  - Search functionality to find stores by name
  - Location-based filtering to find stores in specific areas
- Uses eager loading to preload related data (merchant, categories, location, ratings)
- Implements performance optimizations:
  - Selective field retrieval to minimize data transfer
  - Pagination with configurable limits
  - Efficient join operations
- Returns a collection of stores with all relevant relationships loaded

#### `getStoresFollowingBeenHere()`
Retrieves stores that the authenticated user has visited or followed.

**Implementation Details:**
- Builds a query to find stores where the user has:
  - Created a store rating (indicating they've visited)
  - Interacted with the store in specific ways
- Uses eager loading to preload store relationships
- Applies pagination for efficient data retrieval
- Returns a collection of stores the user has interacted with

#### `getStoresLocationsByStoreId()`
Retrieves location information for specified stores.

**Implementation Details:**
- Accepts a comma-separated list of store IDs
- Converts the string input to an array of IDs
- Queries the Location model to find locations associated with the specified stores
- Uses whereHas to ensure only locations linked to the requested stores are returned
- Returns location data including coordinates, address, and metadata

#### `getRatings()`
Retrieves ratings for a specific store with filtering options.

**Implementation Details:**
- Finds the store by ID and validates its existence
- Builds a query to fetch ratings with user information
- Applies filters based on request parameters:
  - User ID filtering to show ratings from specific users
  - Only mine filtering to show only the authenticated user's ratings
  - Latest only filtering to show only the most recent rating per user
- Implements sorting options for ratings (newest, highest, lowest)
- Returns a paginated collection of store ratings with user information

#### `postRatings()`
Handles the creation or updating of store ratings by users.

**Implementation Details:**
- Validates input data for required fields (rating value, comment)
- Finds the store by ID and validates its existence
- Checks if the user has already rated the store
- If an existing rating is found, updates it with new values
- If no rating exists, creates a new rating record
- Processes any media attachments for the rating
- Dispatches the IndexStore job to update store search indices
- Returns the created or updated rating with user information

#### `getMerchantMenus()`
Retrieves menu information for a specific store.

**Implementation Details:**
- Finds the store by ID and validates its existence
- Retrieves menu categories and items associated with the store
- Structures the response to organize menu items by category
- Returns a hierarchical representation of the store's menu

### MerchantController

#### `index()`
The main method for retrieving merchants with filtering capabilities.

**Implementation Details:**
- Constructs a query to fetch approved merchants
- Applies filters based on request parameters:
  - Search by business name
  - Category filtering to show merchants in specific categories
  - Merchant ID filtering to retrieve specific merchants
  - Store ID filtering to show merchants associated with specific stores
- Uses eager loading to preload related data (categories, offers, stores, locations)
- Includes rating counts for each merchant
- Returns a paginated collection of merchants with all relevant relationships

#### `getAllStoresLocationByMerchantId()`
Retrieves all locations associated with a specific merchant's stores.

**Implementation Details:**
- Finds the merchant by ID
- Queries the Location model to find locations associated with the merchant's stores
- Uses nested whereHas clauses to ensure proper relationship traversal
- Returns a collection of locations linked to the merchant's stores

#### `getNearbyMerchants()`
Finds merchants with stores near a specified location.

**Implementation Details:**
- Uses Algolia search capabilities when enabled
- Accepts latitude, longitude, and radius parameters
- Searches for stores within the specified radius
- Retrieves the merchant information for the found stores
- Returns a collection of merchants sorted by proximity to the specified location

#### `getRatings()`
Retrieves ratings for a specific merchant with filtering options.

**Implementation Details:**
- Finds the merchant by ID
- Builds a query to fetch merchant ratings with user information
- Applies filters based on request parameters:
  - User ID filtering to show ratings from specific users
  - Only mine filtering to show only the authenticated user's ratings
  - Latest only filtering to show only the most recent rating per user
- Implements sorting options for ratings
- Returns a paginated collection of merchant ratings

### MerchantOfferController

#### `index()`
The main method for retrieving merchant offers with extensive filtering options.

**Implementation Details:**
- Constructs a query to fetch published merchant offers
- Applies numerous filters based on request parameters:
  - Category filtering to show offers in specific categories
  - Merchant offer ID filtering to retrieve specific offers
  - City and state filtering for location-based offers
  - Availability filtering (available only, coming soon, not expired)
  - Flash deal filtering for time-sensitive offers
  - Merchant and store filtering
  - Location-based filtering with radius search
- Uses eager loading to preload related data (user, merchant, claims, categories, stores, locations)
- Processes point discount information for offers
- Returns a paginated collection of merchant offers with all relevant relationships

#### `getMyMerchantOffers()`
Retrieves offers that the authenticated user has claimed.

**Implementation Details:**
- Builds a query to find successful claims made by the user
- Applies filters based on request parameters:
  - Redeemed status filtering to show redeemed or unredeemed offers
  - Expiration status filtering to show expired or valid offers
  - Claim ID filtering to retrieve a specific claim
- Uses eager loading to preload related offer data
- Returns a collection of claimed offers with redemption status

#### `postClaimOffer()`
Handles the claiming of merchant offers by users.

**Implementation Details:**
- Validates input data for required fields
- Checks offer availability and validity
- Verifies user eligibility to claim the offer
- Processes payment based on the selected payment method:
  - Points payment with balance verification
  - Fiat payment with gateway integration
- Creates claim records and vouchers
- Sends notifications to relevant parties
- Returns confirmation or payment gateway information

#### `postRedeemOffer()`
Manages the redemption of claimed offers in-store.

**Implementation Details:**
- Validates input data including redemption code
- Verifies the claim exists and belongs to the user
- Checks if the offer has already been redeemed
- Validates the redemption code against the merchant's code
- Creates redemption records
- Updates claim status
- Sends redemption notifications
- Returns confirmation with updated offer information

## Security Considerations
- Authorization checks ensure only authorized users can rate stores
- Input validation prevents invalid rating values
- Redemption codes ensure only legitimate offer claims can be redeemed
- Location data is validated before processing
- Payment processing includes verification steps
- Rate limiting prevents abuse of rating and claiming systems

## Performance Considerations
- Eager loading relationships to prevent N+1 query issues
- Selective field retrieval to minimize data transfer
- Caching of frequently accessed data (e.g., user point balances)
- Background processing for index updates
- Pagination to limit result sets
- Optimized queries for location-based searches
- Indexing of stores and merchants for efficient search operations
