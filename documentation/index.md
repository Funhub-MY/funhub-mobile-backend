# FunHub Mobile Backend API Documentation

This documentation provides a comprehensive overview of the FunHub Mobile Backend API, organized by modules. Each module includes user stories, acceptance criteria, and logic flow explanations.

## Modules

1. [Authentication](./authentication.md)
   - User registration and login
   - OTP verification
   - Social login
   - Password management

2. [User Management](./user-management.md)
   - User profile management
   - User settings
   - User following/followers

3. [Articles](./articles.md)
   - Article creation and management
   - Comments and interactions
   - Categories and tags

4. [Stores and Merchants](./stores-merchants.md)
   - Store information
   - Merchant offers
   - Ratings and reviews

5. [Merchant Offer Module](./merchant-offer-module.md)
   - Merchant offer creation
   - Offer management
   - Offer tracking

5. [Mission and Rewards](./mission-rewards-module.md)
   - Missions
   - Rewards

6. [Media Management](./media-management-module.md)
   - Media upload
   - Signed URL workflow
   - CloudFront URL encoding

7. [Notification Module](./notification-module.md)
   - Notification types and channels
   - Mission-related notifications
   - User interaction notifications
   - Notification content structure

8. [Help Center Module](./help-center-module.md)
   - Support request management
   - User-admin communication
   - Request categories and status tracking
   - Admin interface for support staff

9. [Payment Module](./payment-module.md)
   - MPAY payment gateway integration
   - FUNCARD purchase workflow
   - Merchant offer payment processing
   - Payment callbacks and transaction handling

10. [Scheduled Commands](./scheduled-commands.md)
    - Command schedule overview
    - Detailed command descriptions
    - Runtime schedules and frequencies
    - Configuration dependencies

11. [Scout Search Engine](./scout-search-engine.md)
    - Algolia integration for Laravel Scout
    - Model-specific search implementations
    - Geolocation search capabilities
    - Indexing processes and optimization
    - Custom ranking and relevance scoring
