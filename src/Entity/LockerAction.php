<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Enum\LockerActionStatus;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ApiResource]
class LockerAction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'actions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Locker $locker = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(length: 50, enumType: LockerActionStatus::class)]
    private LockerActionStatus $status = LockerActionStatus::PENDING;

    #[ORM\ManyToOne]
    private ?Reservation $reservation = null;

    #[ORM\ManyToOne]
    private ?User $requestedByUser = null;

    #[ORM\ManyToOne]
    private ?Customer $requestedByCustomer = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $requestedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    public function __construct()
    {
        $this->requestedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getStatus(): LockerActionStatus
    {
        return $this->status;
    }

    public function setStatus(LockerActionStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getReservation(): ?Reservation
    {
        return $this->reservation;
    }

    public function setReservation(?Reservation $reservation): static
    {
        $this->reservation = $reservation;

        return $this;
    }

    public function getRequestedByUser(): ?User
    {
        return $this->requestedByUser;
    }

    public function setRequestedByUser(?User $requestedByUser): static
    {
        $this->requestedByUser = $requestedByUser;

        return $this;
    }

    public function getRequestedByCustomer(): ?Customer
    {
        return $this->requestedByCustomer;
    }

    public function setRequestedByCustomer(?Customer $requestedByCustomer): static
    {
        $this->requestedByCustomer = $requestedByCustomer;

        return $this;
    }

    public function getRequestedAt(): ?\DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function setRequestedAt(\DateTimeImmutable $requestedAt): static
    {
        $this->requestedAt = $requestedAt;

        return $this;
    }

    public function getProcessedAt(): ?\DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function setProcessedAt(?\DateTimeImmutable $processedAt): static
    {
        $this->processedAt = $processedAt;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }
}
