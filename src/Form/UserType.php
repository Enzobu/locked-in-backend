<?php

namespace App\Form;

use App\Entity\Company;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstname', TextType::class, [
                'attr' => ['class' => 'form-control'],
            ])
            ->add('lastname', TextType::class, [
                'attr' => ['class' => 'form-control'],
            ])
            ->add('email', null, [
                'attr' => ['class' => 'form-control'],
            ])
            ->add('company', EntityType::class, [
                'class' => Company::class,
                'choice_label' => 'name',
                'placeholder' => 'Selectionner une societe',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('roles', ChoiceType::class, [
                'choices' => [
                    'Administrateur' => 'ROLE_ADMIN',
                    'Operateur' => 'ROLE_OPERATOR',
                ],
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'required' => $options['is_create'],
                'label' => $options['is_create'] ? 'Mot de passe' : 'Nouveau mot de passe',
                'empty_data' => '',
                'attr' => ['class' => 'form-control'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_create' => false,
        ]);

        $resolver->setAllowedTypes('is_create', 'bool');
    }
}
