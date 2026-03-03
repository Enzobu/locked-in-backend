<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\SpecificationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: SpecificationRepository::class)]
#[ApiResource]
#[ORM\Table(uniqueConstraints: [
    new ORM\UniqueConstraint(name: 'uniq_specification_name', columns: ['name']),
])]
class Specification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['locker:read'])]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(['locker:read'])]
    private ?int $width = null;

    #[ORM\Column]
    #[Groups(['locker:read'])]
    private ?int $height = null;

    #[ORM\Column]
    #[Groups(['locker:read'])]
    private ?int $depth = null;

    #[ORM\Column(length: 255)]
    #[Groups(['locker:read'])]
    private ?string $material = null;

    #[ORM\Column(length: 255)]
    #[Groups(['locker:read'])]
    private ?string $name = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['locker:read'])]
    private bool $isRechargeable = false;

    /**
     * @var Collection<int, Locker>
     */
    #[ORM\OneToMany(targetEntity: Locker::class, mappedBy: 'specification')]
    private Collection $lockers;

    public function __construct()
    {
        $this->lockers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function setWidth(int $width): static
    {
        $this->width = $width;

        return $this;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setHeight(int $height): static
    {
        $this->height = $height;

        return $this;
    }

    public function getDepth(): ?int
    {
        return $this->depth;
    }

    public function setDepth(int $depth): static
    {
        $this->depth = $depth;

        return $this;
    }

    public function getMaterial(): ?string
    {
        return $this->material;
    }

    public function setMaterial(string $material): static
    {
        $this->material = $material;

        return $this;
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

    public function isRechargeable(): ?bool
    {
        return $this->isRechargeable;
    }

    public function setIsRechargeable(bool $isRechargeable): static
    {
        $this->isRechargeable = $isRechargeable;

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
            $locker->setSpecification($this);
        }

        return $this;
    }

    public function removeLocker(Locker $locker): static
    {
        if ($this->lockers->removeElement($locker)) {
            // set the owning side to null (unless already changed)
            if ($locker->getSpecification() === $this) {
                $locker->setSpecification(null);
            }
        }

        return $this;
    }
}
