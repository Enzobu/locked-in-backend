<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Enum\LockerStatus;
use App\Repository\LockerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: LockerRepository::class)]
#[ApiResource(normalizationContext: ['groups' => ['locker:read']])]
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
    #[Groups(['locker:read'])]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(['locker:read'])]
    private ?int $number = null;

    #[ORM\Column(length: 64, nullable: true)]
    #[Groups(['locker:read'])]
    private ?string $hardwareId = null;

    #[ORM\ManyToOne(inversedBy: 'lockers')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['locker:read'])]
    private ?Specification $specification = null;

    #[ORM\Column]
    #[Groups(['locker:read'])]
    private ?int $priceCents = null;

    #[ORM\ManyToOne(inversedBy: 'lockers')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['locker:read'])]
    private ?LockerBay $lockerBay = null;

    #[ORM\Column(length: 50, enumType: LockerStatus::class)]
    #[Groups(['locker:read'])]
    private LockerStatus $status = LockerStatus::AVAILABLE;

    #[ORM\Column(nullable: true)]
    #[Groups(['locker:read'])]
    private ?\DateTimeImmutable $lastSeenAt = null;

    #[ORM\Column]
    #[Groups(['locker:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['locker:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $deviceId = null;

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

    public function getDeviceId(): ?string
    {
        return $this->deviceId;
    }

    public function setDeviceId(?string $deviceId): self
    {
        $this->deviceId = $deviceId;

        return $this;
    }
}
