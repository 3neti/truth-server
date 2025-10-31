<?php

namespace App\Services;

use RuntimeException;

class BubbleIdGenerator
{
    protected ElectionConfigLoader $configLoader;

    public function __construct(ElectionConfigLoader $configLoader)
    {
        $this->configLoader = $configLoader;
    }

    /**
     * Generate bubble metadata from mapping.yaml
     *
     * Returns array keyed by bubble_id (e.g., "A1") with metadata:
     * [
     *   'bubble_id' => 'A1',
     *   'candidate_code' => 'LD_001',
     *   'position_code' => 'PUNONG_BARANGAY-1402702011',
     *   'candidate_name' => 'Leonardo DiCaprio',
     *   'candidate_alias' => 'LD'
     * ]
     */
    public function generateBubbleMetadata(): array
    {
        $mapping = $this->configLoader->loadMapping();
        $election = $this->configLoader->loadElection();

        if (! isset($mapping['marks']) || ! is_array($mapping['marks'])) {
            throw new RuntimeException('Invalid mapping.yaml: missing or invalid "marks" array');
        }

        $bubbles = [];

        foreach ($mapping['marks'] as $mark) {
            $key = $mark['key'] ?? null;
            $candidateCode = $mark['value'] ?? null;

            if (! $key || ! $candidateCode) {
                throw new RuntimeException('Invalid mark in mapping.yaml: missing key or value');
            }

            // Find position for this candidate
            $positionCode = $this->configLoader->findPositionByCandidate($candidateCode);

            if (! $positionCode) {
                throw new RuntimeException("Cannot find position for candidate: {$candidateCode}");
            }

            // Get full candidate details
            $candidateDetails = $this->getCandidateFromElection($election, $positionCode, $candidateCode);

            if (! $candidateDetails) {
                throw new RuntimeException("Cannot find candidate details for: {$candidateCode}");
            }

            // Store bubble metadata with simple grid reference as ID
            $bubbles[$key] = [
                'bubble_id' => $key,
                'candidate_code' => $candidateCode,
                'position_code' => $positionCode,
                'candidate_name' => $candidateDetails['name'] ?? null,
                'candidate_alias' => $candidateDetails['alias'] ?? null,
            ];
        }

        return $bubbles;
    }

    /**
     * Lookup bubble metadata by bubble_id
     *
     * @return array|null Bubble metadata or null if not found
     */
    public function getBubbleMetadata(string $bubbleId): ?array
    {
        $metadata = $this->generateBubbleMetadata();

        return $metadata[$bubbleId] ?? null;
    }

    /**
     * Get all bubbles for a specific position
     *
     * @return array Array of bubble metadata filtered by position
     */
    public function getBubblesByPosition(string $positionCode): array
    {
        $metadata = $this->generateBubbleMetadata();

        return array_filter($metadata, function ($bubble) use ($positionCode) {
            return $bubble['position_code'] === $positionCode;
        });
    }

    /**
     * Get candidate from election data
     */
    protected function getCandidateFromElection(array $election, string $positionCode, string $candidateCode): ?array
    {
        if (! isset($election['candidates'][$positionCode])) {
            return null;
        }

        foreach ($election['candidates'][$positionCode] as $candidate) {
            if (($candidate['code'] ?? null) === $candidateCode) {
                return $candidate;
            }
        }

        return null;
    }
}
