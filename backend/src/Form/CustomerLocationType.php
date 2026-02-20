<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CustomerLocationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nombre',
                'required' => true,
                'attr' => [
                    'class' => 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm',
                    'placeholder' => 'Ej: Almacen Madrid',
                ],
            ])
            ->add('address', TextType::class, [
                'label' => 'Direccion',
                'required' => true,
                'attr' => [
                    'class' => 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm',
                    'placeholder' => 'Direccion completa',
                ],
            ])
            ->add('latitude', NumberType::class, [
                'label' => 'Latitud',
                'required' => false,
                'scale' => 6,
                'attr' => [
                    'class' => 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm',
                    'placeholder' => '40.416775',
                    'step' => '0.000001',
                ],
            ])
            ->add('longitude', NumberType::class, [
                'label' => 'Longitud',
                'required' => false,
                'scale' => 6,
                'attr' => [
                    'class' => 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm',
                    'placeholder' => '-3.703790',
                    'step' => '0.000001',
                ],
            ])
            ->add('isDefault', CheckboxType::class, [
                'label' => 'Ubicacion por defecto',
                'required' => false,
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Activa',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
