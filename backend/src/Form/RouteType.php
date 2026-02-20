<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Customer;
use App\Entity\CustomerLocation;
use App\Entity\Route;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Enum\RouteStatus;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RouteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nombre',
                'required' => true,
                'attr' => [
                    'class' => 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm',
                    'placeholder' => 'Nombre de la ruta',
                ],
            ])
            ->add('customer', EntityType::class, [
                'class' => Customer::class,
                'choice_label' => 'name',
                'required' => false,
                'label' => 'Cliente',
                'placeholder' => 'Seleccionar cliente...',
                'attr' => [
                    'class' => 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm',
                ],
            ])
            ->add('originLocation', EntityType::class, [
                'class' => CustomerLocation::class,
                'choice_label' => 'name',
                'required' => false,
                'label' => 'Ubicacion de origen',
                'placeholder' => 'Seleccionar origen...',
                'query_builder' => function (EntityRepository $er): QueryBuilder {
                    return $er->createQueryBuilder('l')
                        ->where('l.isActive = true')
                        ->orderBy('l.name', 'ASC');
                },
                'attr' => [
                    'class' => 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm',
                ],
            ])
            ->add('vehicle', EntityType::class, [
                'class' => Vehicle::class,
                'choice_label' => 'name',
                'required' => false,
                'label' => 'Vehiculo',
                'placeholder' => 'Seleccionar vehiculo...',
                'query_builder' => function (EntityRepository $er): QueryBuilder {
                    return $er->createQueryBuilder('v')
                        ->where('v.isActive = true')
                        ->orderBy('v.name', 'ASC');
                },
                'attr' => [
                    'class' => 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm',
                ],
            ])
            ->add('driver', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'email',
                'required' => false,
                'label' => 'Transportista',
                'placeholder' => 'Seleccionar transportista...',
                'query_builder' => function (EntityRepository $er): QueryBuilder {
                    return $er->createQueryBuilder('u')
                        ->where('JSON_TEXT(u.roles) LIKE :r')
                        ->setParameter('r', '%ROLE_DRIVER%')
                        ->andWhere('u.isActive = true')
                        ->orderBy('u.email', 'ASC');
                },
                'attr' => [
                    'class' => 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm',
                ],
            ])
            ->add('status', EnumType::class, [
                'class' => RouteStatus::class,
                'label' => 'Estado',
                'attr' => [
                    'class' => 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Route::class,
        ]);
    }
}
