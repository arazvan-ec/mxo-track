<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\ShipmentEvent;
use App\Message\NlpClassificationMessage;
use App\Service\ExceptionClassifierService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class NlpClassificationHandler
{
    public function __construct(
        private ExceptionClassifierService $classifier,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(NlpClassificationMessage $message): void
    {
        $event = $this->em->find(ShipmentEvent::class, $message->shipmentEventId);

        if ($event === null) {
            $this->logger->info('ShipmentEvent not found for NLP classification, skipping.', [
                'shipmentEventId' => $message->shipmentEventId,
            ]);

            return;
        }

        $classification = $this->classifier->classify(
            $message->exceptionNotes,
            $message->exceptionCode,
        );

        $payload = $event->getPayload();
        $payload['ai_classification'] = $classification;
        $event->setPayload($payload);

        $this->em->flush();

        $this->logger->info('NLP classification completed for ShipmentEvent.', [
            'shipmentEventId' => $message->shipmentEventId,
            'subcategory' => $classification['subcategory'],
            'confidence' => $classification['confidence'],
        ]);
    }
}
