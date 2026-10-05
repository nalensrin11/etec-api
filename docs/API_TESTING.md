# API Testing Guide

Base URL: `http://localhost:8000/api` (change it to your server URL). Send `Accept: application/json` on every request. Protected endpoints also need:

```http
Authorization: Bearer <access_token>
```

## Default response format

Every successful response contains `message`, `status`, and `data`. Paginated lists additionally contain `meta`.

```json
{"message":"Records retrieved successfully.","status":200,"data":[],"meta":{"current_page":1,"last_page":1,"per_page":15,"total":0}}
```

Every error response contains `message` and `status`. Validation errors also contain `errors`, keyed by the invalid request field.

```json
{
  "message":"The given data was invalid.",
  "status":422,
  "errors":{"code":["The code has already been taken."]}
}
```

For an unexpected `500` error during local development, set `APP_DEBUG=true` in `.env`. The response then additionally includes `exception`, `file`, and `line` so you can locate the source. Never enable debug in production; production intentionally returns only `{"message":"Server error.","status":500}`.

## Prepare the database

```bash
php artisan migrate:fresh --seed
php artisan serve
```

Seeded accounts use password `password`:

| Account | Role | Email |
| --- | --- | --- |
| Admin | Full access | `admin@example.com` |
| Student | Public/read-only account | `student@example.com` |

An instructor can register with the API. Login responses contain `access_token`; use this as the Bearer token in Postman or cURL. The bundled [Postman collection](../postman/Product-Management-API.postman_collection.json) stores this token automatically after Login.

## Roles

| Role | Scope |
| --- | --- |
| Admin | All users, classes, categories, and products across every class. |
| Instructor | One owned class, its students, categories, and products belonging to that class. |
| Student | No management endpoints. Use the public category/product URLs only. |

## Authentication

### Register instructor — `POST /register`

No authentication. This endpoint always creates the `instructor` role.

```json
{"name":"Ivy Instructor","email":"ivy@example.com","password":"password123","password_confirmation":"password123"}
```

Expected: `201 Created`. Then login and create the instructor's class.

### Login — `POST /login`

No authentication.

```json
{"email":"admin@example.com","password":"password"}
```

Expected: `200 OK`.

```json
{
  "message":"Authentication successful.",
  "status":200,
  "data":{
  "access_token":"<jwt>",
  "token_type":"Bearer",
  "expires_in":3600,
  "user":{
    "id":1,
    "name":"Admin",
    "email":"admin@example.com",
    "role":"admin",
    "class_id":null
  }
  }
}
```

Use `data.user.role` after login to identify the account type: `admin`, `instructor`, or `student`. `data.user.class_id` identifies an instructor/student's class and is `null` for an admin.

### Current user — `GET /user`

Any authenticated role. Expected: `200 OK` with the authenticated user.

### Refresh JWT — `POST /refresh`

Any authenticated role. Use the current Bearer token. Expected: `200 OK` with a replacement `access_token`.

### Logout — `POST /logout`

Any authenticated role. Expected: `200 OK`; the current JWT is blacklisted.

## Public catalog

These endpoints do not need a token and expose active records only. **`class_id` is required** on every public category/product request, so records from another instructor's class are never returned.

### Categories — `GET /public/categories`

Example: `GET /public/categories?class_id=1&page=1`. Expected: paginated active categories belonging only to class `1`.

### List products — `GET /public/products`

Parameters are optional:

| Parameter | Example | Meaning |
| --- | --- | --- |
| `search` | `MacBook` | Product-name search. |
| `class_id` | `1` | Required. Returns only this class's products. |
| `category_id` | `1` | Only products in that category. |
| `promotion` | `1` | Only products where `sale_price < regular_price`. |
| `min_price` / `max_price` | `100` / `1000` | Effective-price range. |
| `sort` | `low_price`, `high_price`, `price`, `name`, `regular_price`, `created_at` | Ordering field. |
| `direction` | `asc` or `desc` | Used with `sort=price`, `name`, `regular_price`, or `created_at`. |
| `page` / `per_page` | `1` / `12` | Pagination (`per_page` maximum is 100). |

Examples:

```text
GET /public/products?class_id=1&category_id=1&sort=low_price
GET /public/products?class_id=1&promotion=1&sort=high_price
GET /public/products?class_id=1&min_price=100&max_price=1000&sort=price&direction=asc
```

`effective_price` is `sale_price` when set, otherwise `regular_price`.

### Promotions shortcut — `GET /public/products/promotions`

Same result as `GET /public/products?class_id=1&promotion=1`. It accepts the other public list filters and pagination parameters.

### Product detail — `GET /public/products/{product_id}`

Example: `GET /public/products/1?class_id=1`. Expected: `200 OK` with category, prices, images, primary image, and product details. A product in another class, inactive product, or missing product returns `404`.

## Classes (JWT required)

### List classes — `GET /classes`

Admin sees all classes. Instructor sees only classes created by that instructor. Student receives `403`.

### Create class — `POST /classes`

Admin or instructor.

```json
{"name":"Web Development A","code":"WEB-A","status":"active"}
```

Expected: `201 Created`. An instructor can create only one class; it is automatically attached to the instructor account.

### Update class — `PUT /classes/{class_id}`

Admin or the owning instructor.

```json
{"name":"Web Development A - Morning","status":"inactive"}
```

Expected: `200 OK`.

## Users (JWT required)

### List users — `GET /users`

Admin sees all users. Instructor sees only student users in their own class. Student receives `403`.

### Create user — `POST /users`

Admin payload:

```json
{"name":"Dara","email":"dara@example.com","password":"password123","role_id":"<role ID>","class_id":1,"status":"active"}
```

Instructor payload (do not send `role_id` or `class_id`; both are set securely by the API):

```json
{"name":"Dara","email":"dara@example.com","password":"password123","status":"active"}
```

Expected: `201 Created`. Instructor-created users always receive the student role and instructor's class.

### Update user — `PUT /users/{user_id}`

Admin may update `name`, `email`, `password`, `role_id`, `class_id`, and `status`. Instructor may update only a student in their own class and cannot change that student's role or class.

```json
{"name":"Dara Updated","status":"inactive"}
```

Expected: `200 OK`.

### Delete user — `DELETE /users/{user_id}`

Admin or owning instructor as above. Expected: `200 OK`. A user cannot delete their own account.

## Categories (JWT required)

### List categories — `GET /categories`

Admin sees all class categories (optionally filter using `?class_id=1`). Instructor sees only categories in their own class. Student receives `403`.

### Create category — `POST /categories`

Admin or instructor.

```json
{"class_id":1,"name":"Laptop","description":"Portable computers","status":"active"}
```

Expected: `201 Created`. For an instructor, omit `class_id`; the API automatically assigns the instructor's class.

### Update category — `PUT /categories/{category_id}`

Admin or instructor. Example:

```json
{"description":"Updated description","status":"inactive"}
```

Expected: `200 OK`.

### Delete category — `DELETE /categories/{category_id}`

Admin or owning instructor. Expected: `200 OK`. A category with products returns `422` and must not be deleted.

## Products (JWT required)

All product-management endpoints require Admin or Instructor. Instructors can manage only their class's products. Students receive `403`.

### List products — `GET /products`

Parameters: `search`, `category_id`, `status`, `min_price`, `max_price`, `sort` (`name`, `sku`, `regular_price`, `quantity`, `created_at`), `direction`, `page`, and `per_page`. Admin can additionally use `class_id`. Instructors cannot bypass their class scope with `class_id`.

Example: `GET /products?status=active&sort=regular_price&direction=asc`.

### Create product — `POST /products`

Use `multipart/form-data`, not raw JSON. Admin must include `class_id`; instructor must not supply it.

| Field | Required | Rules |
| --- | --- | --- |
| `class_id` | Admin only | Existing class ID. |
| `category_id` | Yes | Existing category ID from the same class. |
| `name`, `sku` | Yes | SKU is unique. |
| `description` | No | Text. |
| `regular_price` | Yes | Numeric, at least 0. |
| `sale_price` | No | Numeric, at least 0, not greater than regular price. |
| `quantity` | Yes | Integer, at least 0. |
| `status` | Yes | `active` or `inactive`. |
| `images[]` | No | Up to 10 JPG/JPEG/PNG/WEBP images, 5MB each. Send these files with the same `POST /products` request. `image[]` is also accepted for compatibility. |

The API sets `created_by` from the JWT and uses the instructor's class automatically. The first uploaded image becomes primary. Expected: `201 Created`.

### Get product — `GET /products/{product_id}`

Admin or owning instructor only. Expected: `200 OK`; another class's product returns `403`.

### Update product — `PUT /products/{product_id}`

Admin or owning instructor. Updatable fields: `category_id`, `name`, `sku`, `description`, `regular_price`, `sale_price`, `quantity`, and `status`. Class and creator cannot be changed.

```json
{"sale_price":899,"quantity":12,"status":"active"}
```

Expected: `200 OK`.

### Delete product — `DELETE /products/{product_id}`

Admin or owning instructor. Expected: `204 No Content`. Product-image rows and stored image files are removed.

## Product images (JWT required)

### Add images — `POST /products/{product_id}/images`

Admin or owning instructor. Send `multipart/form-data` with one or more `images[]` files. The same extensions, 5MB-per-file, and 10-images-total limits apply. Expected: `200 OK`.

### Set primary image — `PUT /products/{product_id}/images/{image_id}/primary`

Admin or owning instructor. Expected: `200 OK`. Exactly one image is primary. An image belonging to a different product returns `404`.

### Delete image — `DELETE /products/{product_id}/images/{image_id}`

Admin or owning instructor. Expected: `204 No Content`. Its physical storage file is removed. If it was primary, the next image by sort order becomes primary.

## Expected errors

| Status | Meaning |
| --- | --- |
| `401` | Missing, expired, invalid, or logged-out JWT. Login again or refresh the token. |
| `403` | Authenticated user does not have the required role or class ownership. |
| `404` | Missing resource, inactive public product, or image does not belong to the URL's product. |
| `422` | Validation failure or business rule violation. The response has `message`, `status`, and field-specific `errors`. |

## Quick cURL flow

```bash
# Login and copy access_token from the response.
curl -X POST http://localhost:8000/api/login \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"email":"admin@example.com","password":"password"}'

# Use the copied JWT.
curl http://localhost:8000/api/products \
  -H 'Accept: application/json' \
  -H 'Authorization: Bearer YOUR_ACCESS_TOKEN'

# Public promotions do not need a token.
curl 'http://localhost:8000/api/public/products/promotions?class_id=1&sort=low_price'
```
