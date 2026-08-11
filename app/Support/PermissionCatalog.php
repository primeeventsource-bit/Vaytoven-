<?php

namespace App\Support;

/**
 * The permission catalog: every granular capability the backend recognises,
 * grouped by admin-portal module. This is the ALLOW-LIST — RoleController
 * rejects grants for keys not defined here, the seeder syncs it into the
 * `permissions` table, and the role editor renders from it. Adding a key here
 * surfaces it everywhere automatically, which is what keeps new modules from
 * requiring an RBAC rewrite.
 *
 * Key format is `<module>.<action>`. Modules mirror the admin portal nav order
 * so the role editor groups sensibly:
 *
 *   Users & Roles -> Members -> Properties -> Resorts -> Media
 *   -> Billing -> Reports -> Settings
 *
 * Conventions:
 *   - `view` is the read gate for a module. A role without it should not see
 *     the module in the nav at all.
 *   - `manage` is a coarse catch-all for modules that have no meaningful
 *     split yet; prefer explicit verbs when a module grows.
 *   - Super admins bypass every check (see AppServiceProvider's Gate::before),
 *     so no permission here can lock a super admin out.
 */
final class PermissionCatalog
{
    /** Module key => display label, in admin-portal nav order. */
    public const MODULES = [
        'users' => 'Users',
        'roles' => 'Roles & Permissions',
        'members' => 'Members',
        'properties' => 'Properties',
        'offers' => 'Inquiries & Offers',
        'resorts' => 'Vacation Club Resorts',
        'media' => 'Media Library',
        'billing' => 'Subscriptions & Billing',
        'reports' => 'Reports',
        'contracts' => 'Contracts',
        'inbox' => 'Contact & Support Requests',
        'content' => 'Site Content',
        'settings' => 'Settings',
        'audit' => 'Activity Log',
    ];

    /**
     * Permission key => [module, label, description].
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    public const PERMISSIONS = [
        // --- Users -----------------------------------------------------
        'users.view' => ['users', 'View Users', 'See the backend user list and individual user profiles.'],
        'users.create' => ['users', 'Create Users', 'Create new backend and customer accounts.'],
        'users.edit' => ['users', 'Edit Users', 'Change a user\'s name, email, and profile details.'],
        'users.deactivate' => ['users', 'Activate / Deactivate Users', 'Disable or restore account access.'],
        'users.reset_password' => ['users', 'Reset Passwords', 'Set a new password on another user\'s account.'],
        'users.assign_roles' => ['users', 'Assign Roles', 'Attach or detach roles on a user. Privileged.'],

        // --- Roles -----------------------------------------------------
        'roles.view' => ['roles', 'View Roles', 'See roles and the permissions attached to each.'],
        'roles.create' => ['roles', 'Create Roles', 'Define new custom roles.'],
        'roles.edit' => ['roles', 'Edit Roles', 'Rename roles and change their granted permissions.'],
        'roles.delete' => ['roles', 'Delete Roles', 'Remove a custom role. System roles can never be deleted.'],

        // --- Members ---------------------------------------------------
        'members.view' => ['members', 'View Members', 'See member accounts and their membership details.'],
        'members.create' => ['members', 'Create Members', 'Create member accounts from the backend.'],
        'members.edit' => ['members', 'Edit Members', 'Update member profiles and membership details.'],
        'members.assign_plan' => ['members', 'Assign Membership Plans', 'Attach or change a member\'s plan or tier.'],

        // --- Properties ------------------------------------------------
        'properties.view' => ['properties', 'View Properties', 'See property and resort listings in the backend.'],
        'properties.create' => ['properties', 'Add Properties', 'Create new property listings.'],
        'properties.edit' => ['properties', 'Edit Properties', 'Change descriptions, amenities, location, and pricing.'],
        'properties.publish' => ['properties', 'Publish / Unpublish Properties', 'Control whether a listing is publicly visible.'],
        'properties.delete' => ['properties', 'Delete Properties', 'Remove a listing.'],
        'properties.availability' => ['properties', 'Manage Availability', 'Edit availability calendars and blackout dates.'],

        // --- Inquiries & offers ----------------------------------------
        'offers.view' => ['offers', 'View All Inquiries & Offers', 'See every buyer inquiry and offer across the platform, including buyer, owner, amount, IP, and expiry.'],
        'offers.respond' => ['offers', 'Respond to Offers', 'Accept or decline an offer on behalf of a listing owner.'],

        // --- Vacation Club resorts -------------------------------------
        'resorts.view' => ['resorts', 'View Resorts', 'See Vacation Club resorts.'],
        'resorts.create' => ['resorts', 'Add Resorts', 'Create Vacation Club resorts.'],
        'resorts.edit' => ['resorts', 'Edit Resorts', 'Update resort details, destinations, and amenities.'],
        'resorts.delete' => ['resorts', 'Delete Resorts', 'Remove a resort.'],

        // --- Media -----------------------------------------------------
        'media.view' => ['media', 'View Media Library', 'Browse uploaded images and documents.'],
        'media.upload' => ['media', 'Upload Images', 'Add new images, documents, and marketing graphics.'],
        'media.edit' => ['media', 'Edit Media', 'Change captions, ordering, and the featured image.'],
        'media.delete' => ['media', 'Delete Media', 'Remove files from the library.'],

        // --- Billing ---------------------------------------------------
        'billing.view' => ['billing', 'View Billing', 'See subscriptions, charges, and refunds.'],
        'billing.manage' => ['billing', 'Manage Billing', 'Issue refunds and change subscription state.'],
        'billing.processors' => ['billing', 'Manage Payment Processors', 'Edit gateway credentials and routing. Highly privileged.'],
        'billing.service_fees' => ['billing', 'Manage Service Fees', 'Set the host and guest Vaytoven Service Fee rates and structures. Changes affect every new booking.'],

        // --- Reports ---------------------------------------------------
        'reports.view' => ['reports', 'View Reports', 'See operational and financial reporting.'],
        'reports.export' => ['reports', 'Export Reports', 'Download report data.'],

        // --- Contracts -------------------------------------------------
        'contracts.view' => ['contracts', 'View Contracts', 'See contracts and download signed copies.'],
        'contracts.send' => ['contracts', 'Send Contracts', 'Issue a new contract for signature.'],
        'contracts.void' => ['contracts', 'Void Contracts', 'Cancel an outstanding contract.'],

        // --- Inbox: what the public forms produce ----------------------
        'inbox.view' => ['inbox', 'View Contact & Support Requests', 'Read messages from /contact, Trip Support tickets, and job applications.'],
        'inbox.manage' => ['inbox', 'Work Requests', 'Mark requests handled, assign them, and record outcomes.'],

        // --- Site content: what the public pages render ----------------
        'content.view' => ['content', 'View Site Content', 'See careers, press releases, and help articles in the console.'],
        'content.edit' => ['content', 'Edit Site Content', 'Write and update careers, press releases, and help articles.'],
        'content.publish' => ['content', 'Publish Site Content', 'Make content publicly visible, or take it down.'],
        'content.delete' => ['content', 'Delete Site Content', 'Remove a content record entirely.'],

        // --- Settings --------------------------------------------------
        'settings.view' => ['settings', 'View Settings', 'See the settings console.'],
        'settings.edit' => ['settings', 'Edit Settings', 'Change configuration values and feature flags.'],

        // --- Activity log ----------------------------------------------
        'audit.view' => ['audit', 'View Activity Log', 'See who changed what across the backend.'],
    ];

    /** @return list<string> Every permission key. */
    public static function keys(): array
    {
        return array_keys(self::PERMISSIONS);
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::PERMISSIONS);
    }

    /** @return list<string> Permission keys belonging to one module. */
    public static function forModule(string $module): array
    {
        return array_keys(array_filter(
            self::PERMISSIONS,
            fn (array $meta) => $meta[0] === $module,
        ));
    }

    /**
     * Catalog grouped for the role editor: module key => label => permissions.
     *
     * @return array<string, array{label: string, permissions: array<string, array{label: string, description: string}>}>
     */
    public static function grouped(): array
    {
        $grouped = [];

        foreach (self::MODULES as $module => $label) {
            $grouped[$module] = ['label' => $label, 'permissions' => []];
        }

        foreach (self::PERMISSIONS as $key => [$module, $label, $description]) {
            $grouped[$module]['permissions'][$key] = [
                'label' => $label,
                'description' => $description,
            ];
        }

        return $grouped;
    }
}
