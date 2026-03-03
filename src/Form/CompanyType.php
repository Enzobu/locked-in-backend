<?php

namespace App\Form;

use App\Entity\Company;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CompanyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'attr' => ['class' => 'form-control'],
            ])
            ->add('siret', TextType::class, [
                'attr' => ['class' => 'form-control'],
            ])
            ->add('siren', TextType::class, [
                'attr' => ['class' => 'form-control'],
            ])
            ->add('ape', TextType::class, [
                'attr' => ['class' => 'form-control'],
            ])
            ->add('juridicForm', TextType::class, [
                'attr' => ['class' => 'form-control'],
            ])
            ->add('phone', TextType::class, [
                'attr' => ['class' => 'form-control'],
            ])
            ->add('address', AddressType::class, [
                'label' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Company::class,
        ]);
    }
}
