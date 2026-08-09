# MoneyFlex API

[![CI](https://github.com/hassan561979/moneyflex/actions/workflows/ci.yml/badge.svg)](https://github.com/hassan561979/moneyflex/actions/workflows/ci.yml)

A REST API over customers and the services they hold, built with Laravel. Each
customer may have many services. Every endpoint is protected with HTTP Basic
Authentication, and a JWT may be used instead.

---

## Running it

Docker is the only prerequisite.

```bash
git clone https://github.com/hassan561979/moneyflex.git
cd moneyflex
make up
```

That is the whole setup. The container creates `.env`, generates the
application key, waits for MySQL to become healthy, runs the migrations and
seeds demonstration data. Seeding backs off as soon as the database holds
anything, so restarting never duplicates it.

| | |
| --- | --- |
| API | http://localhost:8080/api/v1 |
| Swagger UI | http://localhost:8080/api/documentation |
| OpenAPI document | http://localhost:8080/docs |
| Health check | http://localhost:8080/api/v1/health |

MySQL is published on host port **3307** rather than 3306, so it will not
collide with a MySQL already running on the machine.

### Credentials

The seeder creates one account, configurable through `API_USER_EMAIL` and
`API_USER_PASSWORD`:

```
api@moneyflex.test / password123
```

These are demonstration values in a demonstration database. Set your own before
deploying anything.

---

## Endpoints

All ten features the brief asks for, under `/api/v1`.

| Method | Path | Feature |
| --- | --- | --- |
| `GET` | `/customers` | View all customers |
| `POST` | `/customers` | Create a customer |
| `GET` | `/customers/{id}` | View a customer |
| `PUT` `PATCH` | `/customers/{id}` | Update a customer |
| `DELETE` | `/customers/{id}` | Delete a customer |
| `GET` | `/customers/{id}/services` | View services of a customer |
| `POST` | `/customers/{id}/services` | Create a service for a customer |
| `GET` | `/services` | View all services |
| `GET` | `/services/{id}` | View a service |
| `PUT` `PATCH` | `/services/{id}` | Update a service |
| `DELETE` | `/services/{id}` | Delete a service |
| `POST` | `/auth/login` | Exchange credentials for a token |
| `GET` | `/auth/me` | The authenticated account |
| `POST` | `/auth/refresh` | Rotate a token |
| `POST` | `/auth/logout` | Revoke a token |
| `GET` | `/health` | Liveness check, open |

Listings accept `?search=`, `?status=`, `?sort=`, `?per_page=` and `?page=`.
Prefix `sort` with `-` for descending order. Sortable columns are whitelisted
and the page size is capped at 100.

[docs/api.http](docs/api.http) is a runnable collection covering every endpoint,
for the VS Code REST Client or the JetBrains HTTP client.

[docs/INTERVIEW.md](docs/INTERVIEW.md) explains every design decision in the
project and the reasoning behind it.

---

## Authentication

Two schemes are accepted, and one is enough.

**Basic**, which the brief requires:

```bash
curl -u api@moneyflex.test:password123 http://localhost:8080/api/v1/customers
```

**Bearer**, obtained by logging in:

```bash
TOKEN=$(curl -s -X POST http://localhost:8080/api/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"api@moneyflex.test","password":"password123"}' \
  | php -r 'echo json_decode(stream_get_contents(STDIN), true)["access_token"];')

curl -H "Authorization: Bearer $TOKEN" http://localhost:8080/api/v1/customers
```

Without credentials every endpoint answers `401` with a `WWW-Authenticate`
header. The Swagger UI's **Authorize** button accepts either scheme.

To refuse tokens entirely and require Basic alone, swap the `auth.api`
middleware for `auth.basic.api` in [routes/api.php](routes/api.php).

---

## Design notes

**Layering.** Controllers are thin and delegate to a service layer. Validation
lives in form requests, response shape in API resources. Nothing constructs a
query inline in a controller.

**Money is never a float.** Prices are `decimal(12,2)` in MySQL, cast as
`decimal:2`, and travel as JSON strings. `19.99` in is `19.99` out.

**Ownership comes from the URL.** A service is created through its customer's
relation, so a `customer_id` in the request body is ignored and cannot attach a
service to another account.

**Authentication runs before route model binding.** Otherwise a missing record
answers `404` while an existing one answers `401`, which lets an anonymous
caller discover which identifiers exist.

**Soft deletes cascade both ways.** Deleting a customer hides their services;
restoring the customer brings them back. A soft delete issues no `DELETE`, so
the database cascade never fires and the model does it explicitly.

**Caching.** Service listings are cached in Redis under a key derived from
their filters, sort and page, tagged globally and per customer. Writes discard
the affected tags, including the customer delete cascade, which is a mass
update that fires no model events. A hit costs no database queries at all. What
is cached is the rendered payload rather than the paginator, because serialised
framework objects break across upgrades.

**Errors are uniform.** Every failure under `/api` returns
`{"message": ..., "errors": {...}}`. Internals are exposed only when
`APP_DEBUG` is on.

**Tokens.** JWTs are issued with `firebase/php-jwt`, chosen over `jwt-auth`,
which drags in abandoned dependencies. Logout adds the token's identifier to a
Redis denylist with a lifetime equal to the token's own, so it expires by
itself. Refreshing rotates the token and retires the old one.

---

## Working on it

```bash
make            # list the available targets
make test       # run the suite
make coverage   # run it with coverage, failing under 90%
make lint       # check code style
make fix        # apply style fixes
make analyse    # static analysis
make swagger    # regenerate and check the OpenAPI document
make ci         # everything the pipeline runs
make fresh      # rebuild the database with demo data
make shell      # a shell inside the app container
```

### Tests

141 tests, 98.5% coverage, run against the same MySQL and Redis the application
uses rather than SQLite: enum columns, decimal precision, the foreign key
cascade and tagged cache behaviour all differ elsewhere. The schema
`moneyflex_testing` is created on the container's first boot.

Every protected route is enumerated in a dataset, so an endpoint added later
without authentication fails the suite rather than slipping through.

### Pipeline

[.github/workflows/ci.yml](.github/workflows/ci.yml) brings the stack up from a
clean checkout exactly as a new contributor would, which makes the first run
experience part of the build. It then validates the composer manifest, audits
dependencies for advisories, checks style with Pint, analyses at **PHPStan
level 8**, runs the suite with a coverage floor, and verifies the committed
OpenAPI document is both internally consistent and up to date.

On a push to `main` the production image is built and published to
`ghcr.io/hassan561979/moneyflex`. Pull requests build the image without
publishing it.

---

## Stack

| | |
| --- | --- |
| PHP | 8.4 |
| Framework | Laravel 13 |
| Database | MySQL 8.4, Eloquent |
| Cache | Redis 7 |
| Documentation | OpenAPI 3 via l5-swagger, written as PHP attributes |
| Tests | Pest, pcov |
| Analysis | Larastan level 8, Pint |

The image is built in four stages. Development keeps dev dependencies and
disables opcache; production ships no dev dependencies, an authoritative
classmap, opcache with timestamp validation off, and runs as a non-root user.

---

## Brief checklist

| Requirement | |
| --- | --- |
| Laravel, PHP | ✅ |
| CRUD for customers and services over REST | ✅ |
| A customer has many services | ✅ |
| Basic Authentication on every endpoint | ✅ |
| ORM | ✅ Eloquent |
| SQL Server | ✅ MySQL 8.4 |
| Swagger | ✅ at `/api/documentation` |
| **Bonus** unit test coverage | ✅ 141 tests, 98.5% |
| **Bonus** caching | ✅ Redis, invalidated on write |
| **Bonus** JWT | ✅ alongside Basic |
| **Bonus** dockerised | ✅ four-stage build, one command to run |
| **Bonus** CI/CD | ✅ GitHub Actions, image published to GHCR |
