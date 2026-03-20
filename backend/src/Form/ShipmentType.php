<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Customer;
use App\Domain\Shipment\Model\Shipment;
use App\Enum\ServiceType;
use App\Enum\ShipmentPriority;
use App\Enum\VehicleSkill;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ShipmentType extends AbstractType
{
    private const string INPUT_CLASS = 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('reference', TextType::class, [
                'label' => 'Referencia',
                'empty_data' => '',
                'attr' => [
                    'class' => self::INPUT_CLASS,
                    'placeholder' => 'Ej: SHP-001',
                ],
            ])
            ->add('customer', EntityType::class, [
                'label' => 'Cliente',
                'class' => Customer::class,
                'choice_label' => 'name',
                'query_builder' => static fn (EntityRepository $er) => $er->createQueryBuilder('c')->orderBy('c.name', 'ASC'),
                'attr' => ['class' => self::INPUT_CLASS],
            ])
            ->add('serviceType', EnumType::class, [
                'label' => 'Tipo de servicio',
                'class' => ServiceType::class,
                'choice_label' => static fn (ServiceType $t): string => $t->label(),
                'attr' => ['class' => self::INPUT_CLASS],
            ])
            ->add('priority', EnumType::class, [
                'label' => 'Prioridad',
                'class' => ShipmentPriority::class,
                'choice_label' => static fn (ShipmentPriority $p): string => $p->label(),
                'attr' => ['class' => self::INPUT_CLASS],
            ])
            ->add('recipientName', TextType::class, [
                'label' => 'Nombre destinatario',
                'required' => false,
                'attr' => [
                    'class' => self::INPUT_CLASS,
                    'placeholder' => 'Nombre completo',
                ],
            ])
            ->add('recipientPhone', TextType::class, [
                'label' => 'Telefono destinatario',
                'required' => false,
                'attr' => [
                    'class' => self::INPUT_CLASS,
                    'placeholder' => '+34612345678',
                ],
            ])
            ->add('address', TextType::class, [
                'label' => 'Direccion',
                'required' => false,
                'attr' => [
                    'class' => self::INPUT_CLASS,
                    'placeholder' => 'Calle, numero, ciudad',
                ],
            ])
            ->add('latitude', NumberType::class, [
                'label' => 'Latitud',
                'required' => false,
                'scale' => 6,
                'html5' => true,
                'attr' => [
                    'class' => self::INPUT_CLASS,
                    'placeholder' => 'Ej: 40.4168',
                    'step' => '0.000001',
                    'min' => -90,
                    'max' => 90,
                ],
            ])
            ->add('longitude', NumberType::class, [
                'label' => 'Longitud',
                'required' => false,
                'scale' => 6,
                'html5' => true,
                'attr' => [
                    'class' => self::INPUT_CLASS,
                    'placeholder' => 'Ej: -3.7038',
                    'step' => '0.000001',
                    'min' => -180,
                    'max' => 180,
                ],
            ])
            ->add('totalWeightKg', NumberType::class, [
                'label' => 'Peso total (kg)',
                'required' => false,
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'class' => self::INPUT_CLASS,
                    'placeholder' => 'Ej: 5.50',
                    'min' => 0,
                    'step' => '0.01',
                ],
            ])
            ->add('totalVolumeM3', NumberType::class, [
                'label' => 'Volumen total (m3)',
                'required' => false,
                'scale' => 4,
                'html5' => true,
                'attr' => [
                    'class' => self::INPUT_CLASS,
                    'placeholder' => 'Ej: 0.015',
                    'min' => 0,
                    'step' => '0.0001',
                ],
            ])
            ->add('totalParcels', IntegerType::class, [
                'label' => 'Numero de bultos',
                'attr' => [
                    'class' => self::INPUT_CLASS,
                    'placeholder' => '1',
                    'min' => 1,
                ],
            ])
            ->add('estimatedDeliveryDate', DateType::class, [
                'label' => 'Fecha estimada de entrega',
                'required' => false,
                'widget' => 'single_text',
                'attr' => ['class' => self::INPUT_CLASS],
            ])
            ->add('serviceTimeSeconds', IntegerType::class, [
                'label' => 'Tiempo de servicio (segundos)',
                'required' => false,
                'attr' => [
                    'class' => self::INPUT_CLASS,
                    'placeholder' => 'Ej: 300',
                    'min' => 0,
                ],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notas',
                'required' => false,
                'attr' => [
                    'class' => self::INPUT_CLASS,
                    'rows' => 3,
                    'placeholder' => 'Instrucciones especiales...',
                ],
            ])
            ->add('requiredSkills', ChoiceType::class, [
                'label' => 'Skills requeridos',
                'required' => false,
                'multiple' => true,
                'expanded' => true,
                'choices' => array_combine(
                    array_map(static fn (VehicleSkill $s): string => $s->label(), VehicleSkill::cases()),
                    VehicleSkill::cases(),
                ),
                'choice_value' => static fn (?VehicleSkill $skill): string => $skill ? (string) $skill->value : '',
                'getter' => static function (Shipment $shipment): array {
                    return $shipment->getRequiredSkills();
                },
                'setter' => static function (Shipment $shipment, ?array $skills): void {
                    $shipment->setRequiredSkills($skills ?? []);
                },
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Shipment::class,
        ]);
    }
}
