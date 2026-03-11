<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Customer;
use App\Entity\CustomerIntegration;
use App\Provider\Gps\GpsProviderType;
use App\Provider\Realtime\RealtimeProviderType;
use App\Provider\RouteOptimizer\RouteOptimizerProvider;
use App\Provider\Routing\RoutingProvider;
use App\Provider\ServiceType;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CustomerIntegrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];
        $inputClass = 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm';

        if (!$isEdit) {
            $builder
                ->add('customer', EntityType::class, [
                    'class' => Customer::class,
                    'choice_label' => 'name',
                    'label' => 'Cliente',
                    'mapped' => false,
                    'query_builder' => static fn (EntityRepository $er) => $er->createQueryBuilder('c')->orderBy('c.name', 'ASC'),
                    'attr' => ['class' => $inputClass],
                ])
                ->add('serviceType', ChoiceType::class, [
                    'label' => 'Tipo de servicio',
                    'mapped' => false,
                    'choices' => array_combine(
                        array_map(static fn (ServiceType $s): string => $s->name, ServiceType::cases()),
                        array_map(static fn (ServiceType $s): string => $s->value, ServiceType::cases()),
                    ),
                    'attr' => ['class' => $inputClass],
                ])
                ->add('providerType', ChoiceType::class, [
                    'label' => 'Proveedor',
                    'mapped' => false,
                    'choices' => self::allProviderChoices(),
                    'attr' => ['class' => $inputClass],
                ]);
        }

        $builder
            ->add('configJson', TextareaType::class, [
                'label' => 'Configuracion (JSON)',
                'mapped' => false,
                'required' => false,
                'data' => $options['config_json'],
                'attr' => [
                    'class' => $inputClass . ' font-mono',
                    'rows' => 6,
                    'placeholder' => '{"key": "value"}',
                ],
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'Activo',
                'required' => false,
                'getter' => static fn (CustomerIntegration $ci): bool => $ci->isEnabled(),
                'setter' => static function (CustomerIntegration $ci, bool $value): void { $ci->setEnabled($value); },
                'attr' => ['class' => 'h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500'],
            ])
            ->add('priority', IntegerType::class, [
                'label' => 'Prioridad (0 = primario)',
                'getter' => static fn (CustomerIntegration $ci): int => $ci->getPriority(),
                'setter' => static function (CustomerIntegration $ci, ?int $value): void { $ci->setPriority($value ?? 0); },
                'attr' => [
                    'class' => $inputClass,
                    'min' => 0,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CustomerIntegration::class,
            'is_edit' => false,
            'config_json' => '{}',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private static function allProviderChoices(): array
    {
        $choices = [];
        foreach (RouteOptimizerProvider::cases() as $p) {
            $choices["Optimizador: {$p->name}"] = $p->value;
        }
        foreach (RoutingProvider::cases() as $p) {
            $choices["Routing: {$p->name}"] = $p->value;
        }
        foreach (GpsProviderType::cases() as $p) {
            $choices["GPS: {$p->name}"] = $p->value;
        }
        foreach (RealtimeProviderType::cases() as $p) {
            $choices["Realtime: {$p->name}"] = $p->value;
        }

        return $choices;
    }
}
