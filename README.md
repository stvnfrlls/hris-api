# HRIS API

A RESTful API for Human Resource Information System built with Laravel. Handles employee management, authentication, and attendance tracking with role-based access control.

## Features

- JWT-based authentication via Laravel Sanctum
- Role-based access control (Admin, HR, Employee)
- Employee management (CRUD)
- Department management (CRUD)
- Position management (CRUD, filterable by department)
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
| Update employee | ✅ | ✅ | ❌ |
| Delete employee | ✅ | ❌ | ❌ |
| View own record | ✅ | ✅ | ✅ |
| Manage departments | ✅ | ✅ | ❌ |
| Delete departments | ✅ | ❌ | ❌ |
| Manage positions | ✅ | ✅ | ❌ |
| Delete positions | ✅ | ❌ | ❌ |
| Clock in / Clock out | ✅ | ✅ | ✅ |
| View own attendance | ✅ | ✅ | ✅ |
| View all attendance | ✅ | ✅ | ❌ |
| Update attendance | ✅ | ✅ | ❌ |

## Getting Started

### Requirements

- PHP 8.3+
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
php artisan migrate
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=DepartmentSeeder
```

Start the server:

```bash
php artisan serve
```

## API Endpoints

### Auth
```
POST   /api/auth/register
POST   /api/auth/login
GET    /api/auth/me
POST   /api/auth/logout
```

### Employees
```
GET    /api/employees
POST   /api/employees
GET    /api/employees/{id}
PUT    /api/employees/{id}
DELETE /api/employees/{id}
```

### Departments
```
GET    /api/departments
POST   /api/departments
GET    /api/departments/{id}
PUT    /api/departments/{id}
DELETE /api/departments/{id}
```

### Positions
```
GET    /api/positions
GET    /api/positions?department_id={id}
GET    /api/positions?employment_type={type}
POST   /api/positions
GET    /api/positions/{id}
PUT    /api/positions/{id}
DELETE /api/positions/{id}
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

# Run with verbose output
php artisan test --verbose

# Run a specific suite
php artisan test tests/Feature/AttendanceTest.php
```

**54 test cases across 5 suites:**

| Suite | Tests |
|---|---|
| AuthTest | 7 |
| EmployeeTest | 12 |
| AttendanceTest | 12 |
| DepartmentTest | 11 |
| PositionTest | 12 |

Set up a separate test database in `.env.testing`:

```env
DB_CONNECTION=pgsql
DB_DATABASE=hris_test
```

## License

MIT