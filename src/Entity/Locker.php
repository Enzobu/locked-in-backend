<?php

namespace App\Entity;

use App\Repository\LockerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LockerRepository::class)]
class Locker
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $number = null;

    #[ORM\ManyToOne(inversedBy: 'lockers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Specification $specification = null;

    #[ORM\Column]
    private ?int $price = null;

    #[ORM\ManyToOne(inversedBy: 'lockers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?LockerBay $lockerBay = null;

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

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(int $price): static
    {
        $this->price = $price;

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
}
