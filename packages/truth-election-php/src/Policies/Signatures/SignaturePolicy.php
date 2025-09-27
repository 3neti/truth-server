<?php

namespace TruthElection\Policies\Signatures;

interface SignaturePolicy
{
    /**
     * @param array<int, mixed> $signatures  // array/DTO/collection entries
     * @throws \RuntimeException if not satisfied and $force === false
     */
    public function assertSatisfied(array $signatures, bool $force): void;
}
