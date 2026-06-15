<?php

declare(strict_types=1);

namespace App\Modules\AuditLog\Enums;

use Shared\Enums\EnumHelper;

/**
 * Canonical list of action strings the AuditEventSubscriber writes.
 * Used by the viewer page to render the action filter dropdown.
 * Adding a new listener? Add the action here so it shows in the filter.
 */
enum AuditAction: string
{
    use EnumHelper;

    case AuthLoginSuccess = 'auth.login.success';
    case AuthLoginFailed = 'auth.login.failed';
    case CustomerCreated = 'customer.created';
    case CustomerUpdated = 'customer.updated';
    case DocumentDeleted = 'document.deleted';
    case DocumentDownloaded = 'document.downloaded';
    case DocumentViewUrlIssued = 'document.view_url_issued';
    case InvitationConsumed = 'invitation.consumed';
    case InvitationIssued = 'invitation.issued';
    case InvitationRevoked = 'invitation.revoked';
    case MemberRemoved = 'member.removed';
    case MemberRoleChanged = 'member.role_changed';
    case UploadLinkGenerated = 'upload_link.generated';
    case UploadLinkRevoked = 'upload_link.revoked';

    /**
     * Human-readable label for the viewer page filter dropdown + table.
     * Keep these phrases compatible with a non-technical audit reader
     * ("Document deleted", not "DocumentDeleted" or "document.deleted").
     */
    public function label(): string
    {
        return match ($this) {
            self::AuthLoginSuccess => __('Login (success)'),
            self::AuthLoginFailed => __('Login (failed)'),
            self::CustomerCreated => __('Customer created'),
            self::CustomerUpdated => __('Customer updated'),
            self::DocumentDeleted => __('Document deleted'),
            self::DocumentDownloaded => __('Document downloaded'),
            self::DocumentViewUrlIssued => __('Document viewed'),
            self::InvitationConsumed => __('Invitation accepted'),
            self::InvitationIssued => __('Invitation sent'),
            self::InvitationRevoked => __('Invitation revoked'),
            self::MemberRemoved => __('Member removed'),
            self::MemberRoleChanged => __('Member role changed'),
            self::UploadLinkGenerated => __('Upload link generated'),
            self::UploadLinkRevoked => __('Upload link revoked'),
        };
    }

    /**
     * Tone for the action badge — destructive vs informational vs auth.
     */
    public function tone(): string
    {
        return match ($this) {
            self::DocumentDeleted, self::MemberRemoved, self::InvitationRevoked, self::UploadLinkRevoked, self::AuthLoginFailed => 'red',
            self::CustomerCreated, self::InvitationIssued, self::InvitationConsumed, self::UploadLinkGenerated, self::AuthLoginSuccess => 'green',
            self::MemberRoleChanged => 'amber',
            default => 'zinc',
        };
    }

    public static function tryLabel(string $value): string
    {
        return self::tryFrom($value)?->label() ?? $value;
    }

    public static function tryTone(string $value): string
    {
        return self::tryFrom($value)?->tone() ?? 'zinc';
    }
}
