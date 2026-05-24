<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Bootstrap;
use App\Core\Request;
use App\Core\Response;

class HealthController
{
    public function index(Request $request): void
    {
        Response::json([
            'app' => Bootstrap::config('name'),
            'status' => 'ok',
            'sms_driver' => Bootstrap::config('sms.driver'),
            'min_age' => Bootstrap::config('min_age'),
        ]);
    }
}
