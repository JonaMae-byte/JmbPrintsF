<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'Please enter a username.']),
                ],
            ])
            ->add('email', EmailType::class, [
                'required' => true,
                'attr' => ['autocomplete' => 'email'],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter an email address.']),
                    new Email(['message' => 'Please enter a valid email address.']),
                ],
            ])
            ->add('roles', ChoiceType::class, [
                'property_path' => 'assignableRoles',
                'choices' => [
                    'Admin' => 'ROLE_ADMIN',
                    'Staff' => 'ROLE_STAFF',
                ],
                'multiple' => true,
                'expanded' => true,
                'constraints' => [
                    new Count([
                        'min' => 1,
                        'minMessage' => 'Please select at least one role.',
                    ]),
                ],
            ])

            ->add('password', PasswordType::class, [
                'required' => $options['password_required'],
                'mapped' => $options['password_required'], // Do NOT update password when editing
                'empty_data' => '',
                'constraints' => $options['password_required']
                    ? [new NotBlank(['message' => 'Please enter a password.'])]
                    : [],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'password_required' => true, // default for "new user"
        ]);

        // IMPORTANT!!
        $resolver->setDefined(['password_required']);
    }
}
