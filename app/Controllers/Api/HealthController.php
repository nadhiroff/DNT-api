<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Services\HealthService;

final class HealthController
{
    public function __construct(private readonly HealthService $health)
    {
    }

    public function show(Request $request): Response
    {
        $result = $this->health->check();

        return Response::json($result, $result['status'] === 'fail' ? 503 : 200, ['Cache-Control' => 'no-store']);
    }
}
