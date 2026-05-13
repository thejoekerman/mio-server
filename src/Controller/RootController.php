<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/', name: 'app_root', methods: ['GET'])]
class RootController extends AbstractController
{
    public function __invoke(): JsonResponse
    {
        return $this->json([
            'name' => 'MioLog API',
            'status' => 'ok',
        ]);
    }
}
