<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Customer;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DriverType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'class' => 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm',
                    'placeholder' => 'conductor@ejemplo.com',
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Contrasena',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm',
                    'placeholder' => 'Dejar vacio para no cambiar',
                    'autocomplete' => 'new-password',
                ],
            ])
            ->add('customer', EntityType::class, [
                'label' => 'Almacen',
                'class' => Customer::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => '-- Sin almacen --',
                'attr' => [
                    'class' => 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm',
                ],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Activo',
                'required' => false,
                'getter' => static fn (User $user): bool => $user->isActive(),
                'setter' => static function (User $user, bool $value): void { $user->setActive($value); },
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
