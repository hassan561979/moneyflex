<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Service\IndexServiceRequest;
use App\Http\Requests\Service\StoreServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Customer;
use App\Services\ServiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

/**
 * Services in the context of the customer that owns them.
 */
class CustomerServiceController extends Controller
{
    public function __construct(private readonly ServiceService $services) {}

    /**
     * GET /api/v1/customers/{customer}/services
     *
     * Served from the cache when a matching listing is already there.
     */
    #[OA\Get(
        path: '/customers/{customer}/services',
        operationId: 'listCustomerServices',
        summary: 'View services of a customer',
        description: 'A paginated listing scoped to one customer. Responses are cached per customer and invalidated on any write.',
        security: [['basicAuth' => []], ['bearerAuth' => []]],
        tags: ['Services'],
        parameters: [
            new OA\Parameter(name: 'customer', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
            new OA\Parameter(name: 'search', in: 'query', description: 'Matches name or description.', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['active', 'suspended', 'cancelled'])),
            new OA\Parameter(name: 'sort', in: 'query', description: 'Prefix with "-" for descending.', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15, maximum: 100, minimum: 1)),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'A page of the customer\'s services.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Service')),
                    new OA\Property(property: 'links', ref: '#/components/schemas/PaginationLinks'),
                    new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
                ]),
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
        ],
    )]
    public function index(IndexServiceRequest $request, Customer $customer): JsonResponse
    {
        return response()->json(
            $this->services->cachedIndex($request->options(), $customer->id),
        );
    }

    /**
     * POST /api/v1/customers/{customer}/services
     */
    #[OA\Post(
        path: '/customers/{customer}/services',
        operationId: 'createCustomerService',
        summary: 'Create a service for a customer',
        description: 'The owner is taken from the URL. A customer_id in the body is ignored, so a service can never be attached to another account.',
        security: [['basicAuth' => []], ['bearerAuth' => []]],
        tags: ['Services'],
        parameters: [
            new OA\Parameter(name: 'customer', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'price'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Payment Gateway'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Card acquiring and settlement'),
                    new OA\Property(property: 'price', type: 'string', example: '249.99', description: 'At most two decimal places.'),
                    new OA\Property(property: 'status', type: 'string', enum: ['active', 'suspended', 'cancelled'], default: 'active'),
                    new OA\Property(property: 'starts_at', type: 'string', format: 'date', nullable: true, example: '2026-01-01'),
                    new OA\Property(property: 'ends_at', type: 'string', format: 'date', nullable: true, example: '2027-01-01', description: 'Must not be earlier than starts_at.'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Created. The Location header points at the new service.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Service')]),
                headers: [new OA\Header(header: 'Location', description: 'URL of the created service.', schema: new OA\Schema(type: 'string'))],
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
        ],
    )]
    public function store(StoreServiceRequest $request, Customer $customer): JsonResponse
    {
        $service = $this->services->createForCustomer($customer, $request->validated());

        return ServiceResource::make($service)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', route('services.show', $service));
    }
}
