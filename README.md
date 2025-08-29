# Veritas Digital Exeat System

A comprehensive digital exeat management system for Veritas University, built with Laravel. This backend powers workflows for students, staff, parents, hostel/admin, and security, providing robust approval, notification, and analytics features.

## Features
- Student exeat request creation (regular, medical, weekend)
- Multi-stage staff approval workflow (CMD, Deputy Dean, Dean, Parent Consent)
- Hostel and security sign-out/sign-in tracking
- Real-time notifications (email, SMS, WhatsApp)
- Admin analytics and reporting endpoints
- Role-based authentication and authorization
- Audit logs and approval history

## Project Structure
```
app/
  Http/Controllers/    # API controllers for all user roles
  Models/              # Eloquent models for all tables
  Services/            # Workflow, notification, and business logic
config/
  mail.php             # Email sender configuration
routes/
  api.php              # API route definitions
```

## Getting Started
### Prerequisites
- PHP 8.1+
- Composer
- MySQL or SQLite
- Node.js & npm (for frontend, if applicable)

### Installation
1. Clone the repository:
   ```sh
   git clone <repo-url>
   cd backend
   ```
2. Install dependencies:
   ```sh
   composer install
   ```
3. Copy and configure environment:
   ```sh
   cp .env.example .env
   # Edit .env with your DB, mail, and queue settings
   ```
4. Generate app key:
   ```sh
   php artisan key:generate
   ```
5. Run migrations:
   ```sh
   php artisan migrate
   ```
6. (Optional) Seed database:
   ```sh
   php artisan db:seed
   ```
7. Start the development server:
   ```sh
   php artisan serve
   ```

## API Documentation
See `EXEAT_TESTING_GUIDE.md` for detailed API endpoints, request/response examples, and workflow scenarios.

## Environment Variables
Key settings in `.env`:
- `APP_NAME`, `APP_URL`
- `DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, ...
- `MAIL_MAILER`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`
- `TWILIO_*` (for SMS/WhatsApp)
- `AWS_*` (if using S3)

## Testing
Run feature and unit tests:
```sh
php artisan test
```

## Contribution
- Fork the repo and create a feature branch
- Follow PSR-12 coding standards
- Submit pull requests with clear descriptions

## License
MIT License. See [LICENSE](LICENSE).

---
For more information, see the full workflow and API guide in `EXEAT_TESTING_GUIDE.md`.
