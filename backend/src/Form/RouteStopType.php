<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RouteStopType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('address', TextType::class, [
                'label' => 'Direccion',
                'required' => true,
                'attr' => [
                    'class' => 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm',
                    'placeholder' => 'Direccion de la parada',
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
            ->add('recipientName', TextType::class, [
                'label' => 'Destinatario',
                'required' => false,
                'attr' => [
                    'class' => 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm',
                    'placeholder' => 'Nombre del destinatario',
                ],
            ])
            ->add('recipientPhone', TextType::class, [
                'label' => 'Telefono',
                'required' => false,
                'attr' => [
                    'class' => 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm',
                    'placeholder' => '+34 600 000 000',
                ],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notas',
                'required' => false,
                'attr' => [
                    'class' => 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm',
                    'placeholder' => 'Notas adicionales',
                    'rows' => 3,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
