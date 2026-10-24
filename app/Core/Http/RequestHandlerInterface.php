<?php

declare(strict_types=1);

namespace App\Core\Http;

interface RequestHandlerInterface
{
    public function handle(Request $request): Response;
}
