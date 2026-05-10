<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\TransactionReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: TransactionReportRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF')"),
        new Get(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF')"),
    ],
    normalizationContext: ['groups' => ['tx:read', 'stock:read', 'product:read', 'category:read']],
)]
class TransactionReport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['tx:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['tx:read'])]
    private ?string $title = null;

    #[ORM\ManyToOne(targetEntity: Stocks::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['tx:read'])]
    private ?Stocks $stock = null;

    #[ORM\Column(length: 255)]
    #[Groups(['tx:read'])]
    private ?string $actionType = null;

    #[ORM\Column]
    #[Groups(['tx:read'])]
    private ?int $quantity = null;

    #[ORM\Column]
    #[Groups(['tx:read'])]
    private ?\DateTime $transactionDate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['tx:read'])]
    private ?string $notes = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['tx:read'])]
    private ?string $changedBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getStock(): ?Stocks
    {
        return $this->stock;
    }

    public function setStock(?Stocks $stock): static
    {
        $this->stock = $stock;

        return $this;
    }

    public function getActionType(): ?string
    {
        return $this->actionType;
    }

    public function setActionType(string $actionType): static
    {
        $this->actionType = $actionType;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getTransactionDate(): ?\DateTime
    {
        return $this->transactionDate;
    }

    public function setTransactionDate(\DateTime $transactionDate): static
    {
        $this->transactionDate = $transactionDate;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getChangedBy(): ?string
    {
        return $this->changedBy;
    }

    public function setChangedBy(?string $changedBy): static
    {
        $this->changedBy = $changedBy;

        return $this;
    }
}
