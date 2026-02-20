<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CsvImportRun;
use App\Entity\Customer;
use App\Service\ShipmentCsvImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

#[Route('/admin/shipments')]
#[IsGranted('ROLE_OPERATOR')]
class AdminShipmentController extends AbstractController
{
    #[Route('/import', name: 'admin_shipments_import', methods: ['GET', 'POST'])]
    public function import(
        Request $request,
        EntityManagerInterface $entityManager,
        ShipmentCsvImporter $importer,
    ): Response {
        $customers = $entityManager->getRepository(Customer::class)->findAll();
        $result = null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('import-shipments', $request->request->getString('_token'))) {
                $this->addFlash('error', 'Token CSRF invalido.');

                return $this->redirectToRoute('admin_shipments_import');
            }

            $customerPublicId = $request->request->getString('customer_id');
            $csv = $request->files->get('csv_file');

            try {
                $customer = $entityManager->getRepository(Customer::class)->findOneBy(['publicId' => Ulid::fromString($customerPublicId)]);
            } catch (\InvalidArgumentException) {
                $customer = null;
            }
            if (!$customer instanceof Customer || $csv === null) {
                $this->addFlash('error', 'Debes seleccionar un cliente y un archivo CSV.');

                return $this->redirectToRoute('admin_shipments_import');
            }

            $result = $importer->import($csv->getPathname(), $customer);

            if ($result['created'] > 0) {
                $this->addFlash(
                    'success',
                    sprintf('%d envio(s) creado(s) correctamente.', $result['created']),
                );
            }

            if ($result['skipped'] > 0) {
                $this->addFlash(
                    'warning',
                    sprintf('%d fila(s) omitida(s) (referencia duplicada).', $result['skipped']),
                );
            }

            if ($result['errors'] > 0) {
                $this->addFlash(
                    'error',
                    sprintf('%d fila(s) con error (referencia vacia o formato invalido).', $result['errors']),
                );
            }

            if ($result['created'] === 0 && $result['skipped'] === 0 && $result['errors'] === 0) {
                $this->addFlash('warning', 'El archivo CSV esta vacio o solo contiene la cabecera.');
            }

            return $this->redirectToRoute('admin_shipments_import');
        }

        $runs = $entityManager->getRepository(CsvImportRun::class)->findBy(
            [],
            ['createdAt' => 'DESC'],
            10,
        );

        return $this->render('admin/shipments_import.html.twig', [
            'customers' => $customers,
            'runs' => $runs,
        ]);
    }
}
