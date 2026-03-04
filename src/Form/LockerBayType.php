<?php

namespace App\Form;

use App\Entity\LockerBay;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LockerBayType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'attr' => ['class' => 'form-control'],
            ])
            ->add('latitude', TextType::class, [
                'attr' => ['class' => 'form-control'],
            ])
            ->add('longitude', TextType::class, [
                'attr' => ['class' => 'form-control'],
            ])
            ->add('minDuration', IntegerType::class, [
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('maxDuration', IntegerType::class, [
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('overtimeSurchargePercent', IntegerType::class, [
                'required' => true,
                'empty_data' => '0',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                ],
                'label' => 'Majoration dépassement (%)',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LockerBay::class,
        ]);
    }
}
