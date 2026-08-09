<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * The document level OpenAPI definition: metadata, servers, the two accepted
 * security schemes, and the schemas shared across endpoints.
 *
 * This class holds no behaviour. It exists so the specification is written in
 * one readable place instead of being scattered across controllers.
 */
#[OA\Info(
    version: '1.0.0',
    title: 'MoneyFlex API',
    description: <<<'TEXT'
    A REST API over customers and the services they hold. Each customer may
    have many services.

    **Authentication**

    Every endpoint except the health check and login requires credentials.
    Two schemes are accepted and either is sufficient:

    - `basicAuth` — HTTP Basic, the scheme the API is required to support.
    - `bearerAuth` — a JWT obtained from `POST /api/v1/auth/login`.

    Use the **Authorize** button above to supply either one. The seeded
    account is `api@moneyflex.test` / `password123`.
    TEXT,
    contact: new OA\Contact(name: 'MoneyFlex'),
)]
#[OA\Server(url: '/api/v1', description: 'Current host')]
#[OA\SecurityScheme(
    securityScheme: 'basicAuth',
    type: 'http',
    scheme: 'basic',
    description: 'HTTP Basic Authentication using the account email and password.',
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'A JWT issued by POST /auth/login.',
)]
#[OA\Tag(name: 'Authentication', description: 'Obtaining and revoking tokens')]
#[OA\Tag(name: 'Customers', description: 'Customer records')]
#[OA\Tag(name: 'Services', description: 'Services held by customers')]
#[OA\Tag(name: 'System', description: 'Operational endpoints')]

/*
|--------------------------------------------------------------------------
| Shared schemas
|--------------------------------------------------------------------------
*/
#[OA\Schema(
    schema: 'Customer',
    title: 'Customer',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Acme Holdings'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'acme@moneyflex.test'),
        new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+971500000001'),
        new OA\Property(property: 'address', type: 'string', nullable: true, example: '1 Sheikh Zayed Road, Dubai'),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive'], example: 'active'),
        new OA\Property(property: 'services_count', type: 'integer', example: 3, description: 'Present on listings and single reads.'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Service',
    title: 'Service',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'customer_id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Payment Gateway'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Card acquiring and settlement'),
        new OA\Property(
            property: 'price',
            type: 'string',
            example: '249.99',
            description: 'A decimal carried as a string so it never loses precision in transit.',
        ),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'suspended', 'cancelled'], example: 'active'),
        new OA\Property(property: 'starts_at', type: 'string', format: 'date', nullable: true, example: '2026-01-01'),
        new OA\Property(property: 'ends_at', type: 'string', format: 'date', nullable: true, example: '2027-01-01'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'PaginationMeta',
    title: 'Pagination meta',
    properties: [
        new OA\Property(property: 'current_page', type: 'integer', example: 1),
        new OA\Property(property: 'from', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'last_page', type: 'integer', example: 4),
        new OA\Property(property: 'per_page', type: 'integer', example: 15),
        new OA\Property(property: 'to', type: 'integer', nullable: true, example: 15),
        new OA\Property(property: 'total', type: 'integer', example: 52),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'PaginationLinks',
    title: 'Pagination links',
    properties: [
        new OA\Property(property: 'first', type: 'string', nullable: true),
        new OA\Property(property: 'last', type: 'string', nullable: true),
        new OA\Property(property: 'prev', type: 'string', nullable: true),
        new OA\Property(property: 'next', type: 'string', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ValidationError',
    title: 'Validation error',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The email field is required.'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            example: ['email' => ['The email field is required.']],
            additionalProperties: new OA\AdditionalProperties(type: 'array', items: new OA\Items(type: 'string')),
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Error',
    title: 'Error',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The requested resource was not found.'),
    ],
    type: 'object',
)]

/*
|--------------------------------------------------------------------------
| Shared responses
|--------------------------------------------------------------------------
*/
#[OA\Response(
    response: 'Unauthorized',
    description: 'No credentials were supplied, or they were not accepted.',
    content: new OA\JsonContent(ref: '#/components/schemas/Error', example: ['message' => 'Unauthenticated.']),
)]
#[OA\Response(
    response: 'NotFound',
    description: 'No such record.',
    content: new OA\JsonContent(ref: '#/components/schemas/Error'),
)]
#[OA\Response(
    response: 'ValidationFailed',
    description: 'The request body or query string did not validate.',
    content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'),
)]
final class ApiDefinition {}
