<?php

namespace App\Form;

use App\Entity\Customer;
use App\Entity\Sepas;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Iban;

class SepasType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ('view' === $options['mode']) {
            $builder->add('RIB', TextType::class, [
                'label' => 'IBAN',
                'attr' => [
                    'placeholder' => 'FRXXXX107001XXXX345XXX901XX',
                ],
                'constraints' => [
                    new Iban(),
                ],
            ]);
            $builder->add('customer', CustomerType::class, [
                'mode' => 'sepa',
                'label' => false,
            ]);
        } else {
            $builder
                ->add('customer', EntityType::class, [
                    'class' => Customer::class,
                    'choice_label' => function ($customer) {
                        return $customer->getLastname() . ' ' . $customer->getFirstname() . ', ' . $customer->getZipcode() . ' ' . $customer->getCity();
                    },
                    'label' => 'Client',
                    'placeholder' => '-- Sélectionnez un client --',
                ])
                ->add('email', TextType::class, [
                    'label' => 'Adresse e-mail',
                ])
                ->add('amount', TextType::class, [
                    'label' => 'Montant',
                ]);

            if ('create' !== $options['mode']) {
                $builder->add('mandate')
                    ->add('status');
            }

        }

        $builder->add('submit', SubmitType::class, [
            'label' => 'Confirmer',
            'attr' => [
                'class' => 'btn btn-primary',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Sepas::class,
            'mode' => 'create',
        ]);
    }
}
