# RTI Solutions - Laravel Advanced CRUD API

A comprehensive Laravel 12 application featuring advanced CRUD operations, role-based authentication, optimistic locking, audit logging, and modern API design patterns.

## Table of Contents

- [Features](#features)
- [Technology Stack](#technology-stack)
- [Installation](#installation)
- [API Documentation](#api-documentation)
- [Authentication](#authentication)
- [Testing](#testing)
- [Architecture](#architecture)
- [Deployment](#deployment)
- [Contributing](#contributing)

## Installation

### Prerequisites
- PHP 8.3 or higher
- Composer 2.0+
- Node.js 18+ and npm
- MySQL 8.0+ or PostgreSQL

### Setup Instructions

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd rti-solutions
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Environment configuration**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Database setup**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

5. **Build assets**
   ```bash
   npm run build
   ```

6. **Start development server**
   ```bash
   composer run dev 
   OR 
   php artisan serve
   npm run dev  # In separate terminal
   ```

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
