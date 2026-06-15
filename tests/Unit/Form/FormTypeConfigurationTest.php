<?php

namespace App\Tests\Unit\Form;

use App\Entity\Address;
use App\Entity\Company;
use App\Entity\Customer;
use App\Entity\Locker;
use App\Entity\LockerBay;
use App\Entity\User;
use App\Form\AddressType;
use App\Form\CompanyType;
use App\Form\CustomerType;
use App\Form\LockerBayType;
use App\Form\LockerType;
use App\Form\UserType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FormTypeConfigurationTest extends TestCase
{
    public function testAddressTypeBuildsFieldsAndOptions(): void
    {
        $type = new AddressType();
        $type->buildForm($this->builderExpectingAdds(5), []);

        $options = $this->resolveOptions($type);
        self::assertSame(Address::class, $options['data_class']);
    }

    public function testCompanyTypeBuildsFieldsAndOptions(): void
    {
        $type = new CompanyType();
        $type->buildForm($this->builderExpectingAdds(7), []);

        $options = $this->resolveOptions($type);
        self::assertSame(Company::class, $options['data_class']);
    }

    public function testCustomerTypeBuildsCreateAndEditOptions(): void
    {
        $type = new CustomerType();
        $type->buildForm($this->builderExpectingAdds(6), ['is_create' => true]);

        $options = $this->resolveOptions($type, ['is_create' => true]);
        self::assertSame(Customer::class, $options['data_class']);
        self::assertTrue($options['is_create']);

        $options = $this->resolveOptions($type);
        self::assertFalse($options['is_create']);
    }

    public function testUserTypeBuildsCreateAndEditOptions(): void
    {
        $type = new UserType();
        $type->buildForm($this->builderExpectingAdds(6), ['is_create' => true]);

        $options = $this->resolveOptions($type, ['is_create' => true]);
        self::assertSame(User::class, $options['data_class']);
        self::assertTrue($options['is_create']);

        $options = $this->resolveOptions($type);
        self::assertFalse($options['is_create']);
    }

    public function testLockerTypeBuildsFieldsAndOptions(): void
    {
        $type = new LockerType();
        $type->buildForm($this->builderExpectingAdds(6), []);

        $options = $this->resolveOptions($type);
        self::assertSame(Locker::class, $options['data_class']);
    }

    public function testLockerBayTypeBuildsFieldsAndOptions(): void
    {
        $type = new LockerBayType();
        $type->buildForm($this->builderExpectingAdds(6), []);

        $options = $this->resolveOptions($type);
        self::assertSame(LockerBay::class, $options['data_class']);
    }

    /**
     * @return FormBuilderInterface&MockObject
     */
    private function builderExpectingAdds(int $count): FormBuilderInterface
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder
            ->expects(self::exactly($count))
            ->method('add')
            ->willReturnSelf();

        return $builder;
    }

    /**
     * @param object{configureOptions(OptionsResolver): void} $type
     * @param array<string, mixed> $provided
     * @return array<string, mixed>
     */
    private function resolveOptions(object $type, array $provided = []): array
    {
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        return $resolver->resolve($provided);
    }
}
