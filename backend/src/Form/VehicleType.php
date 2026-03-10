<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Vehicle;
use App\Enum\VehicleSkill;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VehicleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nombre',
                'empty_data' => '',
                'attr' => [
                    'class' => 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm',
                    'placeholder' => 'Nombre del vehiculo',
                ],
            ])
            ->add('traccarDeviceId', IntegerType::class, [
                'label' => 'Traccar Device ID',
                'required' => false,
                'attr' => [
                    'class' => 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm',
                    'placeholder' => 'ID del dispositivo en Traccar',
                ],
            ])
            ->add('maxWeightKg', NumberType::class, [
                'label' => 'Peso maximo (kg)',
                'required' => false,
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'class' => 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm',
                    'placeholder' => 'Ej: 1000',
                    'min' => 0,
                    'step' => '0.01',
                ],
            ])
            ->add('maxVolumeM3', NumberType::class, [
                'label' => 'Volumen maximo (m3)',
                'required' => false,
                'scale' => 4,
                'html5' => true,
                'attr' => [
                    'class' => 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm',
                    'placeholder' => 'Ej: 12.5',
                    'min' => 0,
                    'step' => '0.0001',
                ],
            ])
            ->add('maxParcels', IntegerType::class, [
                'label' => 'Maximo de paquetes',
                'required' => false,
                'attr' => [
                    'class' => 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm',
                    'placeholder' => 'Ej: 50',
                    'min' => 0,
                ],
            ])
            ->add('skills', ChoiceType::class, [
                'label' => 'Habilidades del vehiculo',
                'required' => false,
                'multiple' => true,
                'expanded' => true,
                'choices' => array_combine(
                    array_map(static fn (VehicleSkill $s): string => $s->label(), VehicleSkill::cases()),
                    VehicleSkill::cases(),
                ),
                'choice_value' => static fn (?VehicleSkill $skill): string => $skill ? (string) $skill->value : '',
                'getter' => static function (Vehicle $vehicle): array {
                    $raw = $vehicle->getSkills();
                    if ($raw === null) {
                        return [];
                    }

                    return array_filter(array_map(
                        static fn (int $v): ?VehicleSkill => VehicleSkill::tryFrom($v),
                        $raw,
                    ));
                },
                'setter' => static function (Vehicle $vehicle, ?array $skills): void {
                    $vehicle->setSkills(
                        $skills !== null
                            ? array_map(static fn (VehicleSkill $s): int => $s->value, $skills)
                            : null,
                    );
                },
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Activo',
                'required' => false,
                'getter' => static fn (Vehicle $vehicle): bool => $vehicle->isActive(),
                'setter' => static function (Vehicle $vehicle, bool $value): void { $vehicle->setActive($value); },
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Vehicle::class,
        ]);
    }
}
