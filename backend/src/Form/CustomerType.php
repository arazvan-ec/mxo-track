<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Customer;
use App\Enum\ClientFrequency;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CustomerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Customer|null $customer */
        $customer = $options['data'] ?? null;

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nombre',
                'required' => true,
                'empty_data' => '',
                'attr' => [
                    'class' => 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm',
                    'placeholder' => 'Nombre del cliente',
                ],
            ])
            ->add('address', TextType::class, [
                'label' => 'Direccion',
                'required' => false,
                'attr' => [
                    'class' => 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm',
                    'placeholder' => 'Direccion del cliente',
                ],
            ])
            ->add('contactPhone', TextType::class, [
                'label' => 'Telefono',
                'required' => false,
                'attr' => [
                    'class' => 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm',
                    'placeholder' => '+34 600 000 000',
                ],
            ])
            ->add('frequency', EnumType::class, [
                'class' => ClientFrequency::class,
                'label' => 'Frecuencia',
                'required' => false,
                'placeholder' => 'Seleccionar frecuencia',
                'choice_label' => static fn (ClientFrequency $f): string => $f->label(),
                'attr' => [
                    'class' => 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm',
                ],
            ])
            ->add('preferredDeliverySlot', TextType::class, [
                'label' => 'Horario de entrega preferido',
                'required' => false,
                'attr' => [
                    'class' => 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm',
                    'placeholder' => 'ej. 9:00-14:00',
                ],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Activo',
                'required' => false,
                'data' => $customer?->getId() !== null ? $customer->isActive() : true,
                'getter' => static fn (Customer $c): bool => $c->isActive(),
                'setter' => static function (Customer $c, bool $value): void { $c->setActive($value); },
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Customer::class,
        ]);
    }
}
