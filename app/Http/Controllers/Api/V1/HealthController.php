<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * A liveness probe. Deliberately unauthenticated so container orchestration
 * and uptime checks can reach it without credentials.
 */
class HealthController extends Controller
{
    #[OA\Get(
        path: '/health',
        operationId: 'health',
        summary: 'Liveness check',
        description: 'Open endpoint, no credentials required.',
        security: [],
        tags: ['System'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The service is up.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'ok'),
                    new OA\Property(property: 'service', type: 'string', example: 'MoneyFlex'),
                    new OA\Property(property: 'time', type: 'string', format: 'date-time'),
                ]),
            ),
        ],
    )]
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => config('app.name'),
            'time' => now()->toIso8601String(),
        ]);
    }
}
