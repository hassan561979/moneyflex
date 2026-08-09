<?php

declare(strict_types=1);

/*
 * Sanity checks the generated OpenAPI document.
 *
 * Swagger UI fails quietly: an unresolved $ref renders as an empty schema and
 * a missing security block renders as an endpoint that looks public. Both are
 * easy to miss by eye, so CI checks them here.
 *
 * Usage: php scripts/validate-openapi.php [path/to/api-docs.json]
 */

$path = $argv[1] ?? __DIR__.'/../storage/api-docs/api-docs.json';

if (! is_file($path)) {
    fwrite(STDERR, "No specification at {$path}. Run: php artisan l5-swagger:generate\n");
    exit(1);
}

$spec = json_decode((string) file_get_contents($path), true);

if (! is_array($spec)) {
    fwrite(STDERR, "The specification is not valid JSON.\n");
    exit(1);
}

$failures = [];

/*
 * Every $ref must point at something that exists in the document.
 */
$refsChecked = 0;
$walk = static function (mixed $node) use (&$walk, $spec, &$failures, &$refsChecked): void {
    if (! is_array($node)) {
        return;
    }

    foreach ($node as $key => $value) {
        if ($key === '$ref' && is_string($value)) {
            $refsChecked++;
            $cursor = $spec;

            foreach (explode('/', ltrim($value, '#/')) as $segment) {
                if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                    $failures[] = "unresolved reference: {$value}";

                    continue 2;
                }
                $cursor = $cursor[$segment];
            }
        }

        $walk($value);
    }
};
$walk($spec);

/*
 * Only the liveness check and login may be reachable without credentials.
 * Anything else missing a security block would be documented as public.
 */
$open = ['/health' => 'get', '/auth/login' => 'post'];
$operations = 0;

foreach ($spec['paths'] ?? [] as $route => $methods) {
    foreach ($methods as $method => $operation) {
        $operations++;
        $isOpen = ($open[$route] ?? null) === $method;
        $requiresAuth = ! empty($operation['security']);

        if ($isOpen && $requiresAuth) {
            $failures[] = strtoupper($method)." {$route} is documented as requiring credentials but is open";
        }

        if (! $isOpen && ! $requiresAuth) {
            $failures[] = strtoupper($method)." {$route} is documented as public but requires credentials";
        }
    }
}

/*
 * Both schemes the API accepts must be declared, or the Authorize button
 * cannot offer them.
 */
foreach (['basicAuth', 'bearerAuth'] as $scheme) {
    if (! isset($spec['components']['securitySchemes'][$scheme])) {
        $failures[] = "missing security scheme: {$scheme}";
    }
}

printf(
    "%d paths, %d operations, %d references checked\n",
    count($spec['paths'] ?? []),
    $operations,
    $refsChecked,
);

if ($failures !== []) {
    fwrite(STDERR, "\n".count($failures)." problem(s):\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "  - {$failure}\n");
    }
    exit(1);
}

echo "The specification is consistent.\n";
