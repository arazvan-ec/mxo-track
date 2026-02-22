<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class LocaleController extends AbstractController
{
    #[Route('/locale/{locale}', name: 'app_locale_switch', methods: ['POST'])]
    public function switchLocale(string $locale, Request $request): Response
    {
        $allowed = ['es', 'en'];

        if (!in_array($locale, $allowed, true)) {
            $locale = 'es';
        }

        $request->getSession()->set('_locale', $locale);

        $referer = $request->headers->get('referer');

        return $this->redirect($referer ?: '/');
    }
}
