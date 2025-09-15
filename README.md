# RTI Solutions - Laravel Advanced CRUD API

A comprehensive Laravel 12 application featuring advanced CRUD operations, role-based authentication, optimistic locking, audit logging, and modern API design patterns.

## Installation

### Prerequisites
- PHP 8.2 or higher
- Composer 2.0+
- Node.js 18+ and npm
- MySQL 8.0+ or PostgreSQL (SQLite is pre-configured for simplicity)

### Setup Instructions

#### Quick Setup (Recommended)
After cloning the repository and navigating to the project directory:

```bash
composer setup
```

This single command will automatically:
- Install PHP and Node.js dependencies
- Generate application key
- Run database migrations
- Seed the database with test data
- Run the server on http://127.0.0.1:8000 (same as http://localhost:8000)

#### Manual Setup (Alternative)
If you prefer to run commands individually:

```bash
composer install && npm install
php artisan key:generate
php artisan migrate
php artisan db:seed
composer run dev
```

#### Database Configuration
The application is pre-configured to use SQLite for simplicity. The database file is automatically created during setup with the supplied .env (in real-life this .env would not be added to the repo however this is to make setup easier)

**To use MySQL instead:**
1. Update your `.env` file:
   ```env
   DB_CONNECTION=mysql
   DB_DATABASE=your_database_name
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```
2. Create the MySQL database
3. Run the migration commands above

#### Start Development Server
```bash
composer run dev
```

The application will be accessible at:
- http://127.0.0.1:8000
- http://localhost:8000

You should see the default Laravel welcome page, confirming the server is running correctly.

### Base URL
```
http://localhost:8000/api/v1
```

### API Versioning Strategy
This API uses URI versioning with the version number included in the path (e.g., `/api/v1/`).

### Authentication Headers
All authenticated endpoints require:
```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

## Postman Collections

### Overview
This project includes comprehensive Postman collections for API testing, located in the `_postman_collections/` directory:

- **RTI_Environment.postman_environment.json** - Environment variables for API testing
- **RTI_Solutions_API.postman_collection.json** - Complete API endpoints collection

### Import Instructions

1. **Import the Environment File:**
   - Open Postman
   - Click "Import" in the sidebar
   - Select `_postman_collections/RTI_Environment.postman_environment.json`
   - Click "Import"

2. **Import the Collection:**
   - Click "Import" again
   - Select `_postman_collections/RTI_Solutions_API.postman_collection.json`
   - Click "Import"

3. **Activate the Environment:**
   - In the top-right corner of Postman, select "RTI Solutions Environment" from the environment dropdown
   - **This step is critical** - the collection won't work properly without the active environment (however feel free to modify the variables as you see fit)

For detailed import instructions, see the [official Postman documentation](https://learning.postman.com/docs/getting-started/importing-and-exporting/importing-data/).

### Environment Variables
The environment includes pre-configured variables for:

- **Base URL:** `http://localhost:8000/api/v1` (matches your development server)
- **Authentication:** Auto-populated tokens from login requests
- **Test Data:** Sample user credentials, task data, and filtering parameters
- **Testing:** Variables for optimistic locking, bulk operations, and other edge cases

### Collection Features
The API collection is organized into folders covering:

- **📁 Authentication** - Registration, login, logout with automatic token handling
- **📁 Tasks** - Full CRUD operations with advanced filtering and sorting
- **📁 Tags** - Tag management and task associations
- **📁 Bulk Operations** - Batch processing with conflict handling
- **📁 Advanced Features** - Optimistic locking, audit logging, and error scenarios

Each request includes:
- Pre-configured headers and authentication
- Test scripts for response validation
- Automatic variable extraction (tokens, IDs, versions)
- Error handling demonstrations

### Getting Started - Authentication

Once the web server is running and both JSON collections have been imported into Postman, you'll need to login to gain a valid bearer token:

1. **Login Process:**
   - Navigate to 'RTI Solutions API > Authentication > Login'
   - Click the 'Body' tab on the right-hand side to view the email and password fields
   - Use the pre-configured credentials:
     - **Regular User**: `user1@example.com` / `password`
     - **Admin User**: `admin@example.com` / `password`
   - Click 'Send' to authenticate and receive your bearer token

2. **Verify Authentication:**
   - After successful login, select 'Authentication > Me' endpoint
   - Click 'Send' to confirm the user has correctly logged in
   - This will display the user's id, email, and role information

3. **Navigate Based on Role:**
   - **Admin users**: Proceed to the 'Admin' endpoints for full administrative access
   - **Regular users**: Proceed to the 'Regular user' endpoints for standard user operations

**Note**: These credentials are included in the 'RTI_Environment.postman_environment.json' file, but feel free to modify them as needed.

### Quick Start
1. Import both files as described above
2. Activate the "RTI Solutions Environment"
3. Follow the authentication steps above to login
4. Explore endpoints based on your user role (tokens are automatically managed)

The collections are designed to work seamlessly with both `http://localhost:8000` and `http://127.0.0.1:8000`.

## Testing

### Running Tests
To run the complete test suite, use the parallel testing approach for optimal performance:

```bash
php artisan test --parallel
```

This command:
- Runs tests in parallel across multiple processes
- Significantly reduces execution time
- Automatically manages database isolation between test processes

### Test Suites
The application includes comprehensive test coverage:

```bash
# Run specific test suites
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Run specific test file
php artisan test tests/Feature/TaskApiTest.php
```

The source of the application can be found in the /app directory, the routes can be found in /routes/api.php 
