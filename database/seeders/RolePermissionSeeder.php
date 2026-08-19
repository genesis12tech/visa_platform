<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Translates the PRD §3.3 role capability matrix into Spatie roles and
 * permissions. Each capability-matrix row becomes one permission (naming
 * convention: Backend_schema.md §12.2, `<resource>.<action>`); a non-❌ cell
 * is a grant of that permission to the role, regardless of the scope word
 * in the cell ("Own", "Assigned", "Team", etc.) — scope is resolved by each
 * model's Policy once the model exists, not by proliferating permission
 * strings (§12.3). refund.initiate/refund.approve are split into two
 * permissions specifically to make the separation-of-duties rule (§3.3,
 * "no single role may both approve a refund and alter the application it
 * relates to") structurally impossible to collapse by accident.
 */
class RolePermissionSeeder extends Seeder
{
    private const GUARD = 'web';

    public function run(): void
    {
        $roles = $this->seedRoles();
        $permissions = $this->seedPermissions();
        $this->syncGrants($roles, $permissions);
    }

    /**
     * @return array<string, Role>
     */
    private function seedRoles(): array
    {
        $names = [
            'applicant', 'agent', 'case_officer', 'senior_officer', 'document_verifier',
            'finance_officer', 'support_staff', 'admin', 'super_admin',
        ];

        $out = [];
        foreach ($names as $name) {
            $out[$name] = Role::findOrCreate($name, self::GUARD);
        }

        return $out;
    }

    /**
     * @return array<string, Permission>
     */
    private function seedPermissions(): array
    {
        $names = [
            'application.create_draft',
            'application.view',
            'document.upload',
            'document.preview',
            'document.review',
            'application.request_info',
            'application.assign',
            'appointment.schedule',
            'application.approve',
            'application.override',
            'refund.initiate',
            'refund.approve',
            'config.manage',
            'user.manage',
            'report.view',
            'audit.view',
        ];

        $out = [];
        foreach ($names as $name) {
            $out[$name] = Permission::findOrCreate($name, self::GUARD);
        }

        return $out;
    }

    /**
     * @param  array<string, Role>  $roles
     * @param  array<string, Permission>  $permissions
     */
    private function syncGrants(array $roles, array $permissions): void
    {
        // PRD §3.3: one row per permission, listing every role whose cell is
        // not ❌ (the scope word in a granted cell — "Own", "Assigned",
        // "Team", "Payment data only", etc. — is a Policy-layer concern).
        $grants = [
            'application.create_draft' => ['applicant', 'agent', 'admin', 'super_admin'],
            'application.view' => ['applicant', 'agent', 'case_officer', 'senior_officer', 'document_verifier', 'finance_officer', 'support_staff', 'admin', 'super_admin'],
            'document.upload' => ['applicant', 'agent', 'admin', 'super_admin'],
            'document.preview' => ['applicant', 'agent', 'case_officer', 'senior_officer', 'document_verifier', 'admin', 'super_admin'],
            'document.review' => ['case_officer', 'senior_officer', 'document_verifier', 'admin', 'super_admin'],
            'application.request_info' => ['case_officer', 'senior_officer', 'document_verifier', 'admin', 'super_admin'],
            'application.assign' => ['case_officer', 'senior_officer', 'admin', 'super_admin'],
            'appointment.schedule' => ['applicant', 'agent', 'case_officer', 'senior_officer', 'admin', 'super_admin'],
            // Admin is explicitly ❌ here (§3.3) — configuration authority is
            // deliberately separated from case-decision authority.
            'application.approve' => ['case_officer', 'senior_officer', 'super_admin'],
            'application.override' => ['senior_officer', 'super_admin'],
            'refund.initiate' => ['finance_officer', 'super_admin'],
            'refund.approve' => ['admin', 'super_admin'],
            'config.manage' => ['admin', 'super_admin'],
            'user.manage' => ['admin', 'super_admin'],
            'report.view' => ['agent', 'case_officer', 'senior_officer', 'finance_officer', 'admin', 'super_admin'],
            'audit.view' => ['case_officer', 'senior_officer', 'finance_officer', 'admin', 'super_admin'],
        ];

        foreach ($grants as $permissionName => $roleNames) {
            $permission = $permissions[$permissionName];

            foreach ($roleNames as $roleName) {
                $roles[$roleName]->givePermissionTo($permission);
            }
        }
    }
}
