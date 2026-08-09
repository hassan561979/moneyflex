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

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $customers) {}

    /**
     * GET /api/v1/customers
     */
    public function index(IndexCustomerRequest $request): AnonymousResourceCollection
    {
        return CustomerResource::collection(
            $this->customers->paginate($request->options()),
        );
    }

    /**
     * POST /api/v1/customers
     */
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
    public function show(Customer $customer): CustomerResource
    {
        return CustomerResource::make($customer->loadCount('services'));
    }

    /**
     * PUT|PATCH /api/v1/customers/{customer}
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): CustomerResource
    {
        return CustomerResource::make(
            $this->customers->update($customer, $request->validated()),
        );
    }

    /**
     * DELETE /api/v1/customers/{customer}
     */
    public function destroy(Customer $customer): Response
    {
        $this->customers->delete($customer);

        return response()->noContent();
    }
}
