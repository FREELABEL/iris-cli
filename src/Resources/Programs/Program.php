<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Programs;

/**
 * Program Model
 *
 * Represents a membership program or funnel.
 */
class Program
{
    public int $id;
    public string $name;
    public ?string $slug;
    public ?string $description;
    public ?string $landingPageContent;
    public ?string $imageUrl;
    public bool $active;
    public ?string $tier;
    public ?int $bloqId;
    public ?string $mailjetListId;
    public ?array $providerSettings;
    public ?string $emailSubject;
    public ?string $emailPreheader;
    public ?string $emailBody;
    public ?string $emailBtnText;
    public ?string $emailBtnLink;
    public bool $hasPaidMembership;
    public bool $requiresMembership;
    public bool $allowFreeEnrollment;
    public ?float $basePrice;
    public ?array $membershipFeatures;
    public ?array $customFields;
    public ?array $enrollmentFormConfig;
    public ?int $defaultTemplateId;
    public ?int $welcomeTemplateId;
    public ?int $notificationTemplateId;
    public ?string $createdAt;
    public ?string $updatedAt;

    // Computed attributes
    public ?array $packages;
    public ?array $memberships;
    public ?array $enrollments;
    public ?float $startingPrice;
    public ?float $totalRevenue;
    public ?float $monthlyRevenue;

    protected array $attributes;

    public function __construct(array $data)
    {
        $this->attributes = $data;

        $this->id = (int) ($data['id'] ?? 0);
        $this->name = $data['name'] ?? '';
        $this->slug = $data['slug'] ?? null;
        $this->description = $data['description'] ?? null;
        $this->landingPageContent = $data['landing_page_content'] ?? null;
        $this->imageUrl = $data['image_url'] ?? null;
        $this->active = (bool) ($data['active'] ?? true);
        $this->tier = $data['tier'] ?? null;
        $this->bloqId = isset($data['bloq_id']) ? (int) $data['bloq_id'] : null;
        $this->mailjetListId = $data['mailjet_list_id'] ?? null;
        
        // Provider settings
        $this->providerSettings = isset($data['provider_settings']) && is_array($data['provider_settings'])
            ? $data['provider_settings']
            : null;

        // Email settings
        $this->emailSubject = $data['email_subject'] ?? null;
        $this->emailPreheader = $data['email_preheader'] ?? null;
        $this->emailBody = $data['email_body'] ?? null;
        $this->emailBtnText = $data['email_btn_text'] ?? null;
        $this->emailBtnLink = $data['email_btn_link'] ?? null;

        // Membership settings
        $this->hasPaidMembership = (bool) ($data['has_paid_membership'] ?? false);
        $this->requiresMembership = (bool) ($data['requires_membership'] ?? false);
        $this->allowFreeEnrollment = (bool) ($data['allow_free_enrollment'] ?? false);
        $this->basePrice = isset($data['base_price']) ? (float) $data['base_price'] : null;
        
        $this->membershipFeatures = isset($data['membership_features']) && is_array($data['membership_features'])
            ? $data['membership_features']
            : null;
        
        $this->customFields = isset($data['custom_fields']) && is_array($data['custom_fields'])
            ? $data['custom_fields']
            : null;
        
        $this->enrollmentFormConfig = isset($data['enrollment_form_config']) && is_array($data['enrollment_form_config'])
            ? $data['enrollment_form_config']
            : null;

        // Template IDs
        $this->defaultTemplateId = isset($data['default_template_id']) ? (int) $data['default_template_id'] : null;
        $this->welcomeTemplateId = isset($data['welcome_template_id']) ? (int) $data['welcome_template_id'] : null;
        $this->notificationTemplateId = isset($data['notification_template_id']) ? (int) $data['notification_template_id'] : null;

        // Timestamps
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;

        // Relationships and computed attributes
        $this->packages = $data['packages'] ?? null;
        $this->memberships = $data['memberships'] ?? null;
        $this->enrollments = $data['enrollments'] ?? null;
        $this->startingPrice = isset($data['starting_price']) ? (float) $data['starting_price'] : null;
        $this->totalRevenue = isset($data['total_revenue']) ? (float) $data['total_revenue'] : null;
        $this->monthlyRevenue = isset($data['monthly_revenue']) ? (float) $data['monthly_revenue'] : null;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Check if program is free.
     */
    public function isFree(): bool
    {
        return !$this->hasPaidMembership && $this->allowFreeEnrollment;
    }

    /**
     * Check if program has paid membership.
     */
    public function isPaid(): bool
    {
        return $this->hasPaidMembership;
    }

    /**
     * Check if program is active.
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Get the display price for the program.
     */
    public function getDisplayPrice(): ?string
    {
        if ($this->isFree()) {
            return 'Free';
        }

        if ($this->startingPrice !== null) {
            return '$' . number_format($this->startingPrice, 2);
        }

        if ($this->basePrice !== null) {
            return '$' . number_format($this->basePrice, 2);
        }

        return null;
    }

    /**
     * Check if program has packages.
     */
    public function hasPackages(): bool
    {
        return !empty($this->packages);
    }

    /**
     * Get the number of enrollments.
     */
    public function getEnrollmentCount(): int
    {
        return is_array($this->enrollments) ? count($this->enrollments) : 0;
    }

    /**
     * Get the number of active memberships.
     */
    public function getMembershipCount(): int
    {
        return is_array($this->memberships) ? count($this->memberships) : 0;
    }

    /**
     * Check if program has custom fields.
     */
    public function hasCustomFields(): bool
    {
        return !empty($this->customFields);
    }

    /**
     * Get a specific custom field value.
     */
    public function getCustomField(string $key, mixed $default = null): mixed
    {
        return $this->customFields[$key] ?? $default;
    }

    /**
     * Get the URL slug for the program.
     */
    public function getSlug(): string
    {
        return $this->slug ?? strtolower(str_replace(' ', '-', $this->name));
    }

    /**
     * Get the public URL for the program landing page/form.
     */
    public function getPublicUrl(): string
    {
        $baseUrl = 'https://heyiris.io/programs/';
        return $baseUrl . $this->getSlug();
    }

    /**
     * Alias for getEnrollmentCount.
     * Useful for form-focused programs.
     */
    public function getSubmissionsCount(): int
    {
        return $this->getEnrollmentCount();
    }
}
