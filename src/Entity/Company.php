<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\CompanyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: CompanyRepository::class)]
#[ApiResource]
#[ORM\Table(uniqueConstraints: [
    new ORM\UniqueConstraint(name: 'uniq_company_siret', columns: ['siret']),
    new ORM\UniqueConstraint(name: 'uniq_company_siren', columns: ['siren']),
])]
#[ORM\HasLifecycleCallbacks]
class Company
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['locker_bay:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['locker_bay:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $siret = null;

    #[ORM\Column(length: 255)]
    #[Groups(['locker_bay:read'])]
    private ?string $siren = null;

    #[ORM\Column(length: 255)]
    private ?string $ape = null;

    #[ORM\ManyToOne(inversedBy: 'companies', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['locker_bay:read'])]
    private ?Address $address = null;

    #[ORM\Column(length: 255)]
    private ?string $juridicForm = null;

    #[ORM\Column(length: 50)]
    private ?string $phone = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'company', orphanRemoval: true)]
    private Collection $users;

    /**
     * @var Collection<int, LockerBay>
     */
    #[ORM\OneToMany(targetEntity: LockerBay::class, mappedBy: 'company')]
    private Collection $lockerBays;

    public function __construct()
    {
        $this->users = new ArrayCollection();
        $this->lockerBays = new ArrayCollection();
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

    public function getSiret(): ?string
    {
        return $this->siret;
    }

    public function setSiret(string $siret): static
    {
        $this->siret = $siret;

        return $this;
    }

    public function getSiren(): ?string
    {
        return $this->siren;
    }

    public function setSiren(string $siren): static
    {
        $this->siren = $siren;

        return $this;
    }

    public function getApe(): ?string
    {
        return $this->ape;
    }

    public function setApe(string $ape): static
    {
        $this->ape = $ape;

        return $this;
    }

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function setAddress(?Address $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getJuridicForm(): ?string
    {
        return $this->juridicForm;
    }

    public function setJuridicForm(string $juridicForm): static
    {
        $this->juridicForm = $juridicForm;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->setCompany($this);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        $this->users->removeElement($user);

        return $this;
    }

    /**
     * @return Collection<int, LockerBay>
     */
    public function getLockerBays(): Collection
    {
        return $this->lockerBays;
    }

    public function addLockerBay(LockerBay $lockerBay): static
    {
        if (!$this->lockerBays->contains($lockerBay)) {
            $this->lockerBays->add($lockerBay);
            $lockerBay->setCompany($this);
        }

        return $this;
    }

    public function removeLockerBay(LockerBay $lockerBay): static
    {
        $this->lockerBays->removeElement($lockerBay);

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
