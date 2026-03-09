<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Customer;
use App\Repository\CustomerRepository;
use App\Service\AccountingExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

#[Route('/admin/billing/export')]
#[IsGranted('ROLE_OPERATOR')]
final class AccountingExportController extends AbstractController
{
    public function __construct(
        private readonly AccountingExportService $exportService,
    ) {}

    #[Route('/customer', name: 'admin_accounting_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $customerPublicId = $request->query->get('customer', '');
        $format = $request->query->get('format', 'csv');

        if ($customerPublicId === '' || $format !== 'csv') {
            $this->addFlash('error', 'Parámetros inválidos para la exportación.');

            return $this->redirectToRoute('admin_billing_index');
        }

        try {
            $ulid = Ulid::fromString($customerPublicId);
        } catch (\Throwable) {
            $this->addFlash('error', 'Identificador de cliente inválido.');

            return $this->redirectToRoute('admin_billing_index');
        }

        /** @var CustomerRepository $repo */
        $repo = $this->container->get('doctrine')->getRepository(Customer::class);
        $customer = $repo->findOneBy(['publicId' => $ulid]);

        if (!$customer instanceof Customer) {
            throw $this->createNotFoundException('Cliente no encontrado.');
        }

        $from = $request->query->get('from')
            ? new \DateTimeImmutable($request->query->get('from'))
            : new \DateTimeImmutable('first day of this month');
        $to = $request->query->get('to')
            ? new \DateTimeImmutable($request->query->get('to'))
            : new \DateTimeImmutable('today');

        $csvContent = $this->exportService->exportCsv($customer, $from, $to);
        $filename = sprintf(
            'accounting_%s_%s_%s.csv',
            preg_replace('/[^a-zA-Z0-9_-]/', '_', $customer->getName()),
            $from->format('Ymd'),
            $to->format('Ymd'),
        );

        return new StreamedResponse(
            function () use ($csvContent): void {
                echo $csvContent;
            },
            Response::HTTP_OK,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            ],
        );
    }
}
