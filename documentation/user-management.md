# User Management Module

The User Management module handles all aspects of user profile management, settings, notifications, and account preferences in the FunHub Mobile Backend.

## User Stories

| As a | I want to | Acceptance Criteria |
|------|----------|---------------------|
| Registered User | View and update my profile information | - Users can view their current profile information<br>- Users can update name, username, bio, job title, and other personal details<br>- Changes are saved and reflected immediately |
| Registered User | Manage my profile picture and cover image | - Users can upload a profile picture<br>- Users can upload a cover image<br>- Images are properly resized and stored<br>- Old images are removed when new ones are uploaded |
| Registered User | Update my email address | - Users can change their email address<br>- Verification email is sent to the new address<br>- Email is marked as verified only after confirmation |
| Registered User | Update my phone number | - Users can change their phone number<br>- OTP verification is required to confirm the new number<br>- Phone number is updated only after verification |
| Registered User | Change my password | - Users can update their password<br>- Old password verification is required<br>- Password strength requirements are enforced |
| Registered User | Manage my privacy settings | - Users can set profile privacy (public/private)<br>- Privacy settings are enforced across the platform |
| Registered User | Select my content interests | - Users can select article categories of interest<br>- Selected interests influence content recommendations |
| Registered User | Manage my payment methods | - Users can add credit/debit cards<br>- Users can set a default payment method<br>- Users can remove payment methods<br>- Card information is securely tokenized |
| Registered User | View and manage my notifications | - Users can view all notifications<br>- Users can mark notifications as read<br>- Users can mark all notifications as read at once |
| Registered User | Manage my device settings | - Users can update FCM token for push notifications<br>- Users can update OneSignal subscription for notifications<br>- Users can set preferred language |
| Registered User | Participate in the referral program | - Users can view their referral code<br>- Users can enter someone else's referral code<br>- System validates referral codes and prevents self-referrals |

## Logic Flow

### Profile Management

1. **Viewing Profile Information**
   - User requests profile information
   - System retrieves user data including name, email, username, bio, job title, etc.
   - System returns formatted profile data

2. **Updating Profile Information**
   - User submits updated profile information
   - System validates the input data
   - System updates the user record
   - System triggers UserSettingsUpdated event
   - System returns success message with updated data

3. **Profile Picture Management**
   - User uploads a new profile picture
   - System validates the image (format, size)
   - System removes old profile picture if exists
   - System processes and stores the new image
   - System updates user record with new image reference
   - System clears avatar cache
   - System returns success with image URLs

4. **Email Management**
   - User submits new email address
   - System validates email format and uniqueness
   - System updates user record with new unverified email
   - System sends verification email
   - User clicks verification link or enters verification code
   - System marks email as verified

5. **Phone Number Management**
   - User submits new phone number
   - System validates phone number format
   - System sends OTP to the new number
   - User enters OTP for verification
   - System validates OTP
   - System updates user record with new phone number

### Security and Privacy

1. **Password Management**
   - User submits old and new password
   - System validates old password
   - System validates new password strength
   - System updates password with encrypted new password
   - System returns success message

2. **Privacy Settings**
   - User selects privacy preference (public/private)
   - System updates user privacy settings
   - System applies privacy rules to user content and interactions

### Preferences and Personalization

1. **Interest Management**
   - User selects article categories of interest
   - System validates category IDs
   - System links categories to user profile
   - System returns updated list of interests

2. **Language Preference**
   - User selects preferred language
   - System updates user language preference
   - System applies language setting to user experience

### Payment Methods

1. **Adding Payment Method**
   - User submits card information
   - System securely tokenizes card information
   - System stores tokenized card reference
   - System returns success message

2. **Managing Payment Methods**
   - User can view all saved payment methods
   - User can set a default payment method
   - User can remove payment methods

### Notifications

1. **Viewing Notifications**
   - User requests notifications
   - System retrieves notifications ordered by date
   - System includes user information for each notification
   - System returns paginated notification list

2. **Managing Notifications**
   - User can mark individual notifications as read
   - User can mark all notifications as read
   - System updates notification status

### Referral System

1. **Referral Code Management**
   - User requests their referral code
   - System generates or retrieves existing referral code
   - System returns referral code with sharing message

2. **Applying Referral Code**
   - User submits someone else's referral code
   - System validates referral code exists
   - System checks user hasn't been referred before
   - System prevents self-referrals
   - System links referrer and referee
   - System applies any referral benefits

## Security Considerations

1. **Data Protection**
   - Personal information is encrypted in transit and at rest
   - Profile images are stored securely with appropriate access controls
   - Payment information is tokenized and never stored directly

2. **Authentication**
   - Sensitive operations (password change, email change) require re-authentication
   - OTP verification is used for critical changes like phone number updates

3. **Privacy**
   - User privacy settings are respected across all platform interactions
   - Profile information visibility is controlled by privacy settings

4. **Notification Security**
   - Notifications only show appropriate information based on privacy settings
   - Users can only access their own notifications

## API Endpoints

### Profile Management
- `GET /api/user/settings` - Get user settings
- `POST /api/user/settings/name` - Update user name
- `POST /api/user/settings/username` - Update username
- `POST /api/user/settings/bio` - Update user bio
- `POST /api/user/settings/job-title` - Update job title
- `POST /api/user/settings/dob` - Update date of birth
- `POST /api/user/settings/gender` - Update gender
- `POST /api/user/settings/location` - Update location
- `POST /api/user/settings/email` - Update email address
- `POST /api/user/settings/verify-email` - Verify email with token
- `POST /api/user/settings/avatar` - Upload profile picture
- `POST /api/user/settings/cover` - Upload cover image
- `POST /api/user/settings/phone` - Update phone number (send OTP)
- `POST /api/user/settings/phone/verify` - Verify phone number with OTP

### Security and Preferences
- `POST /api/user/settings/password` - Update password
- `POST /api/user/settings/profile-privacy` - Update profile privacy
- `POST /api/user/settings/categories` - Update article category interests
- `POST /api/user/settings/language` - Update language preference

### Payment Methods
- `POST /api/user/settings/card/tokenization` - Add a payment card
- `GET /api/user/settings/cards` - Get user cards
- `POST /api/user/settings/card/remove` - Remove a card
- `POST /api/user/settings/card/default` - Set card as default

### Notifications
- `GET /api/notifications` - Get user notifications
- `POST /api/notifications/mark-read` - Mark all notifications as read
- `POST /api/notifications/mark-read-single` - Mark a single notification as read
- `POST /api/user/settings/fcm-token` - Update FCM token
- `POST /api/user/settings/onesignal-subscription-id` - Save OneSignal subscription ID
- `POST /api/user/settings/onesignal-user-id` - Save OneSignal user ID

### Referral System
- `GET /api/user/settings/referral` - Get user referral code
- `POST /api/user/settings/referral` - Save referral code
