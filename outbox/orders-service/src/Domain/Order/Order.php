<?php

declare(strict_types=1);

namespace App\Domain\Order;

class Order
{
    private ?int $id;
    private string $customerName;
    private string $customerEmail;
    private float $totalAmount;
    private string $status;
    private \DateTimeImmutable $createdAt;
    
    public function __construct(
        string $customerName,
        string $customerEmail,
        float $totalAmount,
        string $status = 'pending',
        ?\DateTimeImmutable $createdAt = null,
        ?int $id = null
    ) {
        $this->id = $id;
        $this->customerName = $customerName;
        $this->customerEmail = $customerEmail;
        $this->totalAmount = $totalAmount;
        $this->status = $status;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }
    
    public function getId(): ?int
    {
        return $this->id;
    }
    
    public function setId(int $id): void
    {
        $this->id = $id;
    }
    
    public function getCustomerName(): string
    {
        return $this->customerName;
    }
    
    public function getCustomerEmail(): string
    {
        return $this->customerEmail;
    }
    
    public function getTotalAmount(): float
    {
        return $this->totalAmount;
    }
    
    public function getStatus(): string
    {
        return $this->status;
    }
    
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
    
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'customer_name' => $this->customerName,
            'customer_email' => $this->customerEmail,
            'total_amount' => $this->totalAmount,
            'status' => $this->status,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s')
        ];
    }
}
