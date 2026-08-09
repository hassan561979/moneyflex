<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Service\IndexServiceRequest;
use App\Http\Requests\Service\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Services\ServiceService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Services addressed on their own. Creating one requires an owner, so that
 * lives on the nested customer route instead.
 */
class ServiceController extends Controller
{
    public function __construct(private readonly ServiceService $services) {}

    /**
     * GET /api/v1/services
     */
    public function index(IndexServiceRequest $request): AnonymousResourceCollection
    {
        return ServiceResource::collection(
            $this->services->paginate(
                $request->options(),
                $request->integer('customer_id') ?: null,
            ),
        );
    }

    /**
     * GET /api/v1/services/{service}
     */
    public function show(Service $service): ServiceResource
    {
        return ServiceResource::make($service->load('customer'));
    }

    /**
     * PUT|PATCH /api/v1/services/{service}
     */
    public function update(UpdateServiceRequest $request, Service $service): ServiceResource
    {
        return ServiceResource::make(
            $this->services->update($service, $request->validated()),
        );
    }

    /**
     * DELETE /api/v1/services/{service}
     */
    public function destroy(Service $service): Response
    {
        $this->services->delete($service);

        return response()->noContent();
    }
}
