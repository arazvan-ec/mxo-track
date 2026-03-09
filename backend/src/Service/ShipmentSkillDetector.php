<?php

declare(strict_types=1);

namespace App\Service;

use App\Ai\LlmClientInterface;
use App\Ai\LlmRequest;
use App\Entity\Shipment;
use App\Enum\VehicleSkill;
use Psr\Log\LoggerInterface;

/**
 * Detects required vehicle skills from a shipment description using Claude AI.
 *
 * When importing shipments via CSV, requiredSkills is often empty. This service
 * analyzes the description field to infer skills (e.g., "Nevera médica" -> REFRIGERATED).
 */
final class ShipmentSkillDetector
{
    private const HEAVY_LOAD_THRESHOLD_KG = 50.0;

    public function __construct(
        private readonly LlmClientInterface $llmClient,
        private readonly RateLimitedApiClient $rateLimitedApiClient,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Detect required vehicle skills from a shipment description.
     *
     * @return list<VehicleSkill>
     */
    public function detectSkills(string $description, ?float $weightKg = null): array
    {
        $description = trim($description);
        if ($description === '') {
            // Even with no description, heavy weight alone can trigger HEAVY_LOAD
            if ($weightKg !== null && $weightKg > self::HEAVY_LOAD_THRESHOLD_KG) {
                return [VehicleSkill::HEAVY_LOAD];
            }

            return [];
        }

        $systemPrompt = <<<'PROMPT'
Eres un asistente de logística. Tu tarea es identificar qué habilidades especiales de vehículo se requieren para transportar un envío, basándote en su descripción y peso.

Las habilidades posibles son:
- REFRIGERATED (1): mercancía que necesita temperatura controlada (alimentos perecederos, medicamentos refrigerados, neveras médicas, vacunas, etc.)
- HEAVY_LOAD (2): carga pesada que requiere vehículo con capacidad especial (muebles grandes, maquinaria, paquetes > 50kg)
- PEDESTRIAN_ACCESS (3): entrega en zona peatonal o de acceso restringido a vehículos (casco antiguo, zona centro, mercado, etc.)
- HAZMAT (4): materiales peligrosos (productos químicos, inflamables, corrosivos, baterías de litio grandes, etc.)
- FRAGILE (5): mercancía frágil que requiere manejo especial (cristal, electrónica delicada, obras de arte, cerámica, etc.)

Responde SOLO con los nombres de habilidad aplicables, separados por comas. Si no se requiere ninguna habilidad especial, responde "NONE".
No incluyas explicaciones ni texto adicional.
PROMPT;

        $weightInfo = $weightKg !== null ? sprintf(' Peso: %.1f kg.', $weightKg) : '';
        $userMessage = sprintf('Descripción del envío: "%s".%s', $description, $weightInfo);

        try {
            /** @var string $response */
            $response = $this->rateLimitedApiClient->call(
                fn () => $this->llmClient->complete(new LlmRequest($systemPrompt, $userMessage, maxTokens: 100))->content,
            );

            $skills = $this->parseSkillsResponse($response);

            // Also add HEAVY_LOAD if weight exceeds threshold (even if AI didn't catch it)
            if ($weightKg !== null && $weightKg > self::HEAVY_LOAD_THRESHOLD_KG) {
                if (!in_array(VehicleSkill::HEAVY_LOAD, $skills, true)) {
                    $skills[] = VehicleSkill::HEAVY_LOAD;
                }
            }

            return $skills;
        } catch (\Throwable $e) {
            $this->logger?->warning('Skill detection failed for description "{desc}": {error}', [
                'desc' => mb_substr($description, 0, 80),
                'error' => $e->getMessage(),
            ]);

            // Fallback: at least detect heavy load from weight
            if ($weightKg !== null && $weightKg > self::HEAVY_LOAD_THRESHOLD_KG) {
                return [VehicleSkill::HEAVY_LOAD];
            }

            return [];
        }
    }

    /**
     * Detect skills and apply them to a shipment (only if requiredSkills is empty).
     *
     * @return bool True if skills were detected and applied
     */
    public function detectAndApply(Shipment $shipment): bool
    {
        // Only apply if no skills are set yet and there's something to analyze
        if (count($shipment->getRequiredSkills()) > 0) {
            return false;
        }

        $description = $shipment->getDescription() ?? $shipment->getNotes() ?? '';
        $weightKg = $shipment->getTotalWeightKg();

        if (trim($description) === '' && $weightKg === null) {
            return false;
        }

        $skills = $this->detectSkills($description, $weightKg);

        if (count($skills) === 0) {
            return false;
        }

        $shipment->setRequiredSkills($skills);

        return true;
    }

    /**
     * Parse Claude's response into VehicleSkill enum values.
     *
     * @return list<VehicleSkill>
     */
    private function parseSkillsResponse(string $response): array
    {
        $response = trim($response);

        if ($response === '' || strtoupper($response) === 'NONE') {
            return [];
        }

        $skills = [];
        $nameToSkill = [
            'REFRIGERATED' => VehicleSkill::REFRIGERATED,
            'HEAVY_LOAD' => VehicleSkill::HEAVY_LOAD,
            'PEDESTRIAN_ACCESS' => VehicleSkill::PEDESTRIAN_ACCESS,
            'HAZMAT' => VehicleSkill::HAZMAT,
            'FRAGILE' => VehicleSkill::FRAGILE,
        ];

        $parts = preg_split('/[\s,]+/', strtoupper($response));

        foreach ($parts as $part) {
            $part = trim($part);
            $skill = $nameToSkill[$part] ?? null;
            if ($skill !== null && !in_array($skill, $skills, true)) {
                $skills[] = $skill;
            }
        }

        return $skills;
    }
}
