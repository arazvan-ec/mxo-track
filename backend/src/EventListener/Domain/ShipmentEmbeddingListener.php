<?php

declare(strict_types=1);

namespace App\EventListener\Domain;

use App\Domain\Event\ShipmentsImported;
use App\Domain\Shipment\Model\Shipment;
use App\Service\EmbeddingService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Indexes imported shipments as vector embeddings for semantic search.
 */
#[AsEventListener]
final readonly class ShipmentEmbeddingListener
{
    public function __construct(
        private EmbeddingService $embeddingService,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(ShipmentsImported $event): void
    {
        if ($event->createdCount === 0) {
            return;
        }

        // Find recently created shipments for this customer (from the last minute)
        $since = $event->occurredAt->modify('-1 minute');
        $shipments = $this->em->getRepository(Shipment::class)->createQueryBuilder('s')
            ->where('s.customer = :customer')
            ->andWhere('s.createdAt >= :since')
            ->setParameter('customer', $event->customerId)
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();

        $indexed = 0;
        foreach ($shipments as $shipment) {
            $text = $this->buildSearchableText($shipment);

            try {
                $this->embeddingService->embedAndStore('shipment', $shipment->getId(), $text);
                $indexed++;
            } catch (\Throwable $e) {
                $this->logger->warning('ShipmentEmbeddingListener: failed to index shipment {ref}: {error}', [
                    'ref' => $shipment->getReference(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('ShipmentEmbeddingListener: indexed {count} shipments from import run {run}', [
            'count' => $indexed,
            'run' => $event->importRunId,
        ]);
    }

    private function buildSearchableText(Shipment $shipment): string
    {
        $parts = [
            'Referencia: ' . $shipment->getReference(),
        ];

        if ($shipment->getRecipientName() !== null) {
            $parts[] = 'Destinatario: ' . $shipment->getRecipientName();
        }

        if ($shipment->getAddress() !== null) {
            $parts[] = 'Direccion: ' . $shipment->getAddress();
        }

        if ($shipment->getPhone() !== null) {
            $parts[] = 'Telefono: ' . $shipment->getPhone();
        }

        if ($shipment->getNotes() !== null && $shipment->getNotes() !== '') {
            $parts[] = 'Notas: ' . $shipment->getNotes();
        }

        return implode('. ', $parts);
    }
}
