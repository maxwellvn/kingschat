# Message Tracking System

This system tracks message counts for users and enforces message limits based on premium status.

## Setup Instructions

### Prerequisites

- XAMPP (or similar local server environment with PHP and MySQL)
- Web browser

### Database Setup

1. Start your XAMPP Apache and MySQL services
2. Navigate to `http://localhost/topsecret/init_db.php` in your web browser
3. This will create the necessary database and tables

### Files Overview

- `includes/db_connect.php` - Database connection and helper functions
- `update_message_count.php` - API endpoint to check message count and limits
- `send_message.php` - Sends messages and logs them to the database
- `activate_premium.php` - Activates premium status for users
- `db_status.php` - Shows database status and message counts
- `init_db.php` - Initializes the database and tables

## How It Works

### Database Structure

The system uses two main tables:

1. **users** - Stores user information and premium status
   - `id` - Auto-incremented ID
   - `user_id` - Unique user identifier from Firebase
   - `username` - User's display name
   - `email` - User's email address
   - `is_premium` - Whether the user has premium status (0 or 1)
   - `message_limit` - Maximum number of messages the user can send
   - `created_at` - When the user was created
   - `updated_at` - When the user was last updated

2. **messages** - Stores sent messages
   - `id` - Auto-incremented ID
   - `user_id` - User who sent the message
   - `recipient_id` - User who received the message
   - `message_text` - Content of the message
   - `sent_at` - When the message was sent

### Message Tracking Flow

1. User logs in with Firebase authentication
2. User's ID is extracted from the Firebase token
3. When a user sends a message:
   - The system checks if the user exists in the database
   - If not, it creates a new user record
   - It checks if the user has premium status
   - It checks if the user has reached their message limit
   - If allowed, the message is sent and logged in the database
4. The message count is tracked in the database instead of session variables

### Viewing Message Counts

To view message counts and database status, navigate to:
`http://localhost/topsecret/db_status.php`

## Troubleshooting

- If you encounter database connection issues, check that your MySQL service is running
- If tables are not created properly, try running `init_db.php` again
- Check the `error.log` file for detailed error messages 