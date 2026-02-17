<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class FleetMapController extends AbstractController
{
    #[Route('/fleet/map', name: 'fleet_map', methods: ['GET'])]
    public function __invoke(#[Autowire('%env(MERCURE_PUBLIC_URL)%')] string $mercurePublicUrl): Response
    {
        return $this->render('tracking/map.html.twig', [
            'mercure_public_url' => $mercurePublicUrl,
        ]);
    }
}
