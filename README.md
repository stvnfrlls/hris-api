# HRIS API

A RESTful API for Human Resource Information System built with Laravel. Handles employee management, authentication, and attendance tracking with role-based access control.

## Features

- JWT-based authentication via Laravel Sanctum
- Role-based access control (Admin, HR, Employee)
- Employee management (CRUD)
- Attendance tracking with clock-in/clock-out
- Late detection and attendance status management
- Admin attendance override

## Tech Stack

- **Framework:** Laravel 13
- **Database:** PostgreSQL
- **Auth:** Laravel Sanctum
- **Testing:** PHPUnit (Feature Tests)

## Roles & Permissions

| Action | Admin | HR | Employee |
|---|---|---|---|
| List all employees | ✅ | ✅ | ❌ |
| Create employee | ✅ | ✅ | ❌ |
| View own record | ✅ | ✅ | ✅ |
| Clock in / Clock out | ✅ | ✅ | ✅ |
| View own attendance | ✅ | ✅ | ✅ |
| View all attendance | ✅ | ✅ | ❌ |
| Update attendance | ✅ | ❌ | ❌ |

## Getting Started

### Requirements

- PHP 8.2+
- PostgreSQL
- Composer

### Installation

```bash
git clone https://github.com/stvnfrlls/hris-api.git
cd hris-api

composer install

cp .env.example .env
php artisan key:generate
```

Configure your `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=hris
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

Run migrations and seeders:

```bash
php artisan migrate --seed
```

Start the server:

```bash
php artisan serve
```

## API Endpoints

### Auth
```
POST   /api/register
POST   /api/login
GET    /api/profile
POST   /api/logout
```

### Employees
```
GET    /api/employees
POST   /api/employees
GET    /api/employees/{id}
PUT    /api/employees/{id}
DELETE /api/employees/{id}
```

### Attendance
```
POST   /api/attendance/clock-in
POST   /api/attendance/clock-out
GET    /api/attendance
GET    /api/attendance/{id}
PUT    /api/attendance/{id}
```

## Running Tests

```bash
php artisan test
```

Set up a separate test database in `.env.testing`:

```env
DB_CONNECTION=pgsql
DB_DATABASE=hris_test
```

## License

MIT