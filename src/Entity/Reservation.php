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
