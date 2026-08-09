<?php

declare(strict_types=1);

use App\Exceptions\ApiExceptionRenderer;
use App\Models\Customer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

function apiRequest(string $uri = '/api/v1/customers'): Request
{
    return Request::create($uri, 'GET');
}

it('leaves requests outside the api alone', function (): void {
    // Web routes keep the framework's own error pages.
    expect(ApiExceptionRenderer::handle(new NotFoundHttpException, Request::create('/', 'GET')))->toBeNull();
});

it('defers validation errors to the framework', function (): void {
    // Laravel's own body is already the documented shape.
    $exception = ValidationException::withMessages(['email' => 'required']);

    expect(ApiExceptionRenderer::handle($exception, apiRequest()))->toBeNull();
});

it('answers 401 for an authentication failure', function (): void {
    $response = ApiExceptionRenderer::handle(new AuthenticationException, apiRequest());

    expect($response?->getStatusCode())->toBe(401)
        ->and($response?->getData(true))->toBe(['message' => 'Unauthenticated.']);
});

it('answers 403 for an authorisation failure', function (): void {
    $response = ApiExceptionRenderer::handle(new AuthorizationException, apiRequest());

    expect($response?->getStatusCode())->toBe(403);
});

it('answers 404 for a missing model without naming the class', function (): void {
    // The default message would disclose the internal model name.
    $exception = (new ModelNotFoundException)->setModel(Customer::class, [1]);

    $response = ApiExceptionRenderer::handle($exception, apiRequest());

    expect($response?->getStatusCode())->toBe(404)
        ->and($response?->getData(true))->toBe(['message' => 'The requested resource was not found.'])
        ->and(json_encode($response?->getData()))->not->toContain('Customer');
});

it('answers 404 for a missing route', function (): void {
    $response = ApiExceptionRenderer::handle(new NotFoundHttpException, apiRequest());

    expect($response?->getStatusCode())->toBe(404);
});

it('keeps the status of any other http exception', function (): void {
    $response = ApiExceptionRenderer::handle(new HttpException(429, 'Slow down.'), apiRequest());

    expect($response?->getStatusCode())->toBe(429)
        ->and($response?->getData(true)['message'])->toBe('Slow down.');
});

it('hides the detail of an unexpected failure in production', function (): void {
    config()->set('app.debug', false);

    $response = ApiExceptionRenderer::handle(new RuntimeException('Database password is hunter2'), apiRequest());
    $body = json_encode($response?->getData());

    expect($response?->getStatusCode())->toBe(500)
        ->and($response?->getData(true))->toBe(['message' => 'Server error.'])
        ->and($body)->not->toContain('hunter2')
        ->and($body)->not->toContain('.php');
});

it('shows the detail of an unexpected failure while debugging', function (): void {
    config()->set('app.debug', true);

    $response = ApiExceptionRenderer::handle(new RuntimeException('Something broke'), apiRequest());

    expect($response?->getStatusCode())->toBe(500)
        ->and($response?->getData(true))->toHaveKeys(['message', 'exception', 'file', 'line'])
        ->and($response?->getData(true)['message'])->toBe('Something broke');
});
