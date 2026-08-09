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
    public function index(IndexServiceRequest $request, Customer $customer): JsonResponse
    {
        return response()->json(
            $this->services->cachedIndex($request->options(), $customer->id),
        );
    }

    /**
     * POST /api/v1/customers/{customer}/services
     */
    public function store(StoreServiceRequest $request, Customer $customer): JsonResponse
    {
        $service = $this->services->createForCustomer($customer, $request->validated());

        return ServiceResource::make($service)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', route('services.show', $service));
    }
}
