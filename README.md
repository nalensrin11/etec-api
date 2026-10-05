# Product Management API

Laravel API using JWT bearer tokens. Run `php artisan migrate:fresh --seed`, then `POST /api/login` using a seeded account (`admin@example.com` or `student@example.com`, password `password`) and send `Authorization: Bearer <access_token>`. Tokens expire after the configured `JWT_TTL` (60 minutes by default); use `POST /api/refresh` with the current bearer token to obtain a new one. A ready-to-import Postman collection is in `postman/`.

See [API Testing Guide](docs/API_TESTING.md) for every endpoint, role requirement, validation rule, example request, and cURL/Postman test flow.

## Roles and registration

- `POST /api/register` registers an **instructor** only (`name`, `email`, `password`, `password_confirmation`). An instructor logs in, creates one class at `POST /api/classes`, then creates/manages student users at `/api/users`. Their students are automatically assigned to that class.
- **Admin** has all user, class, category, and product management access across classes.
- **Instructor** manages their own class, its student accounts, categories, and products in their class.
- **Student** is read-only. Public website clients should call `GET /api/public/categories` and `GET /api/public/products`; both require no authentication and expose active records only.

Public product URLs also support `GET /api/public/products/{id}` for details, `category_id`, `min_price`, `max_price`, `promotion=1`, and price ordering: `sort=low_price` or `sort=high_price` (alternatively, `sort=price&direction=asc|desc`). Promotions are products where `sale_price < regular_price`; `GET /api/public/products/promotions` is a shortcut.

The protected management endpoints are `/api/classes`, `/api/users`, `/api/categories`, and `/api/products`.

`/api/products` supports `search`, `category_id`, `class_id` (admins only), `status`, `min_price`, `max_price`, `sort`, `direction`, `page`, and `per_page`. Price filters use `COALESCE(sale_price, regular_price)`, the effective selling price.

Students are always restricted to their own class; they cannot choose `class_id` or `created_by`. Admins can select a class while creating a product. Product uploads use `images[]` (jpg/jpeg/png/webp, max 5MB each, 10 total). The first image becomes primary. Image endpoints are POST/DELETE/PATCH `/api/products/{product}/images` and PATCH `/api/products/{product}/images/{image}/primary`.
