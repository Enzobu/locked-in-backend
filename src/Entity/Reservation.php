<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Enum\ReservationStatus;
use App\Repository\ReservationRepository;
use App\State\ReservationPostProcessor;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ApiResource(operations: [
    new Get(security: "object.getCustomer() == user"),
    new GetCollection(security: "is_granted('ROLE_CUSTOMER')"),
    new Post(processor: ReservationPostProcessor::class),
    new Patch(security: "object.getCustomer() == user"),
    new Delete(security: "object.getCustomer() == user"),
])]
#[ORM\HasLifecycleCallbacks]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\ManyToOne(inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false)]
    #[ApiProperty(writable: false)]
    private ?Customer $customer = null;

    #[ORM\ManyToOne(inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Locker $locker = null;

    #[ORM\Column(length: 50, enumType: ReservationStatus::class)]
    private ReservationStatus $status = ReservationStatus::PENDING;

    #[ORM\Column]
    private int $plannedAmountCents = 0;

    #[ORM\Column(length: 3)]
    private string $currency = 'eur';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $paymentIntentId = null;

    #[ORM\Column(length: 50)]
    private string $paymentStatus = 'unpaid';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $actualEndsAt = null;

    #[ORM\Column]
    private int $overtimeMinutes = 0;

    #[ORM\Column]
    private int $overtimeAmountCents = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $overtimePaymentIntentId = null;

    #[ORM\Column(length: 50)]
    private string $overtimePaymentStatus = 'none';

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStartsAt(): ?\DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(\DateTimeImmutable $startsAt): static
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(\DateTimeImmutable $endsAt): static
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->startsAt = $date;

        return $this;
    }

    public function getDuration(): ?int
    {
        if ($this->startsAt === null || $this->endsAt === null) {
            return null;
        }

        return intdiv($this->endsAt->getTimestamp() - $this->startsAt->getTimestamp(), 60);
    }

    public function setDuration(int $duration): static
    {
        if ($this->startsAt !== null) {
            $this->endsAt = $this->startsAt->modify(sprintf('+%d minutes', $duration));
        }

        return $this;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): static
    {
        $this->customer = $customer;

        return $this;
    }

    public function getLocker(): ?Locker
    {
        return $this->locker;
    }

    public function setLocker(?Locker $locker): static
    {
        $this->locker = $locker;

        return $this;
    }

    public function getStatus(): ReservationStatus
    {
        return $this->status;
    }

    public function setStatus(ReservationStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPlannedAmountCents(): int
    {
        return $this->plannedAmountCents;
    }

    public function setPlannedAmountCents(int $plannedAmountCents): static
    {
        $this->plannedAmountCents = $plannedAmountCents;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = strtolower(trim($currency));

        return $this;
    }

    public function getPaymentIntentId(): ?string
    {
        return $this->paymentIntentId;
    }

    public function setPaymentIntentId(?string $paymentIntentId): static
    {
        $this->paymentIntentId = $paymentIntentId;

        return $this;
    }

    public function getPaymentStatus(): string
    {
        return $this->paymentStatus;
    }

    public function setPaymentStatus(string $paymentStatus): static
    {
        $this->paymentStatus = strtolower(trim($paymentStatus));

        return $this;
    }

    public function getActualEndsAt(): ?\DateTimeImmutable
    {
        return $this->actualEndsAt;
    }

    public function setActualEndsAt(?\DateTimeImmutable $actualEndsAt): static
    {
        $this->actualEndsAt = $actualEndsAt;

        return $this;
    }

    public function getOvertimeMinutes(): int
    {
        return $this->overtimeMinutes;
    }

    public function setOvertimeMinutes(int $overtimeMinutes): static
    {
        $this->overtimeMinutes = max(0, $overtimeMinutes);

        return $this;
    }

    public function getOvertimeAmountCents(): int
    {
        return $this->overtimeAmountCents;
    }

    public function setOvertimeAmountCents(int $overtimeAmountCents): static
    {
        $this->overtimeAmountCents = max(0, $overtimeAmountCents);

        return $this;
    }

    public function getOvertimePaymentIntentId(): ?string
    {
        return $this->overtimePaymentIntentId;
    }

    public function setOvertimePaymentIntentId(?string $overtimePaymentIntentId): static
    {
        $this->overtimePaymentIntentId = $overtimePaymentIntentId;

        return $this;
    }

    public function getOvertimePaymentStatus(): string
    {
        return $this->overtimePaymentStatus;
    }

    public function setOvertimePaymentStatus(string $overtimePaymentStatus): static
    {
        $this->overtimePaymentStatus = strtolower(trim($overtimePaymentStatus));

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
