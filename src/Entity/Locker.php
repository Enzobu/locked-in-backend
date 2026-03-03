<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Enum\LockerStatus;
use App\Repository\LockerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LockerRepository::class)]
#[ApiResource]
#[ORM\Table(uniqueConstraints: [
    new ORM\UniqueConstraint(name: 'uniq_locker_bay_number', columns: ['locker_bay_id', 'number']),
    new ORM\UniqueConstraint(name: 'uniq_locker_hardware_id', columns: ['hardware_id']),
])]
#[ORM\HasLifecycleCallbacks]
class Locker
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $number = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $hardwareId = null;

    #[ORM\ManyToOne(inversedBy: 'lockers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Specification $specification = null;

    #[ORM\Column]
    private ?int $priceCents = null;

    #[ORM\ManyToOne(inversedBy: 'lockers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?LockerBay $lockerBay = null;

    #[ORM\Column(length: 50, enumType: LockerStatus::class)]
    private LockerStatus $status = LockerStatus::AVAILABLE;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSeenAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, Reservation>
     */
    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'locker')]
    private Collection $reservations;

    /**
     * @var Collection<int, LockerAction>
     */
    #[ORM\OneToMany(targetEntity: LockerAction::class, mappedBy: 'locker')]
    private Collection $actions;

    /**
     * @var Collection<int, LockerEvent>
     */
    #[ORM\OneToMany(targetEntity: LockerEvent::class, mappedBy: 'locker')]
    private Collection $events;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->reservations = new ArrayCollection();
        $this->actions = new ArrayCollection();
        $this->events = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumber(): ?int
    {
        return $this->number;
    }

    public function setNumber(int $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getSpecification(): ?Specification
    {
        return $this->specification;
    }

    public function setSpecification(?Specification $specification): static
    {
        $this->specification = $specification;

        return $this;
    }

    public function getPriceCents(): ?int
    {
        return $this->priceCents;
    }

    public function setPriceCents(int $priceCents): static
    {
        $this->priceCents = $priceCents;

        return $this;
    }

    public function getPrice(): ?int
    {
        return $this->priceCents;
    }

    public function setPrice(int $price): static
    {
        $this->priceCents = $price;

        return $this;
    }

    public function getLockerBay(): ?LockerBay
    {
        return $this->lockerBay;
    }

    public function setLockerBay(?LockerBay $lockerBay): static
    {
        $this->lockerBay = $lockerBay;

        return $this;
    }

    public function getStatus(): LockerStatus
    {
        return $this->status;
    }

    public function setStatus(LockerStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getHardwareId(): ?string
    {
        return $this->hardwareId;
    }

    public function setHardwareId(?string $hardwareId): static
    {
        $this->hardwareId = $hardwareId;

        return $this;
    }

    public function getLastSeenAt(): ?\DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function setLastSeenAt(?\DateTimeImmutable $lastSeenAt): static
    {
        $this->lastSeenAt = $lastSeenAt;

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

    /**
     * @return Collection<int, Reservation>
     */
    public function getReservations(): Collection
    {
        return $this->reservations;
    }

    public function addReservation(Reservation $reservation): static
    {
        if (!$this->reservations->contains($reservation)) {
            $this->reservations->add($reservation);
            $reservation->setLocker($this);
        }

        return $this;
    }

    public function removeReservation(Reservation $reservation): static
    {
        $this->reservations->removeElement($reservation);

        return $this;
    }

    /**
     * @return Collection<int, LockerAction>
     */
    public function getActions(): Collection
    {
        return $this->actions;
    }

    /**
     * @return Collection<int, LockerEvent>
     */
    public function getEvents(): Collection
    {
        return $this->events;
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
