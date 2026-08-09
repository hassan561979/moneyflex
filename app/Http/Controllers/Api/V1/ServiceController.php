<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Service\IndexServiceRequest;
use App\Http\Requests\Service\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Services\ServiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

/**
 * Services addressed on their own. Creating one requires an owner, so that
 * lives on the nested customer route instead.
 */
class ServiceController extends Controller
{
    public function __construct(private readonly ServiceService $services) {}

    /**
     * GET /api/v1/services
     *
     * Served from the cache when a matching listing is already there.
     */
    #[OA\Get(
        path: '/services',
        operationId: 'listServices',
        summary: 'View all services',
        description: 'A paginated listing across every customer. Responses are cached and invalidated whenever a service changes.',
        security: [['basicAuth' => []], ['bearerAuth' => []]],
        tags: ['Services'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', description: 'Matches name or description.', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['active', 'suspended', 'cancelled'])),
            new OA\Parameter(name: 'customer_id', in: 'query', description: 'Restrict to one customer.', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(
                name: 'sort',
                in: 'query',
                description: 'Column to sort by. Prefix with "-" for descending.',
                schema: new OA\Schema(type: 'string', enum: ['id', 'name', 'price', 'status', 'starts_at', 'ends_at', 'created_at', 'updated_at', '-id', '-name', '-price', '-status', '-starts_at', '-ends_at', '-created_at', '-updated_at']),
            ),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Between 1 and 100.', schema: new OA\Schema(type: 'integer', default: 15, maximum: 100, minimum: 1)),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'A page of services.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Service')),
                    new OA\Property(property: 'links', ref: '#/components/schemas/PaginationLinks'),
                    new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
                ]),
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
        ],
    )]
    public function index(IndexServiceRequest $request): JsonResponse
    {
        return response()->json(
            $this->services->cachedIndex(
                $request->options(),
                $request->integer('customer_id') ?: null,
            ),
        );
    }

    /**
     * GET /api/v1/services/{service}
     */
    #[OA\Get(
        path: '/services/{service}',
        operationId: 'showService',
        summary: 'View a service',
        description: 'Includes the owning customer.',
        security: [['basicAuth' => []], ['bearerAuth' => []]],
        tags: ['Services'],
        parameters: [
            new OA\Parameter(name: 'service', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The service, with its customer embedded.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(
                        property: 'data',
                        allOf: [
                            new OA\Schema(ref: '#/components/schemas/Service'),
                            new OA\Schema(properties: [new OA\Property(property: 'customer', ref: '#/components/schemas/Customer')], type: 'object'),
                        ],
                    ),
                ]),
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function show(Service $service): ServiceResource
    {
        return ServiceResource::make($service->load('customer'));
    }

    /**
     * PUT|PATCH /api/v1/services/{service}
     */
    #[OA\Put(
        path: '/services/{service}',
        operationId: 'updateService',
        summary: 'Update a service',
        description: 'Every field is optional. PATCH behaves identically. The owning customer cannot be changed here.',
        security: [['basicAuth' => []], ['bearerAuth' => []]],
        tags: ['Services'],
        parameters: [
            new OA\Parameter(name: 'service', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Payment Gateway'),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'price', type: 'string', example: '299.00', description: 'At most two decimal places.'),
                new OA\Property(property: 'status', type: 'string', enum: ['active', 'suspended', 'cancelled'], example: 'suspended'),
                new OA\Property(property: 'starts_at', type: 'string', format: 'date', nullable: true),
                new OA\Property(property: 'ends_at', type: 'string', format: 'date', nullable: true, description: 'Must not be earlier than starts_at.'),
            ]),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'The updated service.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Service')]),
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
        ],
    )]
    public function update(UpdateServiceRequest $request, Service $service): ServiceResource
    {
        return ServiceResource::make(
            $this->services->update($service, $request->validated()),
        );
    }

    /**
     * DELETE /api/v1/services/{service}
     */
    #[OA\Delete(
        path: '/services/{service}',
        operationId: 'deleteService',
        summary: 'Delete a service',
        description: 'A soft delete. The record stops appearing in listings and reads.',
        security: [['basicAuth' => []], ['bearerAuth' => []]],
        tags: ['Services'],
        parameters: [
            new OA\Parameter(name: 'service', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Deleted. No content is returned.'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function destroy(Service $service): Response
    {
        $this->services->delete($service);

        return response()->noContent();
    }
}
