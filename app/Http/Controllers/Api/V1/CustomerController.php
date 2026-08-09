<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\IndexCustomerRequest;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $customers) {}

    /**
     * GET /api/v1/customers
     */
    #[OA\Get(
        path: '/customers',
        operationId: 'listCustomers',
        summary: 'View all customers',
        description: 'A paginated listing, optionally searched, filtered and sorted.',
        security: [['basicAuth' => []], ['bearerAuth' => []]],
        tags: ['Customers'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', description: 'Matches name, email or phone.', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['active', 'inactive'])),
            new OA\Parameter(
                name: 'sort',
                in: 'query',
                description: 'Column to sort by. Prefix with "-" for descending.',
                schema: new OA\Schema(type: 'string', enum: ['id', 'name', 'email', 'status', 'created_at', 'updated_at', '-id', '-name', '-email', '-status', '-created_at', '-updated_at']),
            ),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Between 1 and 100.', schema: new OA\Schema(type: 'integer', default: 15, maximum: 100, minimum: 1)),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'A page of customers.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Customer')),
                    new OA\Property(property: 'links', ref: '#/components/schemas/PaginationLinks'),
                    new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
                ]),
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
        ],
    )]
    public function index(IndexCustomerRequest $request): AnonymousResourceCollection
    {
        return CustomerResource::collection(
            $this->customers->paginate($request->options()),
        );
    }

    /**
     * POST /api/v1/customers
     */
    #[OA\Post(
        path: '/customers',
        operationId: 'createCustomer',
        summary: 'Create a customer',
        security: [['basicAuth' => []], ['bearerAuth' => []]],
        tags: ['Customers'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Acme Holdings'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'acme@moneyflex.test'),
                    new OA\Property(property: 'phone', type: 'string', maxLength: 32, nullable: true, example: '+971500000001'),
                    new OA\Property(property: 'address', type: 'string', maxLength: 255, nullable: true, example: '1 Sheikh Zayed Road, Dubai'),
                    new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive'], default: 'active'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Created. The Location header points at the new record.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Customer')]),
                headers: [new OA\Header(header: 'Location', description: 'URL of the created customer.', schema: new OA\Schema(type: 'string'))],
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
        ],
    )]
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = $this->customers->create($request->validated());

        return CustomerResource::make($customer)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', route('customers.show', $customer));
    }

    /**
     * GET /api/v1/customers/{customer}
     */
    #[OA\Get(
        path: '/customers/{customer}',
        operationId: 'showCustomer',
        summary: 'View a customer',
        security: [['basicAuth' => []], ['bearerAuth' => []]],
        tags: ['Customers'],
        parameters: [
            new OA\Parameter(name: 'customer', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The customer.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Customer')]),
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function show(Customer $customer): CustomerResource
    {
        return CustomerResource::make($customer->loadCount('services'));
    }

    /**
     * PUT|PATCH /api/v1/customers/{customer}
     */
    #[OA\Put(
        path: '/customers/{customer}',
        operationId: 'updateCustomer',
        summary: 'Update a customer',
        description: 'Every field is optional, so this serves both a full replacement and a partial update. PATCH behaves identically.',
        security: [['basicAuth' => []], ['bearerAuth' => []]],
        tags: ['Customers'],
        parameters: [
            new OA\Parameter(name: 'customer', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Acme Holdings Ltd'),
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'phone', type: 'string', maxLength: 32, nullable: true),
                new OA\Property(property: 'address', type: 'string', maxLength: 255, nullable: true),
                new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive'], example: 'inactive'),
            ]),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'The updated customer.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Customer')]),
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
        ],
    )]
    public function update(UpdateCustomerRequest $request, Customer $customer): CustomerResource
    {
        return CustomerResource::make(
            $this->customers->update($customer, $request->validated()),
        );
    }

    /**
     * DELETE /api/v1/customers/{customer}
     */
    #[OA\Delete(
        path: '/customers/{customer}',
        operationId: 'deleteCustomer',
        summary: 'Delete a customer',
        description: 'A soft delete. The customer\'s services are hidden along with them and reappear if the customer is restored.',
        security: [['basicAuth' => []], ['bearerAuth' => []]],
        tags: ['Customers'],
        parameters: [
            new OA\Parameter(name: 'customer', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Deleted. No content is returned.'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function destroy(Customer $customer): Response
    {
        $this->customers->delete($customer);

        return response()->noContent();
    }
}
