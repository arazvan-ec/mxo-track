<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CsvImportRun;
use App\Entity\Customer;
use App\Service\ShipmentCsvImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/shipments')]
class AdminShipmentController extends AbstractController
{
    #[Route('/import', name: 'admin_shipments_import', methods: ['GET', 'POST'])]
    public function import(
        Request $request,
        EntityManagerInterface $entityManager,
        ShipmentCsvImporter $importer,
    ): Response {
        $customers = $entityManager->getRepository(Customer::class)->findAll();

        if ($request->isMethod('POST')) {
            $customerId = (string) $request->request->get('customer_id');
            $csv = $request->files->get('csv_file');

            $customer = $entityManager->find(Customer::class, $customerId);
            if (!$customer instanceof Customer || $csv === null) {
                $this->addFlash('error', 'Debes seleccionar cliente y archivo CSV.');
                return new RedirectResponse('/admin/shipments/import');
            }

            $result = $importer->import($csv->getPathname(), $customer);
            $this->addFlash('success', sprintf('Importación completada: creados=%d, omitidos=%d', $result['created'], $result['skipped']));

            return new RedirectResponse('/admin/shipments/import');
        }

        $runs = $entityManager->getRepository(CsvImportRun::class)->findBy([], ['createdAt' => 'DESC'], 10);

        return $this->render('admin/shipments_import.html.twig', [
            'customers' => $customers,
            'runs' => $runs,
        ]);
    }
}
