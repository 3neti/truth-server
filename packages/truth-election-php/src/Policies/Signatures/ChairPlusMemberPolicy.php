<?php

namespace TruthElection\Policies\Signatures;

use TruthElection\Data\ElectoralInspectorData;
use Spatie\LaravelData\Optional;

final class ChairPlusMemberPolicy implements SignaturePolicy
{
    public function assertSatisfied(array $signatures, bool $force): void
    {
        $haveChair = false;
        $memberCnt = 0;

        foreach ($signatures as $s) {
            $data = ElectoralInspectorData::from($s);

            if ($data->signature instanceof Optional || $data->signature === null) {
                continue;
            }

            if ($data->role->value === 'chairperson') {
                $haveChair = true;
            } elseif ($data->role->value === 'member') {
                $memberCnt++;
            }
        }

        if (!($haveChair && $memberCnt >= 1) && !$force) {
            throw new \RuntimeException('Missing required signatures (need chair + at least one member).');
        }
    }

//    public function assertSatisfied(array $signatures, bool $force): void
//    {
//        $haveChair = false;
//        $memberCnt = 0;
//
//        foreach ($signatures as $s) {
//            if ($s instanceof \Illuminate\Support\Collection) $s = $s->toArray();
//            elseif (is_object($s) && method_exists($s, 'toArray')) $s = $s->toArray();
//
//            $role = null;
//            if (is_array($s)) {
//                $role = $s['role'] ?? null;
//                if (is_object($role) && property_exists($role, 'value')) $role = $role->value;
//            } elseif (is_object($s) && property_exists($s, 'role')) {
//                $role = $s->role;
//                if (is_object($role) && property_exists($role, 'value')) $role = $role->value;
//            }
//
//            if ($role === 'chairperson') $haveChair = true;
//            if ($role === 'member')      $memberCnt++;
//        }
//
//        if (!($haveChair && $memberCnt >= 1) && !$force) {
//            throw new \RuntimeException('Missing required signatures (need chair + at least one member).');
//        }
//    }
}
