<?php

namespace App\Form;

use App\Entity\Locker;
use App\Entity\Specification;
use App\Enum\LockerStatus;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LockerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('number', IntegerType::class, [
                'attr' => ['class' => 'form-control'],
            ])
            ->add('hardwareId', TextType::class, [
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('specification', EntityType::class, [
                'class' => Specification::class,
                'choice_label' => 'name',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('priceCents', IntegerType::class, [
                'attr' => ['class' => 'form-control'],
            ])
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'Disponible' => LockerStatus::AVAILABLE,
                    'Reserve' => LockerStatus::RESERVED,
                    'Occupe' => LockerStatus::OCCUPIED,
                    'Hors service' => LockerStatus::OUT_OF_ORDER,
                    'Offline' => LockerStatus::OFFLINE,
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('lastSeenAt', DateTimeType::class, [
                'required' => false,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Locker::class,
        ]);
    }
}
