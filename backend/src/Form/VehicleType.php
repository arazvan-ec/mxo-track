<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Vehicle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
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
                    'placeholder' => 'ej. 1500.00',
                    'step' => '0.01',
                    'min' => '0',
                ],
            ])
            ->add('maxVolumeM3', NumberType::class, [
                'label' => 'Volumen maximo (m³)',
                'required' => false,
                'scale' => 4,
                'html5' => true,
                'attr' => [
                    'class' => 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm',
                    'placeholder' => 'ej. 12.5000',
                    'step' => '0.0001',
                    'min' => '0',
                ],
            ])
            ->add('maxParcels', IntegerType::class, [
                'label' => 'Bultos maximos',
                'required' => false,
                'attr' => [
                    'class' => 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm',
                    'placeholder' => 'ej. 100',
                    'min' => '0',
                ],
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
