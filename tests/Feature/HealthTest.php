<?php

declare(strict_types=1);

it('reports that the service is up without credentials', function (): void {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonStructure(['status', 'service', 'time'])
        ->assertJsonPath('status', 'ok');
});
