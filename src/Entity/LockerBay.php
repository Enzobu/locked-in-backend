<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\LockerBayRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: LockerBayRepository::class)]
#[ApiResource(normalizationContext: ['groups' => ['locker_bay:read']])]
#[ORM\Table(uniqueConstraints: [
    new ORM\UniqueConstraint(name: 'uniq_company_locker_bay_name', columns: ['company_id', 'name']),
])]
#[ORM\HasLifecycleCallbacks]
class LockerBay
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['locker_bay:read', 'locker:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['locker_bay:read', 'locker:read'])]
    private ?string $name = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7)]
    #[Groups(['locker_bay:read', 'locker:read'])]
    private ?string $latitude = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7)]
    #[Groups(['locker_bay:read', 'locker:read'])]
    private ?string $longitude = null;

    #[ORM\ManyToOne(inversedBy: 'lockerBays')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['locker_bay:read'])]
    private ?Company $company = null;

    /**
     * @var Collection<int, Locker>
     */
    #[ORM\OneToMany(targetEntity: Locker::class, mappedBy: 'lockerBay')]
    #[Groups(['locker_bay:read'])]
    private Collection $lockers;

    #[ORM\Column(nullable: true)]
    #[Groups(['locker_bay:read'])]
    private ?int $maxDuration = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['locker_bay:read'])]
    private ?int $minDuration = null;

    #[ORM\Column]
    #[Groups(['locker_bay:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['locker_bay:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->lockers = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLatitude(string $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(string $longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): static
    {
        $this->company = $company;

        return $this;
    }

    /**
     * @return Collection<int, Locker>
     */
    public function getLockers(): Collection
    {
        return $this->lockers;
    }

    public function addLocker(Locker $locker): static
    {
        if (!$this->lockers->contains($locker)) {
            $this->lockers->add($locker);
            $locker->setLockerBay($this);
        }

        return $this;
    }

    public function removeLocker(Locker $locker): static
    {
        if ($this->lockers->removeElement($locker)) {
            // set the owning side to null (unless already changed)
            if ($locker->getLockerBay() === $this) {
                $locker->setLockerBay(null);
            }
        }

        return $this;
    }

    public function getMaxDuration(): ?int
    {
        return $this->maxDuration;
    }

    public function setMaxDuration(?int $maxDuration): static
    {
        $this->maxDuration = $maxDuration;

        return $this;
    }

    public function getMinDuration(): ?int
    {
        return $this->minDuration;
    }

    public function setMinDuration(?int $minDuration): static
    {
        $this->minDuration = $minDuration;

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
