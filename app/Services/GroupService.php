<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GroupService
{
    /**
     * Resolve group context for message handling.
     *
     * - Creates a new active group when first seen.
     * - Updates name when available for active groups.
     * - Returns null when group is explicitly deactivated.
     */
    public function resolveMessageGroup(string $lineGroupId, ?string $name = null): ?Group
    {
        $group = Group::where('line_group_id', $lineGroupId)->first();

        if (!$group) {
            return $this->ensureActiveGroup($lineGroupId, $name);
        }

        if (!$group->is_active) {
            return null;
        }

        if ($name && trim($name) !== '' && $group->name !== trim($name)) {
            $group->update(['name' => trim($name)]);
        }

        return $group;
    }

    /**
     * Create or reactivate a LINE group record.
     */
    public function ensureActiveGroup(string $lineGroupId, ?string $name = null): Group
    {
        $group = Group::firstOrNew(['line_group_id' => $lineGroupId]);

        if (!$group->exists) {
            $group->name = $name && trim($name) !== '' ? trim($name) : 'กลุ่ม LINE';
        } elseif ($name && trim($name) !== '') {
            $group->name = trim($name);
        }

        $group->is_active = true;
        $group->save();

        return $group;
    }

    /**
     * Mark a LINE group as inactive.
     */
    public function deactivateGroup(string $lineGroupId): void
    {
        Group::where('line_group_id', $lineGroupId)->update(['is_active' => false]);
    }

    /**
     * Rename group by LINE group ID.
     */
    public function renameGroup(string $lineGroupId, string $newName): Group
    {
        return $this->ensureActiveGroup($lineGroupId, trim($newName));
    }

    /**
     * Resolve sender as a local user and track group membership.
     */
    public function resolveGroupMember(Group $group, string $lineUserId, ?array $profile = null): User
    {
        $user = User::where('line_user_id', $lineUserId)->first();

        if (!$user) {
            $displayName = $profile['displayName'] ?? 'LINE User';

            $user = User::create([
                'name' => $displayName,
                'line_user_id' => $lineUserId,
                'avatar_url' => $profile['pictureUrl'] ?? null,
                'password' => Hash::make(Str::random(40)),
            ]);
        } elseif (!$user->avatar_url && !empty($profile['pictureUrl'])) {
            $user->update(['avatar_url' => $profile['pictureUrl']]);
        }

        GroupMember::firstOrCreate(
            [
                'group_id' => $group->id,
                'user_id' => $user->id,
            ],
            [
                'role' => $this->defaultRoleForNewMember($group->id),
                'joined_at' => now(),
            ]
        );

        return $user;
    }

    /**
     * Determine role for newly tracked group member.
     */
    private function defaultRoleForNewMember(int $groupId): string
    {
        return GroupMember::where('group_id', $groupId)->exists() ? 'member' : 'admin';
    }
}
